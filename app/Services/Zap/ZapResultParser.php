<?php

namespace App\Services\Zap;

use App\Models\Finding;
use App\Models\Scan;

class ZapResultParser
{
    /**
     * Parse ZAP JSON report file and store findings for the given scan.
     *
     * @param Scan $scan
     * @param string $jsonFilePath
     * @return int Number of findings created
     */
    public function parseAndStore(Scan $scan, string $jsonFilePath): int
    {
        if (!file_exists($jsonFilePath)) {
            return 0;
        }

        $content = file_get_contents($jsonFilePath);
        $data = json_decode($content, true);

        if (!$data || !isset($data['site'])) {
            return 0;
        }

        $sites = $data['site'];
        // Normalize single site object into array
        if (isset($sites['@name'])) {
            $sites = [$sites];
        }

        $count = 0;

        foreach ($sites as $site) {
            $alerts = $site['alerts'] ?? [];
            if (isset($alerts['alert'])) {
                $alerts = [$alerts];
            }

            foreach ($alerts as $alert) {
                $instances = $alert['instances'] ?? [];
                if (isset($instances['uri'])) {
                    $instances = [$instances];
                }

                $risk = $this->mapRisk($alert['riskcode'] ?? null, $alert['riskdesc'] ?? '');
                $confidence = $this->mapConfidence($alert['confidence'] ?? null);
                $severity = strtolower($risk === 'Informational' ? 'info' : $risk);
                $wstgId = $this->extractWstgId($alert);

                if (empty($instances)) {
                    $instances = [[
                        'uri' => $scan->target_url,
                        'method' => 'GET',
                        'param' => null,
                        'attack' => null,
                        'evidence' => null,
                    ]];
                }

                foreach ($instances as $instance) {
                    $findingUrl = !empty($instance['uri']) ? $instance['uri'] : $scan->target_url;
                    $findingUrl = $this->normalizeFindingUrl($findingUrl, $scan->target_url);

                    Finding::create([
                        'scan_id' => $scan->id,
                        'source' => 'owasp_zap',
                        'external_id' => isset($alert['pluginid']) && $alert['pluginid'] !== '' ? (string)$alert['pluginid'] : ($alert['alertRef'] ?? null),
                        'name' => $alert['name'] ?? ($alert['alert'] ?? 'Security Finding'),
                        'risk' => $risk,
                        'confidence' => $confidence,
                        'severity' => $severity,
                        'url' => $findingUrl,
                        'method' => !empty($instance['method']) ? $instance['method'] : 'GET',
                        'parameter' => !empty($instance['param']) ? $instance['param'] : null,
                        'attack' => !empty($instance['attack']) ? $instance['attack'] : null,
                        'evidence' => !empty($instance['evidence']) ? $instance['evidence'] : null,
                        'description' => !empty($alert['desc']) ? strip_tags($alert['desc']) : null,
                        'impact' => !empty($alert['otherinfo']) ? strip_tags($alert['otherinfo']) : null,
                        'solution' => !empty($alert['solution']) ? strip_tags($alert['solution']) : null,
                        'reference' => !empty($alert['reference']) ? strip_tags($alert['reference']) : null,
                        'cwe_id' => isset($alert['cweid']) && $alert['cweid'] !== '' && $alert['cweid'] !== '-1' ? (string)$alert['cweid'] : null,
                        'wasc_id' => isset($alert['wascid']) && $alert['wascid'] !== '' && $alert['wascid'] !== '-1' ? (string)$alert['wascid'] : null,
                        'wstg_id' => $wstgId,
                        'status' => 'open',
                    ]);

                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Map numeric ZAP riskcode / riskdesc string to standardized Risk value.
     */
    protected function mapRisk(?string $riskcode, string $riskdesc): string
    {
        switch ((string)$riskcode) {
            case '3':
                return 'High';
            case '2':
                return 'Medium';
            case '1':
                return 'Low';
            case '0':
                return 'Informational';
        }

        if (str_contains(strtolower($riskdesc), 'high')) return 'High';
        if (str_contains(strtolower($riskdesc), 'medium')) return 'Medium';
        if (str_contains(strtolower($riskdesc), 'low')) return 'Low';

        return 'Informational';
    }

    /**
     * Map numeric ZAP confidence code to standardized Confidence value.
     */
    protected function mapConfidence(?string $code): string
    {
        switch ((string)$code) {
            case '3':
                return 'High';
            case '2':
                return 'Medium';
            case '1':
                return 'Low';
            case '0':
                return 'False Positive';
            default:
                return 'Medium';
        }
    }

    /**
     * Extract OWASP WSTG ID if present in ZAP alert or alert tags.
     */
    protected function extractWstgId(array $alert): ?string
    {
        if (!empty($alert['wstgid'])) {
            return (string)$alert['wstgid'];
        }

        if (!empty($alert['wstg'])) {
            return (string)$alert['wstg'];
        }

        if (isset($alert['tags']) && is_array($alert['tags'])) {
            foreach ($alert['tags'] as $key => $val) {
                if (str_contains(strtoupper((string)$key), 'WSTG')) {
                    return (string)$key;
                }
                if (str_contains(strtoupper((string)$val), 'WSTG')) {
                    return (string)$val;
                }
            }
        }

        return null;
    }

    /**
     * Normalize finding URL to restore original user-facing target host if container networking URL was used.
     */
    protected function normalizeFindingUrl(string $url, string $originalTargetUrl): string
    {
        if (str_contains($url, 'host.docker.internal')) {
            $parsedOriginal = parse_url($originalTargetUrl);
            $origHost = $parsedOriginal['host'] ?? '127.0.0.1';
            $origPort = isset($parsedOriginal['port']) ? ':' . $parsedOriginal['port'] : '';

            return str_replace('host.docker.internal' . $origPort, $origHost . $origPort, $url);
        }

        return $url;
    }
}
