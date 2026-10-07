<?php

namespace App\Services\Zap;

use App\Models\Scan;
use App\Models\ScanLog;
use App\Services\ScanService;
use Illuminate\Support\Facades\File;
use Throwable;

class ZapService
{
    public function __construct(
        protected ZapConfigurationBuilder $configBuilder,
        protected ZapRunner $runner,
        protected ZapResultParser $parser,
        protected ScanService $scanService,
        protected ZapAuthenticationDiagnosticsParser $authDiagnosticsParser
    ) {}

    /**
     * Execute the full OWASP ZAP assessment lifecycle for a Scan via Docker container.
     *
     * @param Scan $scan
     * @return bool
     */
    public function runAssessment(Scan $scan): bool
    {
        if ($scan->fresh()->status === 'cancelled') {
            return false;
        }

        // 1. Stage 1: Starting
        $this->scanService->updateStatus($scan, 'starting', 'Initializing OWASP ZAP assessment pipeline.');

        // Preflight Check 1: Target URL validation
        if (empty($scan->target_url) || !filter_var($scan->target_url, FILTER_VALIDATE_URL)) {
            $err = 'Preflight failed: Invalid target URL specified.';
            $this->scanService->updateStatus($scan, 'failed', $err);
            $scan->update(['failure_reason' => $err]);
            return false;
        }

        // Preflight Check 2: Docker CLI availability
        if (!$this->runner->isAvailable()) {
            $err = 'Preflight failed: Docker CLI executable is not installed or Docker Desktop daemon is not running.';
            $this->scanService->updateStatus($scan, 'failed', $err);
            $scan->update(['failure_reason' => $err]);
            return false;
        }

        // Preflight Check 3: ZAP Docker image availability
        $dockerImage = config('zap.docker_image', 'ghcr.io/zaproxy/zaproxy:stable');
        if (!$this->runner->isDockerImageAvailable($dockerImage)) {
            $err = "Preflight failed: OWASP ZAP Docker image [{$dockerImage}] is not available locally.";
            $this->scanService->updateStatus($scan, 'failed', $err);
            $scan->update(['failure_reason' => $err]);
            return false;
        }

        // Preflight Check 4: Working directory creation & write permissions
        $workDir = str_replace('\\', '/', storage_path("app/zap/scan_{$scan->id}"));

        try {
            File::ensureDirectoryExists($workDir);

            // Set directory permissions to 0777 because the directory is a Windows Docker bind mount
            // and must be writable by the non-root ZAP container user (zap).
            if (!@chmod($workDir, 0777)) {
                throw new \Exception("Failed to set 0777 permissions on working directory [{$workDir}].");
            }

            if (!is_writable($workDir)) {
                throw new \Exception("Working directory [{$workDir}] is not writable.");
            }
        } catch (Throwable $e) {
            $err = "Preflight failed: Unable to initialize writable working directory on host [{$workDir}]: " . $e->getMessage();
            $this->scanService->updateStatus($scan, 'failed', $err);
            $scan->update(['failure_reason' => $err]);
            return false;
        }

        // Preflight Check 5: Target URL translation for Docker container networking
        $dockerTargetUrl = $this->runner->translateTargetUrlForDocker($scan->target_url);

        $yamlFilename = 'assessment.yaml';
        $hostYamlPath = $workDir . '/' . $yamlFilename;
        $jsonReportFilename = 'report.json';
        $hostJsonReportPath = $workDir . '/' . $jsonReportFilename;

        try {
            // Build Automation Framework YAML for container (/zap/wrk/report.json inside container)
            $yamlContent = $this->configBuilder->buildYaml(
                $scan,
                '/zap/wrk',
                $jsonReportFilename,
                $dockerTargetUrl
            );
            File::put($hostYamlPath, $yamlContent);

            if (!File::exists($hostYamlPath)) {
                throw new \Exception('Failed to write ZAP Automation Framework YAML file.');
            }

            // Log sanitized ZAP Automation Framework YAML for debugging
            $sanitizedYaml = $this->configBuilder->sanitizeYamlForLogging($yamlContent);
            ScanLog::create([
                'scan_id' => $scan->id,
                'level' => 'info',
                'phase' => 'zap_yaml_config',
                'message' => "ZAP_YAML_CONFIG Generated Automation Framework YAML:\n" . $sanitizedYaml,
            ]);

            // 2. Stage 2: Running
            $this->scanService->updateStatus($scan, 'running', 'Starting OWASP ZAP Docker assessment.');

            // Diagnostic Log: DOCKER_WORKDIR (Requirement #13)
            $resolvedHostWorkDir = $this->runner->toHostPath($workDir);
            ScanLog::create([
                'scan_id' => $scan->id,
                'level' => 'info',
                'phase' => 'docker_workdir',
                'message' => "DOCKER_WORKDIR Resolved host path: {$resolvedHostWorkDir}",
            ]);

            $isLocalTarget = str_contains($scan->target_url, '127.0.0.1') || str_contains(strtolower($scan->target_url), 'localhost');
            $containerName = $this->runner->getContainerName($scan->id);

            $logCallback = function (string $phase, string $message) use ($scan) {
                ScanLog::create([
                    'scan_id' => $scan->id,
                    'level' => 'info',
                    'phase' => $phase,
                    'message' => $message,
                ]);
            };

            // Execute container
            $result = $this->runner->runAutomationFramework(
                $hostYamlPath,
                $workDir,
                $isLocalTarget,
                $containerName,
                $scan->id,
                $logCallback
            );

            $reportGenerated = File::exists($hostJsonReportPath) && filesize($hostJsonReportPath) > 0;

            if ($result['timedOut'] ?? false) {
                $timeoutSeconds = config('zap.timeout', 3600);
                $failMessage = "ZAP_TIMEOUT: ZAP Docker assessment exceeded configured timeout of {$timeoutSeconds} seconds.";
                $this->scanService->updateStatus($scan, 'failed', $failMessage);
                $scan->update(['failure_reason' => $failMessage]);

                ScanLog::create([
                    'scan_id' => $scan->id,
                    'level' => 'warning',
                    'phase' => 'zap_debug_artifacts',
                    'message' => "ZAP_DEBUG_ARTIFACTS_RETAINED: Temporary ZAP work directory retained at {$resolvedHostWorkDir} because ZAP execution failed.",
                ]);

                $this->logSanitizedFailureDetails($scan, $result, $resolvedHostWorkDir, true);

                return false;
            }

            if (!$result['success'] && !$reportGenerated) {
                $rawError = $this->extractActualError($result);
                $failMessage = 'OWASP ZAP Docker execution failed: ' . $this->sanitizeError($rawError);
                $this->scanService->updateStatus($scan, 'failed', $failMessage);
                $scan->update(['failure_reason' => $failMessage]);

                ScanLog::create([
                    'scan_id' => $scan->id,
                    'level' => 'warning',
                    'phase' => 'zap_debug_artifacts',
                    'message' => "ZAP_DEBUG_ARTIFACTS_RETAINED: Temporary ZAP work directory retained at {$resolvedHostWorkDir} because ZAP execution failed.",
                ]);

                $this->logSanitizedFailureDetails($scan, $result, $resolvedHostWorkDir, false);

                return false;
            }

            // Audit Log: ZAP_STARTED (Requirement #12: recorded ONLY after Docker process succeeds or report is generated)
            ScanLog::create([
                'scan_id' => $scan->id,
                'level' => 'info',
                'phase' => 'zap_started',
                'message' => 'OWASP ZAP container started.',
            ]);

            // Parse & Log Authentication Diagnostics
            $hostAuthReportPath = $workDir . '/auth-report.json';
            $authReport = null;
            if (File::exists($hostAuthReportPath)) {
                $rawAuthReport = @File::get($hostAuthReportPath);
                if ($rawAuthReport !== false && $rawAuthReport !== '') {
                    $decoded = json_decode($rawAuthReport, true);
                    if (is_array($decoded)) {
                        $authReport = $decoded;
                    }
                }
            }

            $authDiagnostics = $this->authDiagnosticsParser->parse($result, $scan, $authReport);
            $authLogMessage = sprintf(
                "ZAP_AUTH_DIAGNOSTICS Status: %s | Message: %s | Login URL: %s | User: %s",
                strtoupper($authDiagnostics['status']),
                $authDiagnostics['message'],
                $authDiagnostics['login_url'] ?? 'N/A',
                $authDiagnostics['user'] ?? 'AssessmentUser'
            );

            ScanLog::create([
                'scan_id' => $scan->id,
                'level' => $authDiagnostics['status'] === 'failed' ? 'warning' : 'info',
                'phase' => 'zap_auth_diagnostics',
                'message' => $authLogMessage,
            ]);

            // 3. Stage 3: Processing Results
            $this->scanService->updateStatus($scan, 'processing_results', 'Processing OWASP ZAP assessment results.');

            $findingCount = $this->parser->parseAndStore($scan, $hostJsonReportPath);

            // 4. Stage 4: Completed
            $this->scanService->updateStatus(
                $scan,
                'completed',
                "OWASP ZAP assessment completed successfully. Imported {$findingCount} finding(s)."
            );

            // Clean up temporary files ONLY on completion success
            if (File::exists($hostYamlPath)) {
                @File::delete($hostYamlPath);
            }
            if (File::exists($hostJsonReportPath)) {
                @File::delete($hostJsonReportPath);
            }
            if (File::exists($hostAuthReportPath)) {
                @File::delete($hostAuthReportPath);
            }

            return true;
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            $err = 'Assessment execution failure: Unable to decrypt stored authentication credentials. Please re-enter and save the credentials for this assessment.';
            $this->scanService->updateStatus($scan, 'failed', $err);
            $scan->update(['failure_reason' => $err]);
            return false;
        }
    }

