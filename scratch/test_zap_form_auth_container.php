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
use App\Services\Zap\ZapRunner;

echo "=== TESTING ZAP FORM AUTHENTICATION WITH SOAPBOX.CLOUD ===\n";

$user = User::first() ?: User::factory()->create();

$scan = Scan::create([
    'user_id' => $user->id,
    'name' => 'SOAPBOX Form Auth Test Scan',
    'target_url' => 'https://soapbox.cloud/admins',
    'environment' => 'production',
    'status' => 'starting',
]);

ScanScope::create([
    'scan_id' => $scan->id,
    'type' => 'include',
    'path' => '/admins*',
]);

ScanConfiguration::create([
    'scan_id' => $scan->id,
    'spider_enabled' => true,
    'passive_scan_enabled' => false,
    'active_scan_enabled' => false,
]);

AuthenticationConfiguration::create([
    'scan_id' => $scan->id,
    'mode' => 'form',
    'login_url' => 'https://soapbox.cloud/login',
    'username_field' => 'email',
    'password_field' => 'password',
    'username' => 'test-user@example.com',
    'password' => 'test-password',
    'authenticated_url' => 'https://soapbox.cloud/admins',
    'logged_in_indicator' => 'Sign Out',
    'logged_out_indicator' => 'Sign In',
]);

$builder = app(ZapConfigurationBuilder::class);
$runner = app(ZapRunner::class);

$configArray = $builder->buildArray($scan, '/zap/wrk', 'report.json', $scan->target_url);

// Test updated form parameters
$configArray['env']['contexts'][0]['authentication'] = [
    'method' => 'form',
    'parameters' => [
        'loginPageUrl' => 'https://soapbox.cloud/login',
        'loginRequestUrl' => 'https://soapbox.cloud/login',
        'loginRequestBody' => '_token={%_token%}&email={%username%}&password={%password%}',
    ],
    'verification' => [
        'method' => 'response',
        'loggedInRegex' => '(?i)(Sign Out|Logout|Admin Panel)',
        'loggedOutRegex' => '(?i)(Sign In|login-form|login-button)',
    ]
];
$configArray['env']['contexts'][0]['sessionManagement'] = [
    'method' => 'cookie',
];

// Enable progress to stdout
$configArray['env']['parameters']['progressToStdout'] = true;

// Only run traditional spider for 1 min
$configArray['jobs'] = [
    [
        'type' => 'spider',
        'parameters' => [
            'context' => 'Target Context',
            'url' => 'https://soapbox.cloud/admins',
            'maxDuration' => 1,
            'user' => 'AssessmentUser',
        ]
    ],
    [
        'type' => 'report',
        'parameters' => [
            'template' => 'traditional-json',
            'reportDir' => '/zap/wrk',
            'reportFile' => 'report.json',
            'reportTitle' => 'SOAPBOX Form Auth Test Report',
            'displayReport' => false,
        ]
    ]
];

$yamlContent = \Symfony\Component\Yaml\Yaml::dump($configArray, 10, 2);

echo "1. Generated Test YAML:\n" . $builder->sanitizeYamlForLogging($yamlContent) . "\n\n";

$testScanId = "soapbox_form_" . $scan->id;
$workDir = str_replace('\\', '/', storage_path("app/zap/scan_{$testScanId}"));
\Illuminate\Support\Facades\File::ensureDirectoryExists($workDir);
$hostYamlPath = $workDir . '/assessment.yaml';
\Illuminate\Support\Facades\File::put($hostYamlPath, $yamlContent);

$volumeName = $runner->getVolumeName($testScanId);
$containerName = $runner->getContainerName($testScanId);

$runner->createWorkspaceVolume($volumeName);
$runner->initializeWorkspaceVolume($volumeName);
$runner->createZapContainer($volumeName, $containerName, 'assessment.yaml', null, false);
$runner->copyAssessmentYamlToContainer($containerName, $hostYamlPath, 'assessment.yaml');

echo "2. Running ZAP Container...\n";
$startResult = $runner->startZapContainer($containerName, 300);

echo "3. Exporting artifacts...\n";
$runner->exportZapArtifacts($containerName, $workDir);

$runner->removeZapContainer($containerName);
$runner->removeWorkspaceVolume($volumeName);

echo "\n=== STDOUT OUTPUT ===\n" . ($startResult['output'] ?? '') . "\n";
echo "\n=== STDERR OUTPUT ===\n" . ($startResult['error'] ?? '') . "\n";

$scan->delete();
