<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Zap\ZapRunner;
use Symfony\Component\Process\Process;

$runner = app(ZapRunner::class);
$dockerBinary = $runner->getDockerBinaryPath();
$dockerImage = config('zap.docker_image', 'ghcr.io/zaproxy/zaproxy:stable');

echo "Testing Docker binary: {$dockerBinary}\n";
echo "Testing Docker image: {$dockerImage}\n";

// 1. Test ZAP Version
$versionCmd = [$dockerBinary, 'run', '--rm', $dockerImage, 'zap.sh', '-version'];
echo "1. Testing ZAP version: " . implode(' ', $versionCmd) . "\n";
$proc1 = new Process($versionCmd);
$proc1->run();
echo "Exit Code: " . $proc1->getExitCode() . "\n";
echo "STDOUT: " . trim($proc1->getOutput()) . "\n";
echo "STDERR: " . trim($proc1->getErrorOutput()) . "\n\n";

// 2. Test DNS Resolution inside Docker
$dnsCmd = [$dockerBinary, 'run', '--rm', $dockerImage, 'getent', 'hosts', 'soapbox.cloud'];
echo "2. Testing DNS resolution for soapbox.cloud inside Docker...\n";
$proc2 = new Process($dnsCmd);
$proc2->run();
echo "Exit Code: " . $proc2->getExitCode() . "\n";
echo "STDOUT: " . trim($proc2->getOutput()) . "\n";
echo "STDERR: " . trim($proc2->getErrorOutput()) . "\n\n";

// 3. Test HTTPS/TLS Connection to https://soapbox.cloud inside Docker
$httpsCmd = [$dockerBinary, 'run', '--rm', $dockerImage, 'curl', '-i', '-v', '-m', '15', 'https://soapbox.cloud'];
echo "3. Testing HTTPS/TLS connection to https://soapbox.cloud inside Docker...\n";
$proc3 = new Process($httpsCmd);
$proc3->run();
echo "Exit Code: " . $proc3->getExitCode() . "\n";
echo "STDOUT:\n" . trim($proc3->getOutput()) . "\n";
echo "STDERR:\n" . trim($proc3->getErrorOutput()) . "\n\n";

