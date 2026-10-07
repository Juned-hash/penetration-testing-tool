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

echo "=== STARTING SHORT DISCOVERY VALIDATION (spiderClient -> spiderAjax -> spider) ===\n";

$user = User::first() ?: User::factory()->create();

$scan = Scan::create([
    'user_id' => $user->id,
    'name' => 'APEX AJAX Discovery Validation Scan',
    'target_url' => 'https://10.100.0.5:8443/ords/r/corex10/spbx-app-inc/dashboard',
    'environment' => 'staging',
    'status' => 'starting',
]);

ScanScope::create([
    'scan_id' => $scan->id,
    'type' => 'include',
    'path' => '/ords/r/corex10/spbx-app-inc/*',
]);

ScanConfiguration::create([
    'scan_id' => $scan->id,
    'spider_enabled' => true,
    'ajax_spider_enabled' => true,
    'passive_scan_enabled' => false,
    'active_scan_enabled' => false,
]);

AuthenticationConfiguration::create([
    'scan_id' => $scan->id,
    'mode' => 'browser',
    'login_url' => 'https://10.100.0.5:8443/ords/r/corex10/soapboxcloud_landing_page/login',
    'username' => 'camila.rocha@cornerstoneinfra.com',
    'password' => 'oracle',
    'authenticated_url' => 'https://10.100.0.5:8443/ords/r/corex10/spbx-app-inc/dashboard',
]);

$scan->loadMissing(['scanScopes', 'scanConfiguration', 'authenticationConfiguration']);

$builder = app(ZapConfigurationBuilder::class);
$runner = app(ZapRunner::class);
$dockerTargetUrl = $runner->translateTargetUrlForDocker($scan->target_url);

// Build array from configuration
$configArray = $builder->buildArray($scan, '/zap/wrk', 'report.json', $dockerTargetUrl);

// Override max durations for a fast discovery-only validation run (No active scan)
foreach ($configArray['jobs'] as &$job) {
    if ($job['type'] === 'spiderClient') {
        $job['parameters']['maxDuration'] = 2;
    } elseif ($job['type'] === 'spiderAjax') {
        $job['parameters']['maxDuration'] = 3;
    } elseif ($job['type'] === 'spider') {
        $job['parameters']['maxDuration'] = 1;
    }
}
unset($job);

// Filter out activeScan and passiveScan-wait for pure discovery run
$configArray['jobs'] = array_values(array_filter($configArray['jobs'], function ($j) {
    return in_array($j['type'], ['spiderClient', 'spiderAjax', 'spider', 'report'], true);
}));

$yamlContent = \Symfony\Component\Yaml\Yaml::dump($configArray, 10, 2);

$testScanId = "ajax_disc_" . $scan->id;
$workDir = str_replace('\\', '/', storage_path("app/zap/scan_{$testScanId}"));
\Illuminate\Support\Facades\File::ensureDirectoryExists($workDir);
$hostYamlPath = $workDir . '/assessment.yaml';
\Illuminate\Support\Facades\File::put($hostYamlPath, $yamlContent);

echo "1. Generated YAML Configuration:\n" . $yamlContent . "\n\n";

$volumeName = $runner->getVolumeName($testScanId);
$containerName = $runner->getContainerName($testScanId);

echo "2. Initializing Docker workspace volume: {$volumeName}...\n";
$runner->createWorkspaceVolume($volumeName);
$runner->initializeWorkspaceVolume($volumeName);
$runner->createZapContainer($volumeName, $containerName, 'assessment.yaml', null, false);
$runner->copyAssessmentYamlToContainer($containerName, $hostYamlPath, 'assessment.yaml');

echo "3. Executing ZAP Discovery Plan...\n";
$startTime = microtime(true);
$startResult = $runner->startZapContainer($containerName, 600);
$elapsedTime = round(microtime(true) - $startTime, 2);

echo "   Container Execution Completed in {$elapsedTime} seconds.\n";
echo "   Exit Code: " . ($startResult['exitCode'] ?? 'N/A') . "\n";

echo "4. Exporting artifacts host workspace...\n";
$runner->exportZapArtifacts($containerName, $workDir);

echo "5. Cleaning up container and volume...\n";
$runner->removeZapContainer($containerName);
$runner->removeWorkspaceVolume($volumeName);

echo "\n=== ZAP STDOUT OUTPUT ===\n" . ($startResult['output'] ?? '(none)') . "\n";
echo "\n=== ZAP STDERR OUTPUT ===\n" . ($startResult['error'] ?? '(none)') . "\n";

$reportPath = $workDir . '/report.json';
if (file_exists($reportPath)) {
    echo "\n=== REPORT.JSON GENERATED SUCCESSFULLY (" . filesize($reportPath) . " bytes) ===\n";
} else {
    echo "\n=== NO REPORT.JSON FOUND IN {$workDir} ===\n";
}

// Clean up scan record
$scan->delete();
