<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

/** @var \App\Services\Zap\ZapRunner $runner */
$runner = app(\App\Services\Zap\ZapRunner::class);
$dockerBinary = $runner->getDockerBinaryPath();
$dockerImage = config('zap.docker_image', 'ghcr.io/zaproxy/zaproxy:stable');

$scanId = 999;
$dir = str_replace('\\', '/', storage_path("app/zap/scan_{$scanId}"));
File::ensureDirectoryExists($dir);

echo "Dynamically Resolved Host Path: {$dir}\n";

$command = [
    $dockerBinary,
    'run',
    '--rm',
    '-v',
    "{$dir}:/zap/wrk:rw",
    $dockerImage,
    'touch',
    '/zap/wrk/mount_test.tmp'
];

echo "Process Command Array:\n";
print_r($command);

$process = new \Symfony\Component\Process\Process($command);
$process->run();

echo "Exit Code: " . $process->getExitCode() . "\n";
if ($process->getErrorOutput()) {
    echo "Error Output: " . trim($process->getErrorOutput()) . "\n";
}

$testFile = $dir . '/mount_test.tmp';
$fileExists = file_exists($testFile);

echo "Bind mount file created inside container visible on host? " . ($fileExists ? "YES (SUCCESS)" : "NO (FAILURE)") . "\n";

if ($fileExists) {
    @unlink($testFile);
}
@rmdir($dir);
