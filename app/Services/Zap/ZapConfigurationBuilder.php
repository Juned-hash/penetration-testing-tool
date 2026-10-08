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
     * @param string|null $overrideTargetUrl
     * @return array
     */
    public function buildArray(Scan $scan, string $reportDir, string $reportFile = 'report.json', ?string $overrideTargetUrl = null): array
    {
        $scan->loadMissing(['scanScopes', 'scanConfiguration', 'authenticationConfiguration']);

        $contextName = 'Target Context';
        $targetUrl = rtrim($overrideTargetUrl ?: $scan->target_url, '/');

        // Extract base origin URL (e.g. https://10.100.0.5:8443) for context boundary
        $baseUrl = $this->extractBaseUrl($targetUrl);

        $authConfig = $scan->authenticationConfiguration;
        $scanConfig = $scan->scanConfiguration;

        // Context URLs must have target URL as primary entry (urls[0])
        $urls = [$targetUrl];
        if ($authConfig && $authConfig->login_url) {
            $loginUrl = $overrideTargetUrl
                ? (new ZapRunner())->translateTargetUrlForDocker($authConfig->login_url)
                : $authConfig->login_url;
            if (!in_array($loginUrl, $urls, true)) {
                $urls[] = $loginUrl;
            }
        }

        // Included Scope Regexes
        $includePaths = [];
        $includedScopes = $scan->scanScopes->where('type', 'include');
        if ($includedScopes->isEmpty()) {
            $targetPath = parse_url($targetUrl, PHP_URL_PATH);
            if (!empty($targetPath) && $targetPath !== '/') {
                $includePaths[] = $this->convertPathToRegex($baseUrl, rtrim($targetPath, '/') . '/*');
            } else {
                $includePaths[] = preg_quote($baseUrl, '#') . '.*';
            }
        } else {
            foreach ($includedScopes as $scope) {
                $includePaths[] = $this->convertPathToRegex($baseUrl, $scope->path);
            }
        }

        // Ensure login URL is in scope if authentication is configured
        if ($authConfig && $authConfig->login_url) {
            $loginUrl = $overrideTargetUrl
                ? (new ZapRunner())->translateTargetUrlForDocker($authConfig->login_url)
                : $authConfig->login_url;
            $loginPath = parse_url($loginUrl, PHP_URL_PATH);
            if (!empty($loginPath) && $loginPath !== '/') {
                $loginRegex = $this->convertPathToRegex($baseUrl, rtrim($loginPath, '/') . '/*');
                if (!in_array($loginRegex, $includePaths, true)) {
                    $includePaths[] = $loginRegex;
                }
            }
        }

        // Excluded Paths Regexes
        $excludePaths = [];
        foreach ($scan->scanScopes->where('type', 'exclude') as $scope) {
            $excludePaths[] = $this->convertPathToRegex($baseUrl, $scope->path);
        }

        // Context Structure
        $context = [
            'name' => $contextName,
            'urls' => array_values(array_unique($urls)),
            'includePaths' => array_values(array_unique($includePaths)),
        ];

        $cleanExcludePaths = array_values(array_unique($excludePaths));
        if (!empty($cleanExcludePaths)) {
            $context['excludePaths'] = $cleanExcludePaths;
        }

        $users = [];

        // Authentication Setup if enabled
        if ($authConfig && $authConfig->mode !== 'none') {
            $loginUrl = $authConfig->login_url
                ? ($overrideTargetUrl ? (new ZapRunner())->translateTargetUrlForDocker($authConfig->login_url) : $authConfig->login_url)
                : null;

            if ($authConfig->mode === 'browser') {
                $context['authentication'] = [
                    'method' => 'browser',
                    'parameters' => array_filter([
                        'loginPageUrl' => $loginUrl ?: $targetUrl,
                        'loginPageWait' => 10,
                        'stepDelay' => 2,
                        'browserId' => 'firefox-headless',
                        'diagnostics' => true,
                        'steps' => [
                            [
                                'description' => 'Wait for Oracle APEX timezone redirect and login form',
                                'type' => 'WAIT',
                                'timeout' => 10000,
                            ],
                            [
                                'description' => 'Fill Oracle APEX username',
                                'type' => 'USERNAME',
                                'cssSelector' => '#P9999_USERNAME',
                                'timeout' => 10000,
                            ],
                            [
                                'description' => 'Fill Oracle APEX password',
                                'type' => 'PASSWORD',
                                'cssSelector' => '#P9999_PASSWORD',
                                'timeout' => 10000,
                            ],
                            [
                                'description' => 'Click Oracle APEX Sign In',
                                'type' => 'CLICK',
                                'cssSelector' => '#B12056144829423636247',
                                'timeout' => 10000,
                            ],
                        ],
                    ], fn($val) => $val !== null),
                    'verification' => [
                        'method' => 'response',
                        'loggedInRegex' => '(?i)My Incidents',
                        'loggedOutRegex' => '(?i)Sign In',
                    ],
                ];
                $context['sessionManagement'] = [
                    'method' => 'autodetect',
                ];
            } elseif ($authConfig->mode === 'form') {
                $usernameParam = $authConfig->username_field ?? 'username';
                $passwordParam = $authConfig->password_field ?? 'password';
                $loginRequestUrl = $loginUrl ?: $targetUrl;
                $loginPageUrl = $loginUrl ?: $targetUrl;

                $authentication = [
                    'method' => 'form',
                    'parameters' => [
                        'loginPageUrl' => $loginPageUrl,
                        'loginRequestUrl' => $loginRequestUrl,
                        'loginRequestBody' => "_token={%_token%}&{$usernameParam}={%username%}&{$passwordParam}={%password%}",
                    ],
                ];

                $verification = ['method' => 'response'];
                if ($authConfig->logged_in_indicator) {
                    $verification['loggedInRegex'] = '(?i)' . preg_quote($authConfig->logged_in_indicator, '#');
                } else {
                    $verification['loggedInRegex'] = '(?i)Sign Out|Dashboard';
                }
                if ($authConfig->logged_out_indicator) {
                    $verification['loggedOutRegex'] = '(?i)' . preg_quote($authConfig->logged_out_indicator, '#');
                } else {
                    $verification['loggedOutRegex'] = '(?i)Sign In';
                }
                $authentication['verification'] = $verification;

                $context['authentication'] = $authentication;
                $context['sessionManagement'] = [
                    'method' => 'cookie',
                ];
            } elseif ($authConfig->mode === 'token') {
                $context['authentication'] = [
                    'method' => 'token',
                    'parameters' => array_filter([
                        'tokenName' => $authConfig->token_name,
                    ]),
                ];
            }

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
        $isAuthEnabled = $authConfig && $authConfig->mode !== 'none';

        // 0. Enable plan-level authentication diagnostics if authentication is configured
        if ($isAuthEnabled) {
            $jobs[] = [
                'type' => 'diagnostics',
                'parameters' => [
                    'enabled' => true,
                ],
            ];
        }

        $crawlSeedUrl = ($authConfig && $authConfig->authenticated_url)
            ? ($overrideTargetUrl ? (new ZapRunner())->translateTargetUrlForDocker($authConfig->authenticated_url) : $authConfig->authenticated_url)
            : $targetUrl;

        // 1. Client Spider (Browser-based JavaScript Crawler)
        if (!$scanConfig || $scanConfig->spider_enabled || $scanConfig->ajax_spider_enabled) {
            $clientSpiderJob = [
                'type' => 'spiderClient',
                'parameters' => [
                    'context' => $contextName,
                    'url' => $crawlSeedUrl,
                    'maxDuration' => 10,
                    'maxCrawlDepth' => 5,
                    'maxChildren' => 100,
                    'numberOfBrowsers' => 1,
                    'browserId' => 'firefox-headless',
                    'scopeCheck' => 'Flexible',
                ],
            ];
            if (!empty($users)) {
                $clientSpiderJob['parameters']['user'] = 'AssessmentUser';
            }
            $jobs[] = $clientSpiderJob;
        }

        // 2. AJAX Spider Job (Interactive DOM crawler for Oracle APEX dynamic controls, tree menus, ARIA tabs, and modal triggers)
        if ($scanConfig && $scanConfig->ajax_spider_enabled) {
            $ajaxSpiderJob = [
                'type' => 'spiderAjax',
                'parameters' => [
                    'context' => $contextName,
                    'url' => $crawlSeedUrl,
                    'maxDuration' => 10,
                    'maxCrawlDepth' => 5,
                    'numberOfBrowsers' => 1,
                    'browserId' => 'firefox-headless',
                    'inScopeOnly' => true,
                ],
            ];
            if (!empty($users)) {
                $ajaxSpiderJob['parameters']['user'] = 'AssessmentUser';
            }
            $jobs[] = $ajaxSpiderJob;
        }

        // 3. Traditional Spider Job
        if (!$scanConfig || $scanConfig->spider_enabled) {
            $spiderJob = [
                'type' => 'spider',
                'parameters' => [
                    'context' => $contextName,
                    'url' => $crawlSeedUrl,
                    'maxDuration' => 10,
                    'maxDepth' => 5,
                    'maxChildren' => 100,
                ],
            ];
            if (!empty($users)) {
                $spiderJob['parameters']['user'] = 'AssessmentUser';
            }
            $jobs[] = $spiderJob;
        }

        // 4. Passive Scan Job
        if (!$scanConfig || $scanConfig->passive_scan_enabled) {
            $jobs[] = [
                'type' => 'passiveScan-wait',
                'parameters' => [
                    'maxDuration' => 5,
                ],
            ];
        }

        // 5. Active Scan Job (scans all discovered URLs within the context)
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

        // 6. Disable diagnostics and export authentication report if authentication was enabled
        if ($isAuthEnabled) {
            $jobs[] = [
                'type' => 'diagnostics',
                'parameters' => [
                    'enabled' => false,
                ],
            ];
            $jobs[] = [
                'type' => 'report',
                'parameters' => [
                    'template' => 'auth-report-json',
                    'reportDir' => '/zap/wrk',
                    'reportFile' => 'auth-report.json',
                    'reportTitle' => "OWASP ZAP Authentication Report: {$scan->name}",
                    'displayReport' => false,
                ],
            ];
        }

        // 7. Traditional Findings JSON Report Generation Job
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
                    'progressToStdout' => true,
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
     * @param string|null $overrideTargetUrl
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
     * Build lightweight ZAP Automation Framework configuration array specifically for authentication verification testing.
     *
     * @param Scan $scan
     * @param string $reportDir
     * @param string $reportFile
     * @param string|null $overrideTargetUrl
     * @return array
     */
    public function buildAuthTestArray(Scan $scan, string $reportDir = '/zap/wrk', string $reportFile = 'auth-report.json', ?string $overrideTargetUrl = null): array
    {
        $scan->loadMissing(['scanScopes', 'scanConfiguration', 'authenticationConfiguration']);

        $contextName = 'Target Context';
        $targetUrl = rtrim($overrideTargetUrl ?: $scan->target_url, '/');
        $baseUrl = $this->extractBaseUrl($targetUrl);

        $authConfig = $scan->authenticationConfiguration;

        $urls = [$targetUrl];
        if ($authConfig && $authConfig->login_url) {
            $loginUrl = $overrideTargetUrl
                ? (new ZapRunner())->translateTargetUrlForDocker($authConfig->login_url)
                : $authConfig->login_url;
            if (!in_array($loginUrl, $urls, true)) {
                $urls[] = $loginUrl;
            }
        }

        $includePaths = [];
        $includedScopes = $scan->scanScopes->where('type', 'include');
        if ($includedScopes->isEmpty()) {
            $targetPath = parse_url($targetUrl, PHP_URL_PATH);
            if (!empty($targetPath) && $targetPath !== '/') {
                $includePaths[] = $this->convertPathToRegex($baseUrl, rtrim($targetPath, '/') . '/*');
            } else {
                $includePaths[] = preg_quote($baseUrl, '#') . '.*';
            }
        } else {
            foreach ($includedScopes as $scope) {
                $includePaths[] = $this->convertPathToRegex($baseUrl, $scope->path);
            }
        }

        if ($authConfig && $authConfig->login_url) {
            $loginUrl = $overrideTargetUrl
                ? (new ZapRunner())->translateTargetUrlForDocker($authConfig->login_url)
                : $authConfig->login_url;
            $loginPath = parse_url($loginUrl, PHP_URL_PATH);
            if (!empty($loginPath) && $loginPath !== '/') {
                $loginRegex = $this->convertPathToRegex($baseUrl, rtrim($loginPath, '/') . '/*');
                if (!in_array($loginRegex, $includePaths, true)) {
                    $includePaths[] = $loginRegex;
                }
            }
        }

        $context = [
            'name' => $contextName,
            'urls' => array_values(array_unique($urls)),
            'includePaths' => array_values(array_unique($includePaths)),
        ];

        $users = [];
        if ($authConfig && $authConfig->mode !== 'none') {
            $loginUrl = $authConfig->login_url
                ? ($overrideTargetUrl ? (new ZapRunner())->translateTargetUrlForDocker($authConfig->login_url) : $authConfig->login_url)
                : null;

            if ($authConfig->mode === 'browser') {
                $context['authentication'] = [
                    'method' => 'browser',
                    'parameters' => array_filter([
                        'loginPageUrl' => $loginUrl ?: $targetUrl,
                        'loginPageWait' => 10,
                        'stepDelay' => 2,
                        'browserId' => 'firefox-headless',
                        'diagnostics' => true,
                        'steps' => [
                            [
                                'description' => 'Wait for Oracle APEX timezone redirect and login form',
                                'type' => 'WAIT',
                                'timeout' => 10000,
                            ],
                            [
                                'description' => 'Fill Oracle APEX username',
                                'type' => 'USERNAME',
                                'cssSelector' => '#P9999_USERNAME',
                                'timeout' => 10000,
                            ],
                            [
                                'description' => 'Fill Oracle APEX password',
                                'type' => 'PASSWORD',
                                'cssSelector' => '#P9999_PASSWORD',
                                'timeout' => 10000,
                            ],
                            [
                                'description' => 'Click Oracle APEX Sign In',
                                'type' => 'CLICK',
                                'cssSelector' => '#B12056144829423636247',
                                'timeout' => 10000,
                            ],
                        ],
                    ], fn($val) => $val !== null),
                    'verification' => [
                        'method' => 'response',
                        'loggedInRegex' => '(?i)My Incidents',
                        'loggedOutRegex' => '(?i)Sign In',
                    ],
                ];
            } elseif ($authConfig->mode === 'form') {
                $usernameParam = $authConfig->username_field ?: 'username';
                $passwordParam = $authConfig->password_field ?: 'password';
                $loginRequestBody = "_token={%_token%}&{$usernameParam}={%username%}&{$passwordParam}={%password%}";

                $context['authentication'] = [
                    'method' => 'form',
                    'parameters' => [
                        'loginPageUrl' => $loginUrl ?: $targetUrl,
                        'loginRequestUrl' => $loginUrl ?: $targetUrl,
                        'loginRequestBody' => $loginRequestBody,
                    ],
                    'verification' => [
                        'method' => 'response',
                        'loggedInRegex' => '(?i)Sign Out|Dashboard',
                        'loggedOutRegex' => '(?i)Sign In',
                    ],
                ];
                $context['sessionManagement'] = [
                    'method' => 'cookie',
                ];
            }

            if ($authConfig->username || $authConfig->password) {
                $credentials = [];
                if ($authConfig->username) {
                    $credentials['username'] = $authConfig->username;
                }
                if ($authConfig->password) {
                    $credentials['password'] = $authConfig->password;
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

        $crawlSeedUrl = ($authConfig && $authConfig->authenticated_url)
            ? ($overrideTargetUrl ? (new ZapRunner())->translateTargetUrlForDocker($authConfig->authenticated_url) : $authConfig->authenticated_url)
            : $targetUrl;

        $jobs = [];
        $jobs[] = [
            'type' => 'diagnostics',
            'parameters' => ['enabled' => true],
        ];

        // Lightweight 1-minute spider ONLY to trigger login and request authenticated seed URL
        $jobs[] = [
            'type' => 'spider',
            'parameters' => [
                'context' => $contextName,
                'url' => $crawlSeedUrl,
                'maxDuration' => 1,
                'maxDepth' => 1,
                'maxChildren' => 5,
                'user' => 'AssessmentUser',
            ],
        ];

        $jobs[] = [
            'type' => 'diagnostics',
            'parameters' => ['enabled' => false],
        ];

        $jobs[] = [
            'type' => 'report',
            'parameters' => [
                'template' => 'auth-report-json',
                'reportDir' => $reportDir,
                'reportFile' => $reportFile,
                'reportTitle' => "OWASP ZAP Authentication Verification Test: {$scan->name}",
                'displayReport' => false,
            ],
        ];

        return [
            'env' => [
                'contexts' => [$context],
                'parameters' => [
                    'failOnError' => false,
                    'failOnWarning' => false,
                    'progressToStdout' => true,
                ],
            ],
            'jobs' => $jobs,
        ];
    }

    /**
     * Build the YAML configuration string specifically for ZAP Authentication Verification Testing.
     *
     * @param Scan $scan
     * @param string $reportDir
     * @param string $reportFile
     * @param string|null $overrideTargetUrl
     * @return string
     */
    public function buildAuthTestYaml(Scan $scan, string $reportDir = '/zap/wrk', string $reportFile = 'auth-report.json', ?string $overrideTargetUrl = null): string
    {
        $data = $this->buildAuthTestArray($scan, $reportDir, $reportFile, $overrideTargetUrl);

        if (class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            return \Symfony\Component\Yaml\Yaml::dump($data, 6, 2);
        }

        return $this->dumpYamlFallback($data);
    }

    /**
     * Extract base URL scheme://host:port from a full URL.
     *
     * @param string $url
     * @return string
     */
    protected function extractBaseUrl(string $url): string
    {
        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            return rtrim($url, '/');
        }

        $scheme = $parsed['scheme'] ?? 'http';
        $host = $parsed['host'];
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

        return "{$scheme}://{$host}{$port}";
    }

    /**
     * Convert path pattern into URL regex relative to base origin URL.
     *
     * @param string $baseUrl Base origin URL (e.g. https://example.com:8443)
     * @param string $path
     * @return string
     */
    protected function convertPathToRegex(string $baseUrl, string $path): string
    {
        $baseUrl = rtrim($baseUrl, '/');

        if ($path === '/*' || $path === '*' || $path === '/.*' || $path === '.*') {
            return preg_quote($baseUrl, '#') . '.*';
        }

        $cleanPath = '/' . ltrim($path, '/');

        if (str_ends_with($cleanPath, '/.*')) {
            $prefix = substr($cleanPath, 0, -3);
            $regexPath = preg_quote($prefix, '#') . '(?:/.*)?';
        } elseif (str_ends_with($cleanPath, '/*')) {
            $prefix = substr($cleanPath, 0, -2);
            $regexPath = preg_quote($prefix, '#') . '(?:/.*)?';
        } else {
            $regexPath = preg_quote($cleanPath, '#');
        }

        return preg_quote($baseUrl, '#') . $regexPath;
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

    /**
     * Sanitize generated YAML string for safe application logging and audit display.
     *
     * @param string $yaml
     * @return string
     */
    public function sanitizeYamlForLogging(string $yaml): string
    {
        return preg_replace('/(username|password|tokenValue):\s*"?[^\r\n"]+"?/i', '$1: "[REDACTED]"', $yaml);
    }
}
