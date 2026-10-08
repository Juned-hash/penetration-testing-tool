<?php

namespace App\Services\Zap;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class ZapRunner
{
    /**
     * Get the resolved path to the Docker binary executable.
     *
     * @return string
     */
    public function getDockerBinaryPath(): string
    {
        $configured = config('zap.docker_binary', 'docker');

        if ($this->testExecutable($configured)) {
            return $configured;
        }

        if (PHP_OS_FAMILY === 'Windows' && $configured === 'docker') {
            $userProfile = getenv('USERPROFILE');
            $localAppData = getenv('LOCALAPPDATA');

            $candidatePaths = array_filter([
                $localAppData ? $localAppData . '\Programs\DockerDesktop\resources\bin\docker.exe' : null,
                $userProfile ? $userProfile . '\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe' : null,
                'C:\Program Files\Docker\Docker\resources\bin\docker.exe',
            ]);

            foreach ($candidatePaths as $path) {
                if (File::exists($path) && $this->testExecutable($path)) {
                    return $path;
                }
            }
        }

        return $configured;
    }

    /**
     * Check whether Docker CLI is installed and running on the host system.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        $dockerBinary = $this->getDockerBinaryPath();
        return $this->testExecutable($dockerBinary);
    }

    /**
     * Check if the configured ZAP Docker image is present locally or pullable.
     *
     * @param string|null $image
     * @return bool
     */
    public function isDockerImageAvailable(?string $image = null): bool
    {
        $image = $image ?: config('zap.docker_image', 'ghcr.io/zaproxy/zaproxy:stable');
        $dockerBinary = $this->getDockerBinaryPath();

        try {
            $process = new Process([$dockerBinary, 'image', 'inspect', $image]);
            $process->run();

            if ($process->isSuccessful()) {
                return true;
            }

            $pullTimeout = (int) config('zap.pull_timeout', 600);
            $pullProcess = new Process([$dockerBinary, 'pull', $image]);
            $pullProcess->setTimeout($pullTimeout);
            $pullProcess->run();

            return $pullProcess->isSuccessful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Translate localhost targets (127.0.0.1 / localhost) to host.docker.internal for container networking.
     *
     * @param string $url
     * @return string
     */
    public function translateTargetUrlForDocker(string $url): string
    {
        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host'])) {
            return $url;
        }

        $host = strtolower($parsed['host']);
        if ($host === '127.0.0.1' || $host === 'localhost') {
            $scheme = $parsed['scheme'] ?? 'http';
            $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            $path = $parsed['path'] ?? '';
            $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
            $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

            return "{$scheme}://host.docker.internal{$port}{$path}{$query}{$fragment}";
        }

        return $url;
    }

    /**
     * Translate container path to Docker host path if HOST_PROJECT_PATH is configured.
     *
     * @param string $path
     * @return string
     */
    public function toHostPath(string $path): string
    {
        $hostProjectPath = config('zap.host_project_path');
        if (!empty($hostProjectPath)) {
            $basePath = base_path();
            if (str_starts_with($path, $basePath)) {
                $relativePath = ltrim(substr($path, strlen($basePath)), '/\\');
                $path = rtrim($hostProjectPath, '/\\') . '/' . $relativePath;
            }
        }

        return str_replace('\\', '/', $path);
    }

    /**
     * Get unique, deterministic volume name for a scan ID.
     *
     * @param int|string $scanId
     * @return string
     */
    public function getVolumeName(int|string $scanId): string
    {
        return "zap_scan_{$scanId}";
    }

    /**
     * Get unique, deterministic container name for a scan ID.
     *
     * @param int|string $scanId
     * @return string
     */
    public function getContainerName(int|string $scanId): string
    {
        return "zap-scan-{$scanId}";
    }

    /**
     * Build docker volume create command array.
     *
     * @param string $volumeName
     * @return array
     */
    public function buildVolumeCreateCommand(string $volumeName): array
    {
        return [$this->getDockerBinaryPath(), 'volume', 'create', $volumeName];
    }

    /**
     * Create unique Docker named volume for assessment workspace.
     *
     * @param string $volumeName
     * @return bool
     */
    public function createWorkspaceVolume(string $volumeName): bool
    {
        $process = new Process($this->buildVolumeCreateCommand($volumeName));
        $process->run();
        return $process->isSuccessful();
    }

    /**
     * Build volume initialization command array (chown 1000:1000).
     *
     * @param string $volumeName
     * @param string|null $image
     * @return array
     */
    public function buildVolumeInitCommand(string $volumeName, ?string $image = null): array
    {
        $image = $image ?: config('zap.docker_image', 'ghcr.io/zaproxy/zaproxy:stable');
        return [
            $this->getDockerBinaryPath(),
            'run',
            '--rm',
            '--user', 'root',
            '--entrypoint', 'sh',
            '-v', "{$volumeName}:/zap/wrk",
            $image,
            '-c',
            'chown -R 1000:1000 /zap/wrk',
        ];
    }

    /**
     * Initialize ownership of Docker volume to non-root user zap (UID/GID 1000:1000).
     *
     * @param string $volumeName
     * @param string|null $image
     * @return bool
     */
    public function initializeWorkspaceVolume(string $volumeName, ?string $image = null): bool
    {
        $process = new Process($this->buildVolumeInitCommand($volumeName, $image));
        $process->setTimeout(60);
        $process->run();
        return $process->isSuccessful();
    }

    /**
     * Build container creation command array mounting the named volume.
     *
     * @param string $volumeName
     * @param string $containerName
     * @param string $yamlFilename
     * @param string|null $image
     * @param bool $isLocalTarget
     * @return array
     */
    public function buildCreateContainerCommand(
        string $volumeName,
        string $containerName,
        string $yamlFilename = 'assessment.yaml',
        ?string $image = null,
        bool $isLocalTarget = false
    ): array {
        $dockerBinary = $this->getDockerBinaryPath();
        $image = $image ?: config('zap.docker_image', 'ghcr.io/zaproxy/zaproxy:stable');
        $dockerUser = config('zap.docker_user', 'zap');

        $command = [
            $dockerBinary,
            'create',
            '--name', $containerName,
            '--user', $dockerUser ?: 'zap',
        ];

        if ($isLocalTarget) {
            $command[] = '--add-host=host.docker.internal:host-gateway';
        }

        $command[] = '-v';
        $command[] = "{$volumeName}:/zap/wrk";
        $command[] = $image;
        $command[] = 'zap.sh';
        $command[] = '-cmd';
        $command[] = '-autorun';
        $command[] = "/zap/wrk/{$yamlFilename}";

        return $command;
    }

    /**
     * Create temporary ZAP container mounting named volume (without --rm).
     *
     * @param string $volumeName
     * @param string $containerName
     * @param string $yamlFilename
     * @param string|null $image
     * @param bool $isLocalTarget
     * @return bool
     */
    public function createZapContainer(
        string $volumeName,
        string $containerName,
        string $yamlFilename = 'assessment.yaml',
        ?string $image = null,
        bool $isLocalTarget = false
    ): bool {
        $this->removeZapContainer($containerName);
        $command = $this->buildCreateContainerCommand($volumeName, $containerName, $yamlFilename, $image, $isLocalTarget);
        $process = new Process($command);
        $process->run();
        return $process->isSuccessful();
    }

    /**
     * Build command array for copying assessment.yaml into container.
     *
     * @param string $containerName
     * @param string $hostYamlPath
     * @param string $yamlFilename
     * @return array
     */
    public function buildCopyYamlCommand(string $containerName, string $hostYamlPath, string $yamlFilename = 'assessment.yaml'): array
    {
        return [
            $this->getDockerBinaryPath(),
            'cp',
            $hostYamlPath,
            "{$containerName}:/zap/wrk/{$yamlFilename}",
        ];
    }

    /**
     * Copy assessment.yaml file from host into container volume.
     *
     * @param string $containerName
     * @param string $hostYamlPath
     * @param string $yamlFilename
     * @return bool
     */
    public function copyAssessmentYamlToContainer(string $containerName, string $hostYamlPath, string $yamlFilename = 'assessment.yaml'): bool
    {
        $process = new Process($this->buildCopyYamlCommand($containerName, $hostYamlPath, $yamlFilename));
        $process->run();
        return $process->isSuccessful();
    }

    /**
     * Build command array to start container attached.
     *
     * @param string $containerName
     * @return array
     */
    public function buildStartContainerCommand(string $containerName): array
    {
        return [$this->getDockerBinaryPath(), 'start', '-a', $containerName];
    }

    /**
     * Start container attached and capture output with timeout handling.
     *
     * @param string $containerName
     * @param int $timeout
     * @return array
     */
    public function startZapContainer(string $containerName, int $timeout): array
    {
        $command = $this->buildStartContainerCommand($containerName);

        try {
            $process = new Process($command);
            $process->setTimeout($timeout);
            $process->run();

            return [
                'success' => $process->isSuccessful(),
                'exitCode' => $process->getExitCode(),
                'output' => $process->getOutput(),
                'error' => $process->getErrorOutput(),
                'command' => $command,
                'timedOut' => false,
            ];
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
            $this->stopRunningContainer($containerName);
            return [
                'success' => false,
                'exitCode' => 124,
                'output' => $e->getProcess()->getOutput(),
                'error' => "ZAP Docker process exceeded configured timeout of {$timeout} seconds.",
                'command' => $command,
                'timedOut' => true,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'exitCode' => 1,
                'output' => '',
                'error' => get_class($e) . ': ' . $e->getMessage(),
                'command' => $command,
                'timedOut' => false,
            ];
        }
    }

    /**
     * Build command array for selectively exporting persistent assessment artifacts (report.json).
     *
     * @param string $containerName
     * @param string $hostWorkDir
     * @return array
     */
    public function buildExportArtifactsCommand(string $containerName, string $hostWorkDir): array
    {
        $targetPath = rtrim(str_replace('\\', '/', $hostWorkDir), '/') . '/report.json';
        return [
            $this->getDockerBinaryPath(),
            'cp',
            "{$containerName}:/zap/wrk/report.json",
            $targetPath,
        ];
    }

    /**
     * Export persistent ZAP assessment artifacts (report.json) from container volume back to host work directory.
     * Does NOT copy temporary multi-gigabyte auth-report.json.
     *
     * @param string $containerName
     * @param string $hostWorkDir
     * @return bool
     */
    public function exportZapArtifacts(string $containerName, string $hostWorkDir): bool
    {
        File::ensureDirectoryExists($hostWorkDir);
        $process = new Process($this->buildExportArtifactsCommand($containerName, $hostWorkDir));
        $process->setTimeout(300);
        $process->run();

        // If report.json was not generated (e.g. lightweight auth verification test scans omit report.json), treat as non-fatal success
        if (!$process->isSuccessful() && str_contains($process->getErrorOutput(), 'Could not find the file')) {
            return true;
        }

        return $process->isSuccessful();
    }

    /**
     * Extract authentication report summary directly from workspace volume memory-safely without copying multi-gigabyte files.
     * Streams through /zap/wrk/auth-report.json in 128 KB chunks using Python inside a temporary container.
     *
     * @param string $volumeName
     * @param int|string|null $scanId
     * @return array|null
     */
    public function extractAuthReportSummaryFromVolume(string $volumeName, int|string|null $scanId = null): ?array
    {
        if (empty($volumeName)) {
            return null;
        }

        $dockerBinary = $this->getDockerBinaryPath();
        $dockerImage = config('zap.docker_image', 'ghcr.io/zaproxy/zaproxy:stable');

        $pythonScript = <<<'PY'
import json, sys

def extract_key(f, key_name):
    f.seek(0)
    chunk_size = 131072
    overlap_size = 16384
    buf = b""
    file_pos = 0
    key_offset = -1
    value = None
    
    while True:
        chunk = f.read(chunk_size)
        if not chunk:
            break
        buf_start = file_pos - (len(buf) - len(chunk) if len(buf) > len(chunk) else 0)
        buf += chunk
        file_pos += len(chunk)
        
        search_from = 0
        while search_from < len(buf):
            idx = buf.find(key_name, search_from)
            if idx == -1:
                break
            
            cand_offset = buf_start + idx
            col_idx = buf.find(b':', idx + len(key_name))
            while col_idx == -1:
                more = f.read(chunk_size)
                if not more:
                    break
                buf += more
                file_pos += len(more)
                col_idx = buf.find(b':', idx + len(key_name))
            
            if col_idx != -1:
                i = col_idx + 1
                while i < len(buf) and buf[i:i+1].isspace():
                    i += 1
                
                while i >= len(buf):
                    more = f.read(chunk_size)
                    if not more:
                        break
                    buf += more
                    file_pos += len(more)
                
                if i < len(buf):
                    open_char = buf[i:i+1]
                    if open_char in (b'[', b'{'):
                        close_char = b']' if open_char == b'[' else b'}'
                        depth = 0
                        in_string = False
                        escape = False
                        start_pos = i
                        found_end = False
                        curr = i
                        
                        while not found_end:
                            while curr < len(buf):
                                c = buf[curr:curr+1]
                                if in_string:
                                    if escape:
                                        escape = False
                                    elif c == b'\\':
                                        escape = True
                                    elif c == b'"':
                                        in_string = False
                                else:
                                    if c == b'"':
                                        in_string = True
                                    elif c == open_char:
                                        depth += 1
                                    elif c == close_char:
                                        depth -= 1
                                        if depth == 0:
                                            found_end = True
                                            curr += 1
                                            break
                                curr += 1
                            
                            if not found_end:
                                more = f.read(chunk_size)
                                if not more:
                                    break
                                buf += more
                                file_pos += len(more)
                        
                        if found_end:
                            val_bytes = buf[start_pos:curr]
                            try:
                                decoded_val = json.loads(val_bytes.decode('utf-8', errors='ignore'))
                                if decoded_val is not None and isinstance(decoded_val, (list, dict)):
                                    value = decoded_val
                                    key_offset = cand_offset
                                    return key_offset, value
                            except Exception:
                                pass
            
            search_from = idx + len(key_name)

        if len(buf) > overlap_size:
            buf = buf[-overlap_size:]
            
    return key_offset, value

def run():
    filepath = '/zap/wrk/auth-report.json'
    res = {'offsets': {}, 'summary': {}}
    try:
        with open(filepath, 'rb') as f:
            items_off, items_val = extract_key(f, b'"summaryItems"')
            stats_off, stats_val = extract_key(f, b'"statistics"')
            
            if items_off != -1:
                res['offsets']['summaryItems_offset'] = items_off
            if stats_off != -1:
                res['offsets']['statistics_offset'] = stats_off
            if items_val is not None:
                res['summary']['summaryItems'] = items_val
            if stats_val is not None:
                res['summary']['statistics'] = stats_val
    except Exception as e:
        res['error'] = str(e)
    
    print(json.dumps(res))

run()
PY;

        try {
            $process = new Process([
                $dockerBinary,
                'run',
                '--rm',
                '-v',
                "{$volumeName}:/zap/wrk",
                $dockerImage,
                'python3',
                '-c',
                $pythonScript,
            ]);
            $process->setTimeout(60);
            $process->run();

            if (!$process->isSuccessful()) {
                return null;
            }

            $rawOutput = trim($process->getOutput());
            if (empty($rawOutput)) {
                return null;
            }

            $data = json_decode($rawOutput, true);
            if (!is_array($data)) {
                return null;
            }

            $offsets = $data['offsets'] ?? [];
            $itemsOffset = $offsets['summaryItems_offset'] ?? 'NOT FOUND';
            $statsOffset = $offsets['statistics_offset'] ?? 'NOT FOUND';

            $numericScanId = is_numeric($scanId) ? (int)$scanId : (preg_match('/(\d+)/', (string)$scanId, $m) ? (int)$m[1] : null);

            if ($numericScanId) {
                \App\Models\ScanLog::create([
                    'scan_id' => $numericScanId,
                    'level' => 'info',
                    'phase' => 'zap_auth_report_offsets',
                    'message' => "ZAP_AUTH_REPORT_OFFSETS summaryItems byte offset: {$itemsOffset} | statistics byte offset: {$statsOffset}",
                ]);
            }

            return $data['summary'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Legacy method alias maintained for backward compatibility.
     */
    public function extractAuthReportSummaryFromContainer(string $containerName, int|string|null $scanId = null): ?array
    {
        $scanIdPart = $scanId ?: 'test';
        if (preg_match('/zap-(?:auth-test|scan)-(\w+)/', $containerName, $m)) {
            $scanIdPart = $m[1];
        }
        $volumeName = $this->getVolumeName($scanIdPart);
        return $this->extractAuthReportSummaryFromVolume($volumeName, $scanId);
    }

    /**
     * Extract authentication report summary from a host file path memory-safely.
     * Streams through host file in 128 KB chunks using fread with bracket-balancing parser.
     *
     * @param string $hostPath
     * @return array|null
     */
    public function extractAuthReportSummaryFromFile(string $hostPath): ?array
    {
        if (!File::exists($hostPath) || filesize($hostPath) === 0) {
            return null;
        }

        $handle = @fopen($hostPath, 'rb');
        if (!$handle) {
            return null;
        }

        $extractKey = function($handle, string $keyName) {
            fseek($handle, 0);
            $chunkSize = 131072;
            $overlapSize = 16384;
            $buffer = '';
            $filePos = 0;
            $keyOffset = -1;
            $value = null;

            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false || strlen($chunk) === 0) {
                    break;
                }
                $bufStartFilePos = $filePos - (strlen($buffer) > strlen($chunk) ? strlen($buffer) - strlen($chunk) : 0);
                $buffer .= $chunk;
                $filePos += strlen($chunk);

                $searchFrom = 0;
                while ($searchFrom < strlen($buffer)) {
                    $idx = strpos($buffer, $keyName, $searchFrom);
                    if ($idx === false) {
                        break;
                    }

                    $candOffset = $bufStartFilePos + $idx;
                    $colIdx = strpos($buffer, ':', $idx + strlen($keyName));
                    while ($colIdx === false && !feof($handle)) {
                        $more = fread($handle, $chunkSize);
                        if ($more === false || strlen($more) === 0) break;
                        $buffer .= $more;
                        $filePos += strlen($more);
                        $colIdx = strpos($buffer, ':', $idx + strlen($keyName));
                    }

                    if ($colIdx !== false) {
                        $i = $colIdx + 1;
                        while ($i < strlen($buffer) && ctype_space($buffer[$i])) {
                            $i++;
                        }
                        while ($i >= strlen($buffer) && !feof($handle)) {
                            $more = fread($handle, $chunkSize);
                            if ($more === false || strlen($more) === 0) break;
                            $buffer .= $more;
                            $filePos += strlen($more);
                        }

                        if ($i < strlen($buffer)) {
                            $openChar = $buffer[$i];
                            if ($openChar === '[' || $openChar === '{') {
                                $closeChar = $openChar === '[' ? ']' : '}';
                                $depth = 0;
                                $inString = false;
                                $escape = false;
                                $startPos = $i;
                                $foundEnd = false;
                                $curr = $i;

                                while (!$foundEnd) {
                                    while ($curr < strlen($buffer)) {
                                        $c = $buffer[$curr];
                                        if ($inString) {
                                            if ($escape) {
                                                $escape = false;
                                            } elseif ($c === '\\') {
                                                $escape = true;
                                            } elseif ($c === '"') {
                                                $inString = false;
                                            }
                                        } else {
                                            if ($c === '"') {
                                                $inString = true;
                                            } elseif ($c === $openChar) {
                                                $depth++;
                                            } elseif ($c === $closeChar) {
                                                $depth--;
                                                if ($depth === 0) {
                                                    $foundEnd = true;
                                                    $curr++;
                                                    break;
                                                }
                                            }
                                        }
                                        $curr++;
                                    }

                                    if (!$foundEnd) {
                                        if (feof($handle)) break;
                                        $more = fread($handle, $chunkSize);
                                        if ($more === false || strlen($more) === 0) break;
                                        $buffer .= $more;
                                        $filePos += strlen($more);
                                    }
                                }

                                if ($foundEnd) {
                                    $valStr = substr($buffer, $startPos, $curr - $startPos);
                                    $decoded = json_decode($valStr, true);
                                    if (is_array($decoded)) {
                                        return [$candOffset, $decoded];
                                    }
                                }
                            }
                        }
                    }

                    $searchFrom = $idx + strlen($keyName);
                }

                if (strlen($buffer) > $overlapSize) {
                    $buffer = substr($buffer, -$overlapSize);
                }
            }

            return [$keyOffset, $value];
        };

        list($itemsOffset, $summaryItems) = $extractKey($handle, '"summaryItems"');
        list($statsOffset, $statistics) = $extractKey($handle, '"statistics"');
        fclose($handle);

        $summary = [];
        if ($summaryItems !== null) {
            $summary['summaryItems'] = $summaryItems;
        }
        if ($statistics !== null) {
            $summary['statistics'] = $statistics;
        }

        return !empty($summary) ? $summary : null;
    }

    /**
     * Extract summaryItems and statistics arrays from JSON buffer memory-safely.
     *
     * @param string $buffer
     * @return array|null
     */
    public function extractSummaryFromBuffer(string $buffer): ?array
    {
        if (empty($buffer)) {
            return null;
        }

        $summary = [];

        if (preg_match('/"summaryItems"\s*:\s*(\[[^\]]*\])/s', $buffer, $matches)) {
            $items = json_decode($matches[1], true);
            if (is_array($items)) {
                $summary['summaryItems'] = $items;
            }
        }

        if (preg_match('/"statistics"\s*:\s*(\[[^\]]*\])/s', $buffer, $matches)) {
            $stats = json_decode($matches[1], true);
            if (is_array($stats)) {
                $summary['statistics'] = $stats;
            }
        }

        return !empty($summary) ? $summary : null;
    }


    /**
     * Stop container execution without removing it (so artifacts can still be exported).
     *
     * @param string $containerName
     * @return bool
     */
    public function stopRunningContainer(string $containerName): bool
    {
        if (empty($containerName)) {
            return false;
        }

        $dockerBinary = $this->getDockerBinaryPath();
        try {
            $stopProcess = new Process([$dockerBinary, 'stop', '-t', '5', $containerName]);
            $stopProcess->run();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Stop and safely remove container.
     *
     * @param string $containerName
     * @return bool
     */
    public function stopContainer(string $containerName): bool
    {
        if (empty($containerName)) {
            return false;
        }

        $dockerBinary = $this->getDockerBinaryPath();
        try {
            $stopProcess = new Process([$dockerBinary, 'stop', '-t', '10', $containerName]);
            $stopProcess->run();

            $rmProcess = new Process([$dockerBinary, 'rm', '-f', $containerName]);
            $rmProcess->run();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Remove temporary ZAP container.
     *
     * @param string $containerName
     * @return bool
     */
    public function removeZapContainer(string $containerName): bool
    {
        if (empty($containerName)) {
            return false;
        }
        $dockerBinary = $this->getDockerBinaryPath();
        try {
            $process = new Process([$dockerBinary, 'rm', '-f', $containerName]);
            $process->run();
            return $process->isSuccessful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Remove Docker named volume.
     *
     * @param string $volumeName
     * @return bool
     */
    public function removeWorkspaceVolume(string $volumeName): bool
    {
        if (empty($volumeName)) {
            return false;
        }
        $dockerBinary = $this->getDockerBinaryPath();
        try {
            $process = new Process([$dockerBinary, 'volume', 'rm', '-f', $volumeName]);
            $process->run();
            return $process->isSuccessful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Legacy buildDockerCommand helper kept for backward compatibility with existing tests.
     *
     * @param string $hostWorkDir
     * @param string $yamlFilename
     * @param string|null $image
     * @param bool $isLocalTarget
     * @param string|null $containerName
     * @return array
     */
    public function buildDockerCommand(
        string $hostWorkDir,
        string $yamlFilename = 'assessment.yaml',
        ?string $image = null,
        bool $isLocalTarget = false,
        ?string $containerName = null
    ): array {
        $scanId = 'test';
        if (preg_match('/scan_(\w+)/', $hostWorkDir, $m)) {
            $scanId = $m[1];
        }
        $volumeName = $this->getVolumeName($scanId);
        $containerName = $containerName ?: $this->getContainerName($scanId);

        return $this->buildCreateContainerCommand($volumeName, $containerName, $yamlFilename, $image, $isLocalTarget);
    }

    /**
     * Execute the ZAP Automation Framework inside a Docker container using a named volume workspace.
     *
     * @param string $yamlFilePath Absolute host path to generated YAML configuration file.
     * @param string $hostWorkDir Absolute host path to working directory.
     * @param bool $isLocalTarget Whether target requires host-gateway loopback networking.
     * @param string|null $containerName Unique scan-specific container name.
     * @param int|string|null $scanId Unique scan identifier.
     * @param callable|null $logCallback Optional callback for audit stage logging.
     * @return array Result array with success boolean, exit code, output, error streams, and stage details.
     */
    public function runAutomationFramework(
        string $yamlFilePath,
        string $hostWorkDir,
        bool $isLocalTarget = false,
        ?string $containerName = null,
        int|string|null $scanId = null,
        ?callable $logCallback = null
    ): array {
        $dockerImage = config('zap.docker_image', 'ghcr.io/zaproxy/zaproxy:stable');
        $timeout = (int) config('zap.timeout', 3600);

        if (!$this->isAvailable()) {
            return [
                'success' => false,
                'exitCode' => 127,
                'output' => 'Docker is not installed or the Docker Desktop service is not running on the host system.',
                'error' => 'Docker execution binary unavailable or daemon stopped.',
                'timedOut' => false,
            ];
        }

        if (!$this->isDockerImageAvailable($dockerImage)) {
            return [
                'success' => false,
                'exitCode' => 1,
                'output' => "Configured ZAP Docker image [{$dockerImage}] was not found locally.",
                'error' => 'ZAP Docker image missing.',
                'timedOut' => false,
            ];
        }

        if (!File::exists($yamlFilePath)) {
            return [
                'success' => false,
                'exitCode' => 1,
                'output' => 'ZAP Automation Framework YAML configuration file does not exist.',
                'error' => 'Configuration file missing.',
                'timedOut' => false,
            ];
        }

        if (!$scanId) {
            if (preg_match('/scan_(\w+)/', $hostWorkDir, $m)) {
                $scanId = $m[1];
            } else {
                $scanId = uniqid();
            }
        }

        $volumeName = $this->getVolumeName($scanId);
        $containerName = $containerName ?: $this->getContainerName($scanId);
        $yamlFilename = basename($yamlFilePath);

        // 1. Stage 1: Volume creation
        if (!$this->createWorkspaceVolume($volumeName)) {
            return [
                'success' => false,
                'exitCode' => 1,
                'output' => "Failed to create Docker volume [{$volumeName}] for scan [{$scanId}].",
                'error' => "Docker volume creation failure for volume {$volumeName}.",
                'timedOut' => false,
                'stage' => 'volume_creation',
            ];
        }
        if ($logCallback) $logCallback('zap_volume_created', "ZAP_VOLUME_CREATED Created Docker volume: {$volumeName}");

        // 2. Stage 2: Volume initialization (chown 1000:1000)
        if (!$this->initializeWorkspaceVolume($volumeName, $dockerImage)) {
            $this->removeWorkspaceVolume($volumeName);
            return [
                'success' => false,
                'exitCode' => 1,
                'output' => "Failed to initialize ownership (1000:1000) on Docker volume [{$volumeName}] for scan [{$scanId}].",
                'error' => "Docker volume initialization failure for volume {$volumeName}.",
                'timedOut' => false,
                'stage' => 'volume_initialization',
            ];
        }
        if ($logCallback) $logCallback('zap_volume_initialized', "ZAP_VOLUME_INITIALIZED Initialized volume ownership to 1000:1000 for volume: {$volumeName}");

        // 3. Stage 3: Container creation
        if (!$this->createZapContainer($volumeName, $containerName, $yamlFilename, $dockerImage, $isLocalTarget)) {
            $this->removeWorkspaceVolume($volumeName);
            return [
                'success' => false,
                'exitCode' => 1,
                'output' => "Failed to create ZAP container [{$containerName}] for scan [{$scanId}].",
                'error' => "Docker container creation failure for container {$containerName}.",
                'timedOut' => false,
                'stage' => 'container_creation',
            ];
        }
        if ($logCallback) $logCallback('zap_container_created', "ZAP_CONTAINER_CREATED Created ZAP container: {$containerName}");

        // 4. Stage 4: Copy assessment.yaml to Container Volume
        if (!$this->copyAssessmentYamlToContainer($containerName, $yamlFilePath, $yamlFilename)) {
            $this->removeZapContainer($containerName);
            $this->removeWorkspaceVolume($volumeName);
            return [
                'success' => false,
                'exitCode' => 1,
                'output' => "Failed to copy YAML configuration [{$yamlFilename}] to container [{$containerName}] for scan [{$scanId}].",
                'error' => "Assessment YAML copy failure for container {$containerName}.",
                'timedOut' => false,
                'stage' => 'yaml_copy',
            ];
        }
        if ($logCallback) $logCallback('zap_assessment_yaml_copied', "ZAP_ASSESSMENT_YAML_COPIED Copied {$yamlFilename} to container volume: {$volumeName}");

        // 5. Stage 5: Start container
        if ($logCallback) $logCallback('zap_container_started', "ZAP_CONTAINER_STARTED Started ZAP container: {$containerName}");
        $result = $this->startZapContainer($containerName, $timeout);

        // 5b. Extract authentication report summary memory-safely directly from workspace volume before volume destruction
        $authReportSummary = $this->extractAuthReportSummaryFromVolume($volumeName, $scanId);
        if ($authReportSummary !== null) {
            $result['authReportSummary'] = $authReportSummary;
        }

        // 6. Stage 6: Export persistent artifacts (report.json) from volume back to host work directory
        $artifactsExported = $this->exportZapArtifacts($containerName, $hostWorkDir);
        if ($logCallback) {
            $logCallback('zap_artifacts_exported', "ZAP_ARTIFACTS_EXPORTED Exported ZAP artifacts from volume {$volumeName} to host {$hostWorkDir} (" . ($artifactsExported ? 'success' : 'partial/warning') . ")");
        }

        // 7. Stage 7: Cleanup temporary container & named volume
        $this->removeZapContainer($containerName);
        $this->removeWorkspaceVolume($volumeName);
        if ($logCallback) $logCallback('zap_volume_cleanup', "ZAP_VOLUME_CLEANUP Cleaned up ZAP container {$containerName} and volume {$volumeName}");

        return $result;
    }

    /**
     * Internal helper to test process executable responsiveness.
     *
     * @param string $binary
     * @return bool
     */
    protected function testExecutable(string $binary): bool
    {
        try {
            $process = new Process([$binary, '--version']);
            $process->setTimeout(10);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
