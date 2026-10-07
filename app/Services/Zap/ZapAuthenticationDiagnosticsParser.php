<?php

namespace App\Services\Zap;

use App\Models\Scan;

class ZapAuthenticationDiagnosticsParser
{
    /**
     * Parse ZAP execution output (stdout/stderr) and determine authentication status & diagnostics.
     *
     * @param array $result Process result containing 'output' and 'error' strings
     * @param Scan $scan
     * @param array|null $authReport Structured JSON auth-report data if available
     * @return array Structured diagnostic data
     */
    public function parse(array $result, Scan $scan, ?array $authReport = null): array
    {
        $scan->loadMissing('authenticationConfiguration');
        $authConfig = $scan->authenticationConfiguration;

        if (!$authConfig || $authConfig->mode === 'none') {
            return [
                'status' => 'none',
                'status_label' => 'NONE',
                'username_detected' => false,
                'username_field_status' => 'NOT IDENTIFIED',
                'password_detected' => false,
                'password_field_status' => 'NOT IDENTIFIED',
                'login_attempted' => false,
                'login_attempt_status' => 'NO',
                'successful_login_count' => 0,
                'failed_login_count' => 0,
                'session_management_detected' => false,
                'session_management_status' => 'NOT IDENTIFIED',
                'verification_detected' => false,
                'verification_status' => 'NOT IDENTIFIED',
                'failure_reasons' => [],
                'failure_reason_text' => 'None',
                'message' => 'No authentication configured for this assessment.',
                'login_url' => 'N/A',
                'user' => 'N/A',
                'diagnostic_steps' => [],
                'log_message' => 'ZAP_AUTH_DIAGNOSTICS Status: NONE | Message: No authentication configured.',
            ];
        }

        $rawOutput = ($result['output'] ?? '') . "\n" . ($result['error'] ?? '');
        $sanitizedOutput = $this->sanitizeOutput($rawOutput);

        $loginUrl = $authConfig->login_url ?: $scan->target_url;

        // Structured Auth Report Evaluation (Primary Source of Truth)
        $hasStructuredSuccess = false;
        $hasStructuredFailure = false;
        $hasReportData = !empty($authReport);

        $authSummaryAuthPassed = null;
        $authSummarySessionPassed = null;
        $authSummaryVerifPassed = null;

        $statSuccess = 0;
        $statLoggedin = 0;
        $statFailure = 0;
        $statLoggedout = 0;
        $statUnknown = 0;

        if ($hasReportData) {
            // 1. Inspect summaryItems
            $summaryItems = $authReport['summaryItems'] ?? [];
            if (is_array($summaryItems)) {
                foreach ($summaryItems as $item) {
                    if (!is_array($item)) continue;
                    $key = $item['key'] ?? '';
                    $passed = (bool) ($item['passed'] ?? false);

                    if ($key === 'auth.summary.auth' || $key === 'summary.auth') {
                        $authSummaryAuthPassed = $passed;
                    } elseif ($key === 'auth.summary.session' || $key === 'summary.session') {
                        $authSummarySessionPassed = $passed;
                    } elseif ($key === 'auth.summary.verif' || $key === 'summary.verif') {
                        $authSummaryVerifPassed = $passed;
                    }
                }
            }

            // Fallback lookup in authReport if summaryItems omitted key
            if ($authSummaryAuthPassed === null) {
                $metricVal = $this->extractReportMetric($authReport, ['auth.summary.auth', 'summary.auth']);
                if ($metricVal !== null) {
                    $authSummaryAuthPassed = ($metricVal === true || $metricVal === 1 || $metricVal === 'true');
                }
            }
            if ($authSummarySessionPassed === null) {
                $metricVal = $this->extractReportMetric($authReport, ['auth.summary.session', 'summary.session']);
                if ($metricVal !== null) {
                    $authSummarySessionPassed = ($metricVal === true || $metricVal === 1 || $metricVal === 'true');
                }
            }
            if ($authSummaryVerifPassed === null) {
                $metricVal = $this->extractReportMetric($authReport, ['auth.summary.verif', 'summary.verif']);
                if ($metricVal !== null) {
                    $authSummaryVerifPassed = ($metricVal === true || $metricVal === 1 || $metricVal === 'true');
                }
            }

            // 2. Inspect statistics
            $statistics = $authReport['statistics'] ?? [];
            if (is_array($statistics)) {
                foreach ($statistics as $item) {
                    if (!is_array($item)) continue;
                    $key = $item['key'] ?? '';
                    $val = (int) ($item['value'] ?? 0);

                    if ($key === 'stats.auth.success' || $key === 'auth.success') {
                        $statSuccess += $val;
                    } elseif ($key === 'stats.auth.state.loggedin' || $key === 'auth.state.loggedin') {
                        $statLoggedin += $val;
                    } elseif ($key === 'stats.auth.failure' || $key === 'stats.auth.failed' || $key === 'auth.failure') {
                        $statFailure += $val;
                    } elseif ($key === 'stats.auth.state.loggedout' || $key === 'auth.state.loggedout') {
                        $statLoggedout += $val;
                    } elseif ($key === 'stats.auth.state.unknown' || $key === 'auth.state.unknown') {
                        $statUnknown += $val;
                    }
                }
            }

            // Fallback lookup in authReport if statistics array omitted key
            if ($statSuccess === 0) {
                $statSuccess = (int) $this->extractReportMetric($authReport, ['stats.auth.success', 'auth.success', 'stats.success', 'success'], 0);
            }
            if ($statLoggedin === 0) {
                $statLoggedin = (int) $this->extractReportMetric($authReport, ['stats.auth.state.loggedin', 'auth.state.loggedin', 'stats.state.loggedin', 'stats.loggedin', 'loggedin'], 0);
            }
            if ($statFailure === 0) {
                $statFailure = (int) $this->extractReportMetric($authReport, ['stats.auth.failure', 'stats.auth.failed', 'auth.failure', 'stats.failure', 'failure'], 0);
            }
            if ($statLoggedout === 0) {
                $statLoggedout = (int) $this->extractReportMetric($authReport, ['stats.auth.state.loggedout', 'auth.state.loggedout', 'stats.state.loggedout', 'stats.loggedout', 'loggedout'], 0);
            }

            $hasStructuredSuccess = ($authSummaryAuthPassed === true) || ($statSuccess > 0) || ($statLoggedin > 0);
            $hasStructuredFailure = (($authSummaryAuthPassed === false) || ($statFailure > 0) || ($statLoggedout > 0)) && !$hasStructuredSuccess;
        }

        if ($hasStructuredSuccess) {
            $status = 'success';
            $statusLabel = 'SUCCESS';
            $usernameDetected = true;
            $passwordDetected = true;
            $loginAttempted = true;

            $successfulLoginCount = max(1, $statSuccess, $statLoggedin, $this->countSuccessfulLogins($rawOutput));
            $failedLoginCount = max($statFailure, $statLoggedout, $this->countFailedLogins($rawOutput));
            $sessionManagementDetected = ($authSummarySessionPassed !== false);
            $verificationDetected = ($authSummaryVerifPassed !== false);
            $failureReasons = [];
            $message = 'Authentication appeared to work.';
        } elseif ($hasStructuredFailure) {
            $status = 'failed';
            $statusLabel = 'FAILED';
            $usernameDetected = $this->detectUsernameField($rawOutput);
            $passwordDetected = $this->detectPasswordField($rawOutput);
            $loginAttempted = true;

            $successfulLoginCount = max($statSuccess, $statLoggedin, $this->countSuccessfulLogins($rawOutput));
            $failedLoginCount = max(1, $statFailure, $statLoggedout, $this->countFailedLogins($rawOutput));
            $sessionManagementDetected = ($authSummarySessionPassed === true) || $this->detectSessionManagement($rawOutput);
            $verificationDetected = ($authSummaryVerifPassed === true) || $this->detectVerification($rawOutput);
            $failureReasons = $this->extractFailureReasons($rawOutput);
            $message = "ZAP authentication failed for user 'AssessmentUser' at login URL [{$loginUrl}] (indicated by ZAP authentication report).";
        } else {
            // Fallback: Detect structured attributes from raw stdout/stderr
            $usernameDetected = $this->detectUsernameField($rawOutput);
            $passwordDetected = $this->detectPasswordField($rawOutput);
            $loginAttempted = $this->detectLoginAttempt($rawOutput);

            $successfulLoginCount = $this->countSuccessfulLogins($rawOutput);
            $failedLoginCount = $this->countFailedLogins($rawOutput);

            $sessionManagementDetected = $this->detectSessionManagement($rawOutput);
            $verificationDetected = $this->detectVerification($rawOutput);
            $failureReasons = $this->extractFailureReasons($rawOutput);

            $hasExplicitSuccess = $this->hasSuccessMarkers($rawOutput) || $successfulLoginCount > 0;
            $hasExplicitFailure = $this->hasFailureMarkers($rawOutput) || $failedLoginCount > 0 || !empty($failureReasons);

            // Determine status: SUCCESS, FAILED, UNKNOWN
            if ($hasExplicitSuccess && !$hasExplicitFailure) {
                $status = 'success';
                $statusLabel = 'SUCCESS';
                $message = "User 'AssessmentUser' authenticated successfully via {$authConfig->mode} authentication.";
            } elseif ($hasExplicitFailure) {
                $status = 'failed';
                $statusLabel = 'FAILED';
                $reasonStr = !empty($failureReasons) ? implode('; ', $failureReasons) : 'Verification or login step failed.';
                $message = "ZAP authentication failed for user 'AssessmentUser' at login URL [{$loginUrl}]. Reason: {$reasonStr}";
            } else {
                $status = 'unknown';
                $statusLabel = 'UNKNOWN';
                $message = "ZAP authentication status is ambiguous/unknown for user 'AssessmentUser'. Neither explicit success nor failure markers were reported in execution logs.";
            }
        }

        $failureReasonText = !empty($failureReasons)
            ? implode('; ', $failureReasons)
            : ($status === 'unknown' ? 'Neither explicit success nor failure markers were reported in execution logs.' : 'None');

        $steps = $this->extractDiagnosticSteps($sanitizedOutput);

        $logMessage = sprintf(
            "ZAP_AUTH_DIAGNOSTICS Status: %s | Username Field: %s | Password Field: %s | Login Attempt: %s | Successful Logins: %d | Failed Logins: %d | Session Management: %s | Verification: %s | Failure Reason: %s | Message: %s",
            $statusLabel,
            $usernameDetected ? 'IDENTIFIED' : 'NOT IDENTIFIED',
            $passwordDetected ? 'IDENTIFIED' : 'NOT IDENTIFIED',
            $loginAttempted ? 'YES' : 'NO',
            $successfulLoginCount,
            $failedLoginCount,
            $sessionManagementDetected ? 'IDENTIFIED' : 'NOT IDENTIFIED',
            $verificationDetected ? 'IDENTIFIED' : 'NOT IDENTIFIED',
            $failureReasonText,
            $message
        );

        return [
            'status' => $status,
            'status_label' => $statusLabel,
            'username_detected' => $usernameDetected,
            'username_field_status' => $usernameDetected ? 'IDENTIFIED' : 'NOT IDENTIFIED',
            'password_detected' => $passwordDetected,
            'password_field_status' => $passwordDetected ? 'IDENTIFIED' : 'NOT IDENTIFIED',
            'login_attempted' => $loginAttempted,
            'login_attempt_status' => $loginAttempted ? 'YES' : 'NO',
            'successful_login_count' => $successfulLoginCount,
            'failed_login_count' => $failedLoginCount,
            'session_management_detected' => $sessionManagementDetected,
            'session_management_status' => $sessionManagementDetected ? 'IDENTIFIED' : 'NOT IDENTIFIED',
            'verification_detected' => $verificationDetected,
            'verification_status' => $verificationDetected ? 'IDENTIFIED' : 'NOT IDENTIFIED',
            'failure_reasons' => $failureReasons,
            'failure_reason_text' => $failureReasonText,
            'message' => $message,
            'login_url' => $loginUrl,
            'user' => 'AssessmentUser',
            'diagnostic_steps' => $steps,
            'log_message' => $logMessage,
        ];
    }

