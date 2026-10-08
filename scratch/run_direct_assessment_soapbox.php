<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::first() ?: App\Models\User::factory()->create([
    'name' => 'Assessment Admin',
    'email' => 'admin@example.com',
]);

// 1. Create fresh scan (FLOW 1: Direct Assessment Without Pre-Flight)
$scan = App\Models\Scan::create([
    'user_id' => $user->id,
    'name' => 'FLOW 1 Direct Assessment Soapbox Test',
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
    'authenticated_url' => 'https://soapbox.cloud/admins',
]);

echo "Created Scan #{$scan->id} for FLOW 1 Direct Assessment Test.\n";
echo "Dispatching RunAssessment to queue 'assessments'...\n";

App\Jobs\RunAssessment::dispatch($scan)->onQueue('assessments');

echo "Dispatched! Scan ID: {$scan->id}\n";
