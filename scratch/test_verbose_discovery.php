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

echo "=== STARTING VERBOSE DISCOVERY LOGGING RUN ===\n";

$user = User::first() ?: User::factory()->create();

$scan = Scan::create([
    'user_id' => $user->id,
    'name' => 'APEX Verbose Discovery Scan',
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

$configArray = $builder->buildArray($scan, '/zap/wrk', 'report.json', $dockerTargetUrl);

// Enable stdout progress and stats output
$configArray['env']['parameters']['progressToStdout'] = true;

// Add tests to jobs to dump URLs added statistics to stdout
foreach ($configArray['jobs'] as &$job) {
    if ($job['type'] === 'spiderClient') {
        $job['parameters']['maxDuration'] = 2;
    } elseif ($job['type'] === 'spiderAjax') {
        $job['parameters']['maxDuration'] = 3;
        $job['tests'] = [
            [
                'name' => 'AJAX Spider URLs Added',
                'type' => 'stats',
                'statistic' => 'stats.spiderAjax.urls.added',
                'operator' => '>=',
                'value' => 0,
                'onFail' => 'info',
            ]
        ];
    } elseif ($job['type'] === 'spider') {
        $job['parameters']['maxDuration'] = 1;
        $job['tests'] = [
            [
                'name' => 'Traditional Spider URLs Added',
                'type' => 'stats',
                'statistic' => 'automation.spider.urls.added',
                'operator' => '>=',
                'value' => 0,
                'onFail' => 'info',
            ]
        ];
    }
}
unset($job);

$configArray['jobs'] = array_values(array_filter($configArray['jobs'], function ($j) {
    return in_array($j['type'], ['spiderClient', 'spiderAjax', 'spider', 'report'], true);
}));

$yamlContent = \Symfony\Component\Yaml\Yaml::dump($configArray, 10, 2);

$testScanId = "verbose_disc_" . $scan->id;
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

$startTime = microtime(true);
$startResult = $runner->startZapContainer($containerName, 600);
$elapsedTime = round(microtime(true) - $startTime, 2);

$runner->exportZapArtifacts($containerName, $workDir);
$runner->removeZapContainer($containerName);
$runner->removeWorkspaceVolume($volumeName);

echo "=== ZAP STDOUT VERBOSE OUTPUT ===\n" . ($startResult['output'] ?? '(none)') . "\n";
echo "=== ZAP STDERR VERBOSE OUTPUT ===\n" . ($startResult['error'] ?? '(none)') . "\n";

$scan->delete();
