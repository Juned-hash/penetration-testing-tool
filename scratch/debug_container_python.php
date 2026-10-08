<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Symfony\Component\Process\Process;

$runner = app(\App\Services\Zap\ZapRunner::class);
$dockerBinary = $runner->getDockerBinaryPath();

$proc = new Process([$dockerBinary, 'run', '--rm', 'ghcr.io/zaproxy/zaproxy:stable', 'python3', '--version']);
$proc->run();

echo "Python version test exit code: " . $proc->getExitCode() . "\n";
echo "Output: " . $proc->getOutput() . "\n";
echo "Error: " . $proc->getErrorOutput() . "\n";