    /**
     * Extract report metric from auth-report data trying multiple key shapes.
     *
     * @param array $report
     * @param array $keys
     * @param mixed $default
     * @return mixed
     */
    protected function extractReportMetric(array $report, array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            $val = $this->getValueByDotKey($report, $key);
            if ($val !== null) {
                return $val;
            }
        }
        return $default;
    }

    /**
     * Retrieve nested array value using dot notation or direct key lookup.
     *
     * @param array $array
     * @param string $key
     * @return mixed
     */
    protected function getValueByDotKey(array $array, string $key): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        $segments = explode('.', $key);
        $current = $array;

        foreach ($segments as $i => $segment) {
            if (!is_array($current)) {
                return null;
            }
            if (array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } else {
                $remainingKey = implode('.', array_slice($segments, $i));
                if (array_key_exists($remainingKey, $current)) {
                    return $current[$remainingKey];
                }
                return null;
            }
        }

        return $current;
    }

    /**
     * Detect if username field was identified in output.
     *
     * @param string $output
     * @return bool
     */
    protected function detectUsernameField(string $output): bool
    {
        $patterns = [
            '/username\s+field/i',
            '/found\s+username/i',
            '/username_field/i',
            '/usernamefield/i',
            '/username\s+input/i',
            '/identified\s+username/i',
            '/username\s+element/i',
            '/user\s+field/i',
            '/P\d+_USERNAME/i',
            '/name=[\'"]?username[\'"]?/i',
            '/id=[\'"]?username[\'"]?/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $output)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect if password field was identified in output.
     *
     * @param string $output
     * @return bool
     */
    protected function detectPasswordField(string $output): bool
    {
        $patterns = [
            '/password\s+field/i',
            '/found\s+password/i',
            '/password_field/i',
            '/passwordfield/i',
            '/password\s+input/i',
            '/identified\s+password/i',
            '/password\s+element/i',
            '/pass\s+field/i',
            '/P\d+_PASSWORD/i',
            '/name=[\'"]?password[\'"]?/i',
            '/id=[\'"]?password[\'"]?/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $output)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect if login submission was attempted in output.
     *
     * @param string $output
     * @return bool
     */
    protected function detectLoginAttempt(string $output): bool
    {
        $patterns = [
            '/login\s+attempt/i',
            '/attempting\s+login/i',
            '/submitting\s+login/i',
            '/authenticating/i',
            '/auth\s+request\s+sent/i',
            '/browser\s+authentication\s+started/i',
            '/job\s+authentication\s+started/i',
            '/submitting\s+credentials/i',
            '/posting\s+credentials/i',
            '/navigating\s+to\s+login\s+page/i',
            '/wwv_flow\.accept/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $output)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Count explicit successful login indicators.
     *
     * @param string $output
     * @return int
     */
    protected function countSuccessfulLogins(string $output): int
    {
        $patterns = [
            '/user\s+[\'"]?AssessmentUser[\'"]?\s+logged\s+in/i',
            '/logged\s+in\s+successfully/i',
            '/browser\s+authentication\s+succeeded/i',
            '/authentication\s+successful/i',
            '/authentication\s+succeeded/i',
            '/logged\s+in\s+indicator\s+matched/i',
            '/logged\s+in\s+regex\s+matched/i',
            '/verification:\s*logged\s*in/i',
            '/authentication\s+verification\s+successful/i',
            '/auth\s+status:\s*success/i',
        ];

        $count = 0;
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $output, $matches)) {
                $count += count($matches[0]);
            }
        }

        return $count;
    }

    /**
     * Count explicit failed login indicators.
     *
     * @param string $output
     * @return int
     */
    protected function countFailedLogins(string $output): int
    {
        $patterns = [
            '/authentication\s+failed/i',
            '/authentication\s+verification\s+failed/i',
            '/user\s+[\'"]?AssessmentUser[\'"]?\s+is\s+not\s+logged\s+in/i',
            '/user\s+is\s+not\s+logged\s+in/i',
            '/user\s+[\'"]?AssessmentUser[\'"]?\s+failed\s+to\s+log\s+in/i',
            '/browser\s+authentication\s+failed/i',
            '/logged\s+out\s+indicator\s+matched/i',
            '/logged\s+out\s+regex\s+matched/i',
            '/verification:\s*logged\s*out/i',
            '/unable\s+to\s+authenticate/i',
            '/auth\s+status:\s*failed/i',
            '/failed\s+to\s+find\s+username\s+field/i',
            '/failed\s+to\s+find\s+password\s+field/i',
            '/element\s+not\s+found/i',
            '/login\s+step\s+timed\s+out/i',
        ];

        $count = 0;
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $output, $matches)) {
                $count += count($matches[0]);
            }
        }

        return $count;
    }

    /**
     * Detect if session management was identified in output.
     *
     * @param string $output
     * @return bool
     */
    protected function detectSessionManagement(string $output): bool
    {
        $patterns = [
            '/session\s+management\s+identified/i',
            '/session\s+management\s+method/i',
            '/session\s+management:\s*autodetect/i',
            '/session\s+management:/i',
            '/cookie\s+based\s+session\s+management/i',
            '/autodetect\s+session\s+management/i',
            '/session\s+management\s+response\s+identified/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $output)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect if verification method was identified in output.
     *
     * @param string $output
     * @return bool
     */
    protected function detectVerification(string $output): bool
    {
        $patterns = [
            '/verification\s+identified/i',
            '/verification\s+method/i',
            '/verification:\s*response/i',
            '/verification:\s*autodetect/i',
            '/logged\s+in\s+indicator/i',
            '/logged\s+out\s+indicator/i',
            '/logged\s+in\s+regex/i',
            '/logged\s+out\s+regex/i',
            '/verification:/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $output)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract failure reasons from execution output.
     *
     * @param string $output
     * @return array
     */
    protected function extractFailureReasons(string $output): array
    {
        $reasons = [];

        $failurePatterns = [
            '/failed\s+to\s+find\s+username\s+field[^\.\r\n]*/i',
            '/failed\s+to\s+find\s+password\s+field[^\.\r\n]*/i',
            '/authentication\s+verification\s+failed[^\.\r\n]*/i',
            '/logged\s+out\s+indicator\s+matched[^\.\r\n]*/i',
            '/element\s+not\s+found[^\.\r\n]*/i',
            '/login\s+step\s+timed\s+out[^\.\r\n]*/i',
            '/unable\s+to\s+locate[^\.\r\n]*/i',
            '/browser\s+authentication\s+failed[^\.\r\n]*/i',
        ];

        foreach ($failurePatterns as $pattern) {
            if (preg_match_all($pattern, $output, $matches)) {
                foreach ($matches[0] as $match) {
                    $trimmed = trim($match);
                    if (!empty($trimmed)) {
                        $reasons[] = mb_strimwidth($trimmed, 0, 150, '...');
                    }
                }
            }
        }

        return array_values(array_unique($reasons));
    }

    /**
     * Check if output contains explicit success markers.
     *
     * @param string $output
     * @return bool
     */
    protected function hasSuccessMarkers(string $output): bool
    {
        return $this->countSuccessfulLogins($output) > 0;
    }

    /**
     * Check if output contains explicit failure markers.
     *
     * @param string $output
     * @return bool
     */
    protected function hasFailureMarkers(string $output): bool
    {
        return $this->countFailedLogins($output) > 0;
    }

    /**
     * Extract step-by-step diagnostic lines from output.
     *
     * @param string $output
     * @return array
     */
    protected function extractDiagnosticSteps(string $output): array
    {
        $lines = explode("\n", $output);
        $steps = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) continue;

            if (preg_match('/(auth|login|browser|verification|step|indicator)/i', $trimmed)) {
                if (!str_contains($trimmed, 'java.util.prefs') && !str_contains($trimmed, 'Using JVM args')) {
                    $steps[] = mb_strimwidth($trimmed, 0, 200, '...');
                }
            }
        }

        return array_slice(array_unique($steps), 0, 10);
    }

    /**
     * Sanitize execution output to remove passwords, tokens, cookies, headers, and secret values.
     *
     * @param string $output
     * @return string
     */
    public function sanitizeOutput(string $output): string
    {
        $patterns = [
            '/(password|pass|pwd|token|secret|authorization)=[^&\s\n"\'`]+/i' => '$1=[REDACTED]',
            '/(password|tokenValue|token_value|secret):\s*"?[^\r\n"]+"?/i' => '$1: "[REDACTED]"',
            '/([?&])(session|token|auth|cookie|sid|JSESSIONID|apex_session|p_instance)=[^&\s\n"\'`]+/i' => '$1$2=[REDACTED]',
            '/(Cookie|Set-Cookie|Authorization):\s*[^\r\n]+/i' => '$1: [REDACTED]',
            '/(JSESSIONID|PHPSESSID|apex_session|session_id|p_instance)=[^;\s\r\n]+/i' => '$1=[REDACTED]',
            '/Bearer\s+[A-Za-z0-9\-\._~\+\/]+=*/i' => 'Bearer [REDACTED]',
            '/(p_arg_names|p_arg_values|p_password|p_username)=[^&\s\n"\'`]+/i' => '$1=[REDACTED]',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $output = preg_replace($pattern, $replacement, $output);
        }

        return $output;
    }
}
