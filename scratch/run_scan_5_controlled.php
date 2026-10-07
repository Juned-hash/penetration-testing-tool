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
    'name' => 'Controlled Full Assessment Soapbox Final',
    'target_url' => 'https://soapbox.cloud/admins',
    'environment' => 'staging',
    'status' => 'pending',
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

App\Models\AuthenticationConfiguration::create([
    'scan_id' => $scan->id,
    'mode' => 'form',
    'login_url' => 'https://soapbox.cloud/login',
    'username' => 'admin@soapbox.cloud',
    'password' => 'password123',
    'authenticated_url' => 'https://soapbox.cloud/admins',
]);

echo "Created Scan #{$scan->id}. Starting controlled assessment...\n";
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
