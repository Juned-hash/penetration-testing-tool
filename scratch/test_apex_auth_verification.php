<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Scan;
use App\Models\User;
use App\Models\AuthenticationConfiguration;
use App\Services\Zap\ZapConfigurationBuilder;
use App\Services\Zap\ZapRunner;
use App\Services\Zap\ZapAuthenticationDiagnosticsParser;

echo "=== STARTING SHORT APEX AUTHENTICATION VERIFICATION VALIDATION TEST ===\n";

$user = User::first() ?: User::factory()->create();

$scan = Scan::create([
    'user_id' => $user->id,
    'name' => 'APEX Auth Verification Validation Scan',
    'target_url' => 'https://10.100.0.5:8443/ords/r/intg001/spbx-app-inc/dashboard',
    'environment' => 'development',
    'status' => 'starting',
]);

AuthenticationConfiguration::create([
    'scan_id' => $scan->id,
    'mode' => 'browser',
    'login_url' => 'https://10.100.0.5:8443/ords/r/intg001/soapboxcloud_landing_page/login',
    'username' => 'camila.rocha@cornerstoneinfra.com',
    'password' => 'oracle',
    'authenticated_url' => 'https://10.100.0.5:8443/ords/r/intg001/soapboxcloud_landing_page/home',
]);

$scan->loadMissing(['scanScopes', 'scanConfiguration', 'authenticationConfiguration']);

// Generate new YAML using updated builder logic
$builder = app(ZapConfigurationBuilder::class);
$runner = app(ZapRunner::class);
$dockerTargetUrl = $runner->translateTargetUrlForDocker($scan->target_url);

// Create custom short validation plan (spiderClient maxDuration: 2 mins, no activeScan)
$configArray = $builder->buildArray($scan, '/zap/wrk', 'report.json', $dockerTargetUrl);
$configArray['jobs'] = [
    [
        'type' => 'spiderClient',
        'parameters' => [
            'context' => 'Target Context',
            'url' => 'https://10.100.0.5:8443/ords/r/intg001/spbx-app-inc/dashboard',
            'maxDuration' => 2,
            'maxCrawlDepth' => 1,
            'numberOfBrowsers' => 1,
            'browserId' => 'firefox-headless',
            'scopeCheck' => 'Flexible',
            'user' => 'AssessmentUser',
        ]
    ],
    [
        'type' => 'report',
        'parameters' => [
            'template' => 'traditional-json',
            'reportDir' => '/zap/wrk',
            'reportFile' => 'report.json',
            'reportTitle' => 'APEX Auth Verification Test Report',
            'displayReport' => false,
        ]
    ]
];

// Convert to YAML
$yamlContent = \Symfony\Component\Yaml\Yaml::dump($configArray, 10, 2);

$testScanId = "auth_ver_" . $scan->id;
$workDir = str_replace('\\', '/', storage_path("app/zap/scan_{$testScanId}"));
\Illuminate\Support\Facades\File::ensureDirectoryExists($workDir);
$hostYamlPath = $workDir . '/assessment.yaml';
\Illuminate\Support\Facades\File::put($hostYamlPath, $yamlContent);

echo "1. Generated Assessment YAML:\n" . $yamlContent . "\n";

$volumeName = $runner->getVolumeName($testScanId);
$containerName = $runner->getContainerName($testScanId);

echo "2. Initializing Docker named volume: {$volumeName}...\n";
$runner->createWorkspaceVolume($volumeName);
$runner->initializeWorkspaceVolume($volumeName);
$runner->createZapContainer($volumeName, $containerName, 'assessment.yaml', null, false);
$runner->copyAssessmentYamlToContainer($containerName, $hostYamlPath, 'assessment.yaml');

echo "3. Starting ZAP container and executing authentication validation...\n";
$startResult = $runner->startZapContainer($containerName, 300);

echo "   Exit Code: " . ($startResult['exitCode'] ?? 'N/A') . "\n";
echo "   Success: " . ($startResult['success'] ? "YES" : "NO") . "\n";

echo "4. Exporting artifacts back to host workspace...\n";
$runner->exportZapArtifacts($containerName, $workDir);

echo "5. Cleaning up temporary container and volume...\n";
$runner->removeZapContainer($containerName);
$runner->removeWorkspaceVolume($volumeName);

echo "=== RAW ZAP STDOUT OUTPUT ===\n" . ($startResult['output'] ?? '(none)') . "\n";
echo "=== RAW ZAP STDERR OUTPUT ===\n" . ($startResult['error'] ?? '(none)') . "\n";

$parser = app(ZapAuthenticationDiagnosticsParser::class);
$diagnostics = $parser->parse($startResult, $scan);

echo "\n=== AUTHENTICATION DIAGNOSTICS RESULT ===\n";
echo "Status: " . strtoupper($diagnostics['status']) . "\n";
echo "Message: " . $diagnostics['message'] . "\n";
echo "Login URL: " . ($diagnostics['login_url'] ?? 'N/A') . "\n";
echo "User: " . ($diagnostics['user'] ?? 'AssessmentUser') . "\n";

// Clean up test scan
$scan->delete();
\Illuminate\Support\Facades\File::deleteDirectory($workDir);
