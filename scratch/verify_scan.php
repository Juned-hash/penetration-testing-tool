<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Scan;
use App\Services\Zap\ZapService;

$scan = Scan::latest()->first();

if (!$scan) {
    echo "No scan found in database.\n";
    exit(0);
}

echo "Testing scan #{$scan->id} (Target: {$scan->target_url})\n";
$expectedWorkDir = str_replace('\\', '/', storage_path("app/zap/scan_{$scan->id}"));
echo "Expected WorkDir: {$expectedWorkDir}\n";

$runner = app(\App\Services\Zap\ZapRunner::class);
$dockerTarget = $runner->translateTargetUrlForDocker($scan->target_url);
echo "Docker Target: {$dockerTarget}\n";

$cmdArray = $runner->buildDockerCommand($expectedWorkDir, 'assessment.yaml');
echo "Built Docker Command Array:\n";
print_r($cmdArray);