    /**
     * Execute a dedicated lightweight ZAP Authentication Verification Test for a Scan.
     * Does NOT run vulnerability scanning or deep crawling.
     *
     * @param Scan $scan
     * @return array Structured authentication diagnostics result
     */
    public function testAuthentication(Scan $scan): array
    {
        $scan->loadMissing(['scanScopes', 'scanConfiguration', 'authenticationConfiguration']);
        $authConfig = $scan->authenticationConfiguration;

        if (!$authConfig || $authConfig->mode === 'none') {
            return [
                'status' => 'NONE',
                'message' => 'No authentication configured for this assessment.',
                'details' => [],
            ];
        }

        if (!$this->runner->isAvailable() || !$this->runner->isDockerImageAvailable()) {
            $err = 'Authentication verification could not be executed by the assessment worker (Docker CLI or ZAP image unavailable).';
            ScanLog::create([
                'scan_id' => $scan->id,
                'level' => 'error',
                'phase' => 'zap_auth_test_failed',
                'message' => $err,
            ]);
            return [
                'status' => 'FAILED',
                'message' => $err,
                'details' => [],
            ];
        }

        $workDir = str_replace('\\', '/', storage_path("app/zap/auth_test_{$scan->id}"));
        File::ensureDirectoryExists($workDir);
        @chmod($workDir, 0777);

        $dockerTargetUrl = $this->runner->translateTargetUrlForDocker($scan->target_url);
        $yamlFilename = 'auth_test.yaml';
        $hostYamlPath = $workDir . '/' . $yamlFilename;

        $yamlContent = $this->configBuilder->buildAuthTestYaml(
            $scan,
            '/zap/wrk',
            'auth-report.json',
            $dockerTargetUrl
        );
        File::put($hostYamlPath, $yamlContent);

        $isLocalTarget = str_contains($scan->target_url, '127.0.0.1') || str_contains(strtolower($scan->target_url), 'localhost');
        $containerName = "zap-auth-test-{$scan->id}";

        $logCallback = function (string $phase, string $message) use ($scan) {
            ScanLog::create([
                'scan_id' => $scan->id,
                'level' => 'info',
                'phase' => 'auth_test_' . $phase,
                'message' => $message,
            ]);
        };

        // Run ZAP with a short timeout (180s)
        $result = $this->runner->runAutomationFramework(
            $hostYamlPath,
            $workDir,
            $isLocalTarget,
            $containerName,
            "auth_test_{$scan->id}",
            $logCallback
        );

        $hostAuthReportPath = $workDir . '/auth-report.json';
        $authReport = null;
        if (File::exists($hostAuthReportPath)) {
            $rawAuthReport = @File::get($hostAuthReportPath);
            if ($rawAuthReport !== false && $rawAuthReport !== '') {
                $decoded = json_decode($rawAuthReport, true);
                if (is_array($decoded)) {
                    $authReport = $decoded;
                }
            }
        }

        $authDiagnostics = $this->authDiagnosticsParser->parse($result, $scan, $authReport);
        $authStatus = strtoupper($authDiagnostics['status']);

        // Log dedicated structured outcome for UI display
        ScanLog::create([
            'scan_id' => $scan->id,
            'level' => ($authStatus === 'AUTHENTICATED' || $authStatus === 'SUCCESS') ? 'info' : 'warning',
            'phase' => 'zap_auth_test_completed',
            'message' => json_encode($authDiagnostics),
        ]);

        // Cleanup temporary work directory
        File::deleteDirectory($workDir);

        return [
            'status' => $authStatus,
            'message' => $authDiagnostics['message'],
            'details' => $authDiagnostics,
        ];
    }

