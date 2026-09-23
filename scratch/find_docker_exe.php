<?php

require __DIR__ . '/../vendor/autoload.php';

$candidates = [
    'C:\Program Files\Docker\Docker\resources\bin\docker.exe',
    'C:\Program Files\Docker\Docker\resources\docker.exe',
    'C:\Program Files\Docker\Docker\resources\cli-plugins\docker.exe',
    getenv('LOCALAPPDATA') . '\Programs\DockerDesktop\resources\bin\docker.exe',
    getenv('LOCALAPPDATA') . '\Docker\Docker\resources\bin\docker.exe',
    getenv('USERPROFILE') . '\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe',
];

foreach ($candidates as $c) {
    if (file_exists($c)) {
        echo "Found candidate: {$c}\n";
        $p = new \Symfony\Component\Process\Process([$c, '--version']);
        $p->setTimeout(5);
        $p->run();
        echo "  Exit code: " . $p->getExitCode() . "\n";
        echo "  Output: " . trim($p->getOutput()) . "\n";
        echo "  Error: " . trim($p->getErrorOutput()) . "\n";
    }
}
