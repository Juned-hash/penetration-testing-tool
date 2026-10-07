<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$scans = App\Models\Scan::with('logs')->latest()->take(10)->get();
foreach ($scans as $scan) {
    echo "Scan #{$scan->id} (Name: {$scan->name}, Status: {$scan->status}):\n";
    foreach ($scan->logs as $log) {
        echo "  [{$log->phase}] " . substr($log->message, 0, 150) . "\n";
    }
}
