<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::first() ?: App\Models\User::factory()->create();

// 1. Create Scan #16 for FLOW 2
$scan = App\Models\Scan::create([
    'user_id' => $user->id,
    'name' => 'FLOW 2 Pre-Flight Then Assessment Test',
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

App\Models\ScanScope::create([
    'scan_id' => $scan->id,
    'type' => 'exclude',
    'path' => '/*',
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
    'password' => 'password123',
    'authenticated_url' => 'https://soapbox.cloud/admins',
]);

echo "Created Scan #{$scan->id} for FLOW 2 Test.\n";
echo "Step 1: Running Pre-Flight TestAuthentication job...\n";

App\Jobs\TestAuthentication::dispatchSync($scan);

echo "Pre-Flight test completed! Log count: " . $scan->logs()->count() . "\n";
echo "Step 2: Dispatching full assessment RunAssessment job...\n";

App\Jobs\RunAssessment::dispatch($scan)->onQueue('assessments');

echo "Dispatched RunAssessment for Scan #16!\n";
