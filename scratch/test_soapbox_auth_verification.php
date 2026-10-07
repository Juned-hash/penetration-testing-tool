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
    'name' => 'Phase 1 Dedicated Auth Verification Test',
    'target_url' => 'https://soapbox.cloud/admins',
    'environment' => 'staging',
    'status' => 'draft',
]);

App\Models\ScanScope::create([
    'scan_id' => $scan->id,
    'type' => 'include',
    'path' => '/admins/*',
]);

App\Models\AuthenticationConfiguration::create([
    'scan_id' => $scan->id,
    'mode' => 'form',
    'login_url' => 'https://soapbox.cloud/login',
    'username' => 'admin@soapbox.cloud',
    'password' => 'password123',
    'authenticated_url' => 'https://soapbox.cloud/admins',
]);

echo "Running Phase 1 ZAP Authentication Verification Test for Scan #{$scan->id}...\n";
$startTime = microtime(true);

$zapService = app(App\Services\Zap\ZapService::class);
$result = $zapService->testAuthentication($scan);

$elapsed = round(microtime(true) - $startTime, 2);

echo "\n==========================================\n";
echo "AUTHENTICATION TEST RESULT: {$result['status']}\n";
echo "Message: {$result['message']}\n";
echo "Duration: {$elapsed} seconds\n";
echo "Details: " . json_encode($result['details'], JSON_PRETTY_PRINT) . "\n";
echo "==========================================\n\n";

echo "SCAN LOGS:\n";
foreach ($scan->logs()->orderBy('id')->get() as $log) {
    echo "[{$log->created_at}] [{$log->phase}] {$log->message}\n";
}
