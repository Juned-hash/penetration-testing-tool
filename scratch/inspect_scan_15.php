<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$scan = App\Models\Scan::with(['logs', 'findings', 'reports'])->find(15);
if (!$scan) {
    echo "Scan #15 not found!\n";
    exit;
}

echo "=== SCAN #15 DETAILS ===\n";
echo "ID: {$scan->id} | Name: {$scan->name} | Status: {$scan->status}\n";
echo "Failure Reason: {$scan->failure_reason}\n";
echo "Findings Count: " . $scan->findings()->count() . "\n\n";

echo "=== SCAN #15 LOGS (" . $scan->logs()->count() . " entries) ===\n";
foreach ($scan->logs()->orderBy('id')->get() as $log) {
    echo "[{$log->created_at}] [{$log->phase}] [{$log->level}]\n";
    echo "{$log->message}\n";
    echo "-----------------------\n";
}
