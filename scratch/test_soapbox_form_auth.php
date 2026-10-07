<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Scan;
use App\Models\User;
use App\Models\AuthenticationConfiguration;
use App\Models\ScanScope;
use App\Models\ScanConfiguration;
use App\Services\Zap\ZapConfigurationBuilder;

echo "=== INSPECTING CURRENT SOAPBOX FORM AUTHENTICATION YAML ===\n";

$user = User::first() ?: User::factory()->create();

$scan = Scan::create([
    'user_id' => $user->id,
    'name' => 'SOAPBOX Form Auth Inspection Scan',
    'target_url' => 'https://soapbox.cloud/admins',
    'environment' => 'production',
    'status' => 'draft',
]);

ScanScope::create([
    'scan_id' => $scan->id,
    'type' => 'include',
    'path' => '/admins*',
]);

ScanConfiguration::create([
    'scan_id' => $scan->id,
    'spider_enabled' => true,
    'passive_scan_enabled' => true,
    'active_scan_enabled' => false,
]);

AuthenticationConfiguration::create([
    'scan_id' => $scan->id,
    'mode' => 'form',
    'login_url' => 'https://soapbox.cloud/login',
    'username_field' => 'email',
    'password_field' => 'password',
    'username' => 'admin@soapbox.cloud',
    'password' => 'secret-password-123',
    'authenticated_url' => 'https://soapbox.cloud/admins',
    'logged_in_indicator' => 'Dashboard',
    'logged_out_indicator' => 'Sign In',
]);

$builder = app(ZapConfigurationBuilder::class);
$yaml = $builder->buildYaml($scan, '/tmp/reports');

echo "Generated YAML:\n" . $builder->sanitizeYamlForLogging($yaml) . "\n";

$scan->delete();
