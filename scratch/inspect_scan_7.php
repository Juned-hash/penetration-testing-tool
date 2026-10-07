<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Scan;
use App\Models\ScanLog;

$scan = Scan::find(7);
if ($scan) {
    echo "=== SCAN 7 DETAILS ===\n";
    echo "ID: {$scan->id} | Name: {$scan->name} | Target: {$scan->target_url} | Status: {$scan->status}\n";
    echo "Failure Reason: {$scan->failure_reason}\n";
    
    $logs = ScanLog::where('scan_id', 7)->orderBy('id', 'asc')->get();
    echo "\n=== SCAN 7 LOGS (" . count($logs) . " entries) ===\n";
    foreach ($logs as $log) {
        echo "[{$log->created_at}] [{$log->phase}] [{$log->level}]\n{$log->message}\n-----------------------\n";
    }
} else {
    echo "Scan 7 not found.\n";
}
