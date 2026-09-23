<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Scan;
use App\Services\Zap\ZapConfigurationBuilder;
use App\Services\Zap\ZapRunner;
use Illuminate\Support\Facades\File;

$scan = Scan::find(8) ?: Scan::latest()->first();

if (!$scan) {
    echo "No scan found.\n";
    exit(1);
}

echo "Inspecting Scan #{$scan->id}\n";
echo "Target URL: {$scan->target_url}\n";

$runner = app(ZapRunner::class);
$builder = app(ZapConfigurationBuilder::class);

$workDir = str_replace('\\', '/', storage_path("app/zap/scan_{$scan->id}"));
File::ensureDirectoryExists($workDir);

$dockerTargetUrl = $runner->translateTargetUrlForDocker($scan->target_url);
$hostYamlPath = $workDir . '/assessment.yaml';

$yamlContent = $builder->buildYaml(
    $scan,
    '/zap/wrk',
    'report.json',
    $dockerTargetUrl
);

File::put($hostYamlPath, $yamlContent);

echo "Generated YAML Content:\n";
echo $yamlContent . "\n";

echo "Running ZAP Automation Framework container test...\n";
$result = $runner->runAutomationFramework($hostYamlPath, $workDir);

echo "Success? " . ($result['success'] ? 'YES' : 'NO') . "\n";
echo "Exit Code: " . $result['exitCode'] . "\n";
echo "STDOUT:\n" . $result['output'] . "\n";
echo "STDERR:\n" . $result['error'] . "\n";

