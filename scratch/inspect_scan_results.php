<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$scan = App\Models\Scan::latest()->first();

if (!$scan) {
    echo "No scan found in DB.\n";
    exit(1);
}

echo "=== SCAN DETAILS ===\n";
echo "ID: {$scan->id} | Name: {$scan->name} | Target: {$scan->target_url} | Status: {$scan->status}\n";

echo "\n=== SCAN LOGS (EXECUTION PHASES) ===\n";
$logs = App\Models\ScanLog::where('scan_id', $scan->id)->get();
foreach ($logs as $log) {
    echo "[{$log->phase}] {$log->message}\n\n";
}

echo "\n=== FINDINGS SUMMARY ===\n";
$findingCount = App\Models\Finding::where('scan_id', $scan->id)->count();
echo "Total Findings Imported: {$findingCount}\n";

$sampleFindings = App\Models\Finding::where('scan_id', $scan->id)->take(15)->get();
foreach ($sampleFindings as $f) {
    echo "- [{$f->risk_rating}] {$f->title} | URL: {$f->url}\n";
}
