<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Scan;
use App\Models\ScanLog;

$scan = Scan::find(6);
if ($scan) {
    echo "=== SCAN 6 DETAILS ===\n";
    echo "ID: {$scan->id} | Name: {$scan->name} | Target: {$scan->target_url} | Status: {$scan->status}\n";
    echo "Failure Reason: {$scan->failure_reason}\n";
    
    $logs = ScanLog::where('scan_id', 6)->orderBy('id', 'asc')->get();
    echo "\n=== SCAN 6 LOGS (" . count($logs) . " entries) ===\n";
    foreach ($logs as $log) {
        echo "[{$log->created_at}] [{$log->phase}] [{$log->level}]\n{$log->message}\n-----------------------\n";
    }
} else {
    echo "Scan 6 not found.\n";
}
