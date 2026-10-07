<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config([
    'database.connections.mysql.port' => 33066,
    'database.connections.mysql.username' => 'pentest_user',
    'database.connections.mysql.password' => 'pentest_password',
]);

DB::purge('mysql');
DB::reconnect('mysql');

$scan = App\Models\Scan::find(4);
if (!$scan) {
    echo "Scan #4 not found.\n";
    exit(1);
}

echo "Starting Controlled Full Assessment for Scan #{$scan->id} ({$scan->name})...\n";
$startTime = microtime(true);

$zapService = app(App\Services\Zap\ZapService::class);
$success = $zapService->runAssessment($scan);

$elapsed = round(microtime(true) - $startTime, 2);

$freshScan = $scan->fresh();
echo "\n==========================================\n";
echo "ASSESSMENT RESULT: " . ($success ? "SUCCESS" : "FAILED") . "\n";
echo "Scan Status: {$freshScan->status}\n";
echo "Total Duration: {$elapsed} seconds\n";
if ($freshScan->failure_reason) {
    echo "Failure Reason: {$freshScan->failure_reason}\n";
}
echo "Findings Count: " . $freshScan->findings()->count() . "\n";
echo "==========================================\n\n";

echo "SCAN LOGS:\n";
foreach ($freshScan->logs()->orderBy('id')->get() as $log) {
    echo "[{$log->created_at}] [{$log->phase}] {$log->message}\n";
}
