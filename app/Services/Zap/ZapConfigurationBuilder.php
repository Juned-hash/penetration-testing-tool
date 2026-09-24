<?php

namespace App\Services\Zap;

use App\Models\Scan;

class ZapConfigurationBuilder
{
    /**
     * Build the ZAP Automation Framework configuration structure as an array.
     *
     * @param Scan $scan
     * @param string $reportDir
     * @param string $reportFile
     * @return array
     */
    public function buildArray(Scan $scan, string $reportDir, string $reportFile = 'report.json', ?string $overrideTargetUrl = null): array
    {
        $scan->loadMissing(['scanScopes', 'scanConfiguration', 'authenticationConfiguration']);

        $contextName = 'Target Context';
        $targetUrl = rtrim($overrideTargetUrl ?: $scan->target_url, '/');

        // Target URLs and Included Paths
        $urls = [$targetUrl];
        $includePaths = [];

        $includedScopes = $scan->scanScopes->where('type', 'include');
        if ($includedScopes->isEmpty()) {
            $includePaths[] = preg_quote($targetUrl, '#') . '.*';
        } else {
            foreach ($includedScopes as $scope) {
                $includePaths[] = $this->convertPathToRegex($targetUrl, $scope->path);
            }
        }

        // Excluded Paths
        $excludePaths = [];
        foreach ($scan->scanScopes->where('type', 'exclude') as $scope) {
            $excludePaths[] = $this->convertPathToRegex($targetUrl, $scope->path);
        }

        // Context Structure
        $context = [
            'name' => $contextName,
            'urls' => $urls,
            'includePaths' => array_values(array_unique($includePaths)),
        ];

        $cleanExcludePaths = array_values(array_unique($excludePaths));
        if (!empty($cleanExcludePaths)) {
            $context['excludePaths'] = $cleanExcludePaths;
        }

        $users = [];
        $authConfig = $scan->authenticationConfiguration;
        $scanConfig = $scan->scanConfiguration;

        // Authentication Setup if enabled
        if ($authConfig && $authConfig->mode !== 'none') {
            $authentication = [
                'method' => $authConfig->mode,
                'parameters' => [],
            ];

            if ($authConfig->login_url) {
                $loginUrl = $overrideTargetUrl
                    ? (new ZapRunner())->translateTargetUrlForDocker($authConfig->login_url)
                    : $authConfig->login_url;
                $authentication['parameters']['loginUrl'] = $loginUrl;
            }

            if ($authConfig->mode === 'form') {
                $usernameParam = $authConfig->username_field ?? 'username';
                $passwordParam = $authConfig->password_field ?? 'password';
                $loginRequestUrl = $authConfig->login_url
                    ? ($overrideTargetUrl ? (new ZapRunner())->translateTargetUrlForDocker($authConfig->login_url) : $authConfig->login_url)
                    : $targetUrl;
                $authentication['parameters']['loginRequestUrl'] = $loginRequestUrl;
                $authentication['parameters']['loginRequestBody'] = "{$usernameParam}={%username%}&{$passwordParam}={%password%}";
            }

            if ($authConfig->logged_in_indicator || $authConfig->logged_out_indicator) {
                $verification = ['method' => 'response'];
                if ($authConfig->logged_in_indicator) {
                    $verification['loggedInRegex'] = preg_quote($authConfig->logged_in_indicator, '#');
                }
                if ($authConfig->logged_out_indicator) {
                    $verification['loggedOutRegex'] = preg_quote($authConfig->logged_out_indicator, '#');
                }
                $authentication['verification'] = $verification;
            }

            $context['authentication'] = $authentication;

            if ($authConfig->username || $authConfig->password || $authConfig->token_value) {
                $credentials = [];
                if ($authConfig->username) {
                    $credentials['username'] = $authConfig->username;
                }
                if ($authConfig->password) {
                    $credentials['password'] = $authConfig->password;
                }
                if ($authConfig->token_name && $authConfig->token_value) {
                    $credentials['tokenName'] = $authConfig->token_name;
                    $credentials['tokenValue'] = $authConfig->token_value;
                }

                $users[] = [
                    'name' => 'AssessmentUser',
                    'credentials' => $credentials,
                ];
            }
        }

        if (!empty($users)) {
            $context['users'] = $users;
        }

        // Construct Jobs List
        $jobs = [];

        // Spider / Crawling Job
        if (!$scanConfig || $scanConfig->spider_enabled) {
            $spiderJob = [
                'type' => 'spider',
                'parameters' => [
                    'context' => $contextName,
                    'url' => $targetUrl,
                    'maxDuration' => 10,
                ],
            ];
            if (!empty($users)) {
                $spiderJob['parameters']['user'] = 'AssessmentUser';
            }
            $jobs[] = $spiderJob;
        }

        // Passive Scan Job
        if (!$scanConfig || $scanConfig->passive_scan_enabled) {
            $jobs[] = [
                'type' => 'passiveScan-wait',
                'parameters' => [
                    'maxDuration' => 5,
                ],
            ];
        }

        // Active Scan Job
        if (!$scanConfig || $scanConfig->active_scan_enabled) {
            $maxDuration = (int) config('zap.active_scan_max_duration', 20);
            $activeScanJob = [
                'type' => 'activeScan',
                'parameters' => [
                    'context' => $contextName,
                ],
            ];
            if ($maxDuration > 0) {
                $activeScanJob['parameters']['maxScanDurationInMins'] = $maxDuration;
            }
            if (!empty($users)) {
                $activeScanJob['parameters']['user'] = 'AssessmentUser';
            }
            $jobs[] = $activeScanJob;
        }

        // JSON Report Generation Job
        $jobs[] = [
            'type' => 'report',
            'parameters' => [
                'template' => 'traditional-json',
                'reportDir' => $reportDir,
                'reportFile' => $reportFile,
                'reportTitle' => "OWASP ZAP Assessment Report: {$scan->name}",
                'displayReport' => false,
            ],
        ];

        return [
            'env' => [
                'contexts' => [$context],
                'parameters' => [
                    'failOnError' => false,
                    'failOnWarning' => false,
                    'progressToStdout' => false,
                ],
            ],
            'jobs' => $jobs,
        ];
    }

