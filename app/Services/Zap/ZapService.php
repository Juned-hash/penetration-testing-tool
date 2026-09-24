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
        protected ScanService $scanService
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
            if (!is_writable($workDir)) {
                throw new \Exception("Working directory [{$workDir}] is not writable.");
            }
        } catch (Throwable $e) {
            $err = 'Preflight failed: Unable to initialize writable working directory on host.';
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
            $containerName = "pentest-zap-scan-{$scan->id}";

            // Execute container
            $result = $this->runner->runAutomationFramework($hostYamlPath, $workDir, $isLocalTarget, $containerName);

            $reportGenerated = File::exists($hostJsonReportPath) && filesize($hostJsonReportPath) > 0;

            if ($result['timedOut'] ?? false) {
                $timeoutSeconds = config('zap.timeout', 3600);
                $failMessage = "ZAP_TIMEOUT: ZAP Docker assessment exceeded configured timeout of {$timeoutSeconds} seconds.";
                $this->scanService->updateStatus($scan, 'failed', $failMessage);
                $scan->update(['failure_reason' => $failMessage]);

                ScanLog::create([
                    'scan_id' => $scan->id,
                    'level' => 'error',
                    'phase' => 'zap_timeout',
                    'message' => $this->sanitizeError("ZAP_TIMEOUT: Container [{$containerName}] exceeded timeout of {$timeoutSeconds}s. Cleanup: " . (($result['cleanupSuccess'] ?? false) ? 'Success' : 'Failed')),
                ]);
                return false;
            }

            if (!$result['success'] && !$reportGenerated) {
                $rawError = $this->extractActualError($result);
                $failMessage = 'OWASP ZAP Docker execution failed: ' . $this->sanitizeError($rawError);
                $this->scanService->updateStatus($scan, 'failed', $failMessage);
                $scan->update(['failure_reason' => $failMessage]);
                // Note: Preserve scan directory and files for diagnostics on failure
                return false;
            }

            // Audit Log: ZAP_STARTED (Requirement #12: recorded ONLY after Docker process succeeds or report is generated)
            ScanLog::create([
                'scan_id' => $scan->id,
                'level' => 'info',
                'phase' => 'zap_started',
                'message' => 'OWASP ZAP container started.',
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

            return true;
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            $err = 'Assessment execution failure: Unable to decrypt stored authentication credentials. Please re-enter and save the credentials for this assessment.';
            $this->scanService->updateStatus($scan, 'failed', $err);
            $scan->update(['failure_reason' => $err]);
            return false;
        }
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
}