    /**
     * Extract relevant error details from stdout/stderr, filtering out harmless JVM startup info logs.
     *
     * @param array $result
     * @return string
     */
    protected function extractActualError(array $result): string
    {
        $output = ($result['output'] ?? '') . "\n" . ($result['error'] ?? '');
        $lines = explode("\n", $output);
        $relevantLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) continue;

            if (str_contains($trimmed, 'java.util.prefs.FileSystemPreferences')) continue;
            if (str_contains($trimmed, 'Created user preferences directory')) continue;
            if (str_contains($trimmed, 'Found Java version')) continue;
            if (str_contains($trimmed, 'Available memory')) continue;
            if (str_contains($trimmed, 'Using JVM args')) continue;

            $relevantLines[] = $trimmed;
        }

        return !empty($relevantLines) ? implode(' ', $relevantLines) : ($result['error'] ?: 'Non-zero exit code returned by Docker process.');
    }

    /**
     * Sanitize error message to prevent accidental credential or sensitive system path exposure in logs.
     *
     * @param string $error
     * @return string
     */
    protected function sanitizeError(string $error): string
    {
        // Strip line breaks and limit length for clean audit logging
        $clean = trim(preg_replace('/\s+/', ' ', $error));
        return mb_strimwidth($clean, 0, 255, '...');
    }

    /**
     * Log sanitized ZAP failure details including exit code, stdout tail, stderr tail, and retained workdir path.
     *
     * @param Scan $scan
     * @param array $result
     * @param string $retainedWorkDir
     * @param bool $timedOut
     * @return void
     */
    protected function logSanitizedFailureDetails(Scan $scan, array $result, string $retainedWorkDir, bool $timedOut): void
    {
        $exitCode = $result['exitCode'] ?? 1;
        $stdout = $this->authDiagnosticsParser->sanitizeOutput($result['output'] ?? '');
        $stderr = $this->authDiagnosticsParser->sanitizeOutput($result['error'] ?? '');

        $stdoutTail = $this->getTailLines($stdout, 15);
        $stderrTail = $this->getTailLines($stderr, 15);

        $logMsg = sprintf(
            "ZAP_EXECUTION_FAILURE Exit Code: %d | Timed Out: %s | Retained Directory: %s\n--- STDOUT TAIL ---\n%s\n--- STDERR TAIL ---\n%s",
            $exitCode,
            $timedOut ? 'YES' : 'NO',
            $retainedWorkDir,
            $stdoutTail ?: '(empty)',
            $stderrTail ?: '(empty)'
        );

        ScanLog::create([
            'scan_id' => $scan->id,
            'level' => 'error',
            'phase' => 'zap_execution_failure',
            'message' => $logMsg,
        ]);
    }

    /**
     * Get final N lines of text.
     *
     * @param string $text
     * @param int $linesCount
     * @return string
     */
    protected function getTailLines(string $text, int $linesCount = 15): string
    {
        $lines = array_filter(array_map('trim', explode("\n", $text)));
        if (empty($lines)) {
            return '';
        }
        $tail = array_slice($lines, -$linesCount);
        return implode("\n", $tail);
    }
}
