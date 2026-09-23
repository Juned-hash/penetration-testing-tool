<?php

require __DIR__ . '/../vendor/autoload.php';
use Symfony\Component\Process\Process;

echo "1. Testing ['docker', '--version']...\n";
try {
    $p1 = new Process(['docker', '--version']);
    $p1->setTimeout(10);
    $p1->run();
    echo "Exit code: " . $p1->getExitCode() . "\n";
    echo "Output: " . trim($p1->getOutput()) . "\n";
    echo "Error: " . trim($p1->getErrorOutput()) . "\n";
} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

echo "\n2. Testing ['docker.exe', '--version']...\n";
try {
    $p2 = new Process(['docker.exe', '--version']);
    $p2->setTimeout(10);
    $p2->run();
    echo "Exit code: " . $p2->getExitCode() . "\n";
    echo "Output: " . trim($p2->getOutput()) . "\n";
    echo "Error: " . trim($p2->getErrorOutput()) . "\n";
} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
