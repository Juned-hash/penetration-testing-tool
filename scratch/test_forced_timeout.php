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

$scan = App\Models\Scan::create([
    'user_id' => 1,
    'name' => 'Forced Timeout Verification Test',
    'target_url' => 'https://soapbox.cloud/admins',
    'environment' => 'staging',
    'status' => 'draft',
]);

App\Models\ScanScope::create([
    'scan_id' => $scan->id,
    'type' => 'include',
    'path' => '/admins/*',
]);

App\Models\ScanConfiguration::create([
    'scan_id' => $scan->id,
    'spider_enabled' => true,
    'ajax_spider_enabled' => false,
    'passive_scan_enabled' => true,
    'active_scan_enabled' => true,
]);

// Set a short timeout (5 seconds) to force timeout detection and cleanup verification
config(['zap.timeout' => 5]);

echo "Running Forced Timeout Verification Test for Scan #{$scan->id} with 5-second timeout...\n";
$startTime = microtime(true);

$zapService = app(App\Services\Zap\ZapService::class);
$success = $zapService->runAssessment($scan);

$elapsed = round(microtime(true) - $startTime, 2);
$freshScan = $scan->fresh();

echo "\n==========================================\n";
echo "FORCED TIMEOUT TEST RESULT: " . ($success ? "UNEXPECTED SUCCESS" : "PASSED (TIMED OUT AS EXPECTED)") . "\n";
echo "Scan Status: {$freshScan->status}\n";
echo "Total Duration: {$elapsed} seconds\n";
echo "Failure Reason: {$freshScan->failure_reason}\n";
echo "==========================================\n\n";

echo "SCAN LOGS:\n";
foreach ($freshScan->logs()->orderBy('id')->get() as $log) {
    echo "[{$log->created_at}] [{$log->phase}] {$log->message}\n";
}
