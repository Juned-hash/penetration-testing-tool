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

        // Check if configured binary runs directly
        if ($this->testExecutable($configured)) {
            return $configured;
        }

        // On Windows, if default 'docker' command wasn't found in PATH, check common Docker Desktop installation paths
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
            // First check if image exists locally
            $process = new Process([$dockerBinary, 'image', 'inspect', $image]);
            $process->run();

            if ($process->isSuccessful()) {
                return true;
            }

            // If not available locally, attempt to pull the image automatically
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
     * Stop and safely remove a specific named Docker container.
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
     * Build the safe Docker process command argument array.
     *
     * @param string $hostWorkDir Absolute path to local working directory.
     * @param string $yamlFilename Name of YAML plan file in working dir (e.g. assessment.yaml).
     * @param string|null $image Optional custom image name.
     * @param bool $isLocalTarget
     * @param string|null $containerName Optional unique container name for lifecycle management.
     * @return array
     */
    public function buildDockerCommand(
        string $hostWorkDir,
        string $yamlFilename = 'assessment.yaml',
        ?string $image = null,
        bool $isLocalTarget = false,
        ?string $containerName = null
    ): array {
        $dockerBinary = $this->getDockerBinaryPath();
        $image = $image ?: config('zap.docker_image', 'ghcr.io/zaproxy/zaproxy:stable');
        $dockerUser = config('zap.docker_user');

        $normalizedWorkDir = $this->toHostPath($hostWorkDir);

        $command = [
            $dockerBinary,
            'run',
            '--rm',
        ];

        if (!empty($containerName)) {
            $command[] = '--name';
            $command[] = $containerName;
        }

        if (!empty($dockerUser)) {
            $command[] = '--user';
            $command[] = $dockerUser;
        }

        if ($isLocalTarget) {
            $command[] = '--add-host=host.docker.internal:host-gateway';
        }

        $command[] = '-v';
        $command[] = "{$normalizedWorkDir}:/zap/wrk:rw";
        $command[] = $image;
        $command[] = 'zap.sh';
        $command[] = '-cmd';
        $command[] = '-autorun';
        $command[] = "/zap/wrk/{$yamlFilename}";

        return $command;
    }

    /**
     * Execute the ZAP Automation Framework inside a Docker container.
     *
     * @param string $yamlFilePath Absolute host path to generated YAML configuration file.
     * @param string $hostWorkDir Absolute host path to working directory mounted into /zap/wrk.
     * @param bool $isLocalTarget Whether target requires host-gateway loopback networking.
     * @param string|null $containerName Unique scan-specific container name for lifecycle control.
     * @return array Result array with success boolean, exit code, output, and error streams.
     */
    public function runAutomationFramework(
        string $yamlFilePath,
        string $hostWorkDir,
        bool $isLocalTarget = false,
        ?string $containerName = null
    ): array {
        $dockerBinary = $this->getDockerBinaryPath();
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

        $yamlFilename = basename($yamlFilePath);
        $command = $this->buildDockerCommand($hostWorkDir, $yamlFilename, $dockerImage, $isLocalTarget, $containerName);

        try {
            $process = new Process($command, $hostWorkDir);
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
            $cleanupSuccess = false;
            if (!empty($containerName)) {
                $cleanupSuccess = $this->stopContainer($containerName);
            }

            return [
                'success' => false,
                'exitCode' => 124,
                'output' => $e->getProcess()->getOutput(),
                'error' => "ZAP Docker process exceeded the configured timeout of {$timeout} seconds. Container cleanup: " . ($cleanupSuccess ? 'successful' : 'failed or skipped'),
                'command' => $command,
                'timedOut' => true,
                'cleanupSuccess' => $cleanupSuccess,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'exitCode' => 1,
                'output' => 'Execution error occurred while launching ZAP Docker container.',
                'error' => get_class($e) . ': ' . $e->getMessage(),
                'command' => $command,
                'timedOut' => false,
            ];
        }
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
