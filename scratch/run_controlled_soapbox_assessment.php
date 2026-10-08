<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::first() ?: App\Models\User::factory()->create([
    'name' => 'Assessment Admin',
    'email' => 'admin@example.com',
]);

// Create fresh scan specifically for target URL https://soapbox.cloud/admins
$scan = App\Models\Scan::create([
    'user_id' => $user->id,
    'name' => 'Soapbox Admins Target Preserved Assessment',
    'target_url' => 'https://soapbox.cloud/admins',
    'environment' => 'staging',
    'status' => 'queued',
    'authorization_confirmed_at' => now(),
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
    'authentication_enabled' => true,
]);

App\Models\AuthenticationConfiguration::create([
    'scan_id' => $scan->id,
    'mode' => 'form',
    'login_url' => 'https://soapbox.cloud/login',
    'username' => 'admin@soapbox.cloud',
    'password' => 'Admin@0147',
    'username_field' => 'email',
    'password_field' => 'password',
    'authenticated_url' => 'https://soapbox.cloud/admins',
]);

echo "Created Scan #{$scan->id} with target_url = https://soapbox.cloud/admins\n";
echo "Executing ZapService->runAssessment directly...\n";

$zapService = app(App\Services\Zap\ZapService::class);
$result = $zapService->runAssessment($scan);

echo "Execution finished with result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
echo "Final Scan Status: " . $scan->fresh()->status . "\n";
