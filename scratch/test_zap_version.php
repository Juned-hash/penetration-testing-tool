<?php

require __DIR__ . '/../vendor/autoload.php';
use Symfony\Component\Process\Process;

$dockerBinary = 'C:\Users\M. Junaid Ahmed\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe';
$image = 'ghcr.io/zaproxy/zaproxy:stable';

echo "Testing ZAP Version: zap.sh -cmd -version...\n";
$p = new Process([$dockerBinary, 'run', '--rm', $image, 'zap.sh', '-cmd', '-version']);
$p->setTimeout(120);
$p->run();

echo "Exit code: " . $p->getExitCode() . "\n";
echo "STDOUT:\n" . trim($p->getOutput()) . "\n";
echo "STDERR:\n" . trim($p->getErrorOutput()) . "\n";