    /**
     * Build the YAML configuration string for the ZAP Automation Framework.
     *
     * @param Scan $scan
     * @param string $reportDir
     * @param string $reportFile
     * @return string
     */
    public function buildYaml(Scan $scan, string $reportDir, string $reportFile = 'report.json', ?string $overrideTargetUrl = null): string
    {
        $data = $this->buildArray($scan, $reportDir, $reportFile, $overrideTargetUrl);

        if (class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            return \Symfony\Component\Yaml\Yaml::dump($data, 6, 2);
        }

        return $this->dumpYamlFallback($data);
    }

    /**
     * Convert path pattern into URL regex.
     *
     * @param string $targetUrl
     * @param string $path
     * @return string
     */
    protected function convertPathToRegex(string $targetUrl, string $path): string
    {
        if ($path === '/*' || $path === '*') {
            return preg_quote($targetUrl, '#') . '.*';
        }

        $cleanPath = '/' . ltrim($path, '/');
        $regexPath = str_replace('\*', '.*', preg_quote($cleanPath, '#'));

        return preg_quote($targetUrl, '#') . $regexPath;
    }

    /**
     * Simple fallback YAML dumper if Symfony Yaml component is not installed.
     *
     * @param array $array
     * @param int $indent
     * @return string
     */
    protected function dumpYamlFallback(array $array, int $indent = 0): string
    {
        $yaml = '';
        $prefix = str_repeat(' ', $indent);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (array_keys($value) === range(0, count($value) - 1)) {
                    // Sequential array
                    $yaml .= "{$prefix}{$key}:\n";
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $yaml .= "{$prefix}  -\n";
                            $yaml .= $this->dumpYamlFallback($item, $indent + 4);
                        } else {
                            $escaped = is_string($item) ? '"' . addcslashes($item, '"\\') . '"' : (is_bool($item) ? ($item ? 'true' : 'false') : $item);
                            $yaml .= "{$prefix}  - {$escaped}\n";
                        }
                    }
                } else {
                    // Associative array
                    $yaml .= "{$prefix}{$key}:\n";
                    $yaml .= $this->dumpYamlFallback($value, $indent + 2);
                }
            } else {
                $formattedValue = is_bool($value) ? ($value ? 'true' : 'false') : (is_numeric($value) ? $value : '"' . addcslashes((string)$value, '"\\') . '"');
                $yaml .= "{$prefix}{$key}: {$formattedValue}\n";
            }
        }

        return $yaml;
    }
}
