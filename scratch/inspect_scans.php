<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$scans = App\Models\Scan::with(['scanConfiguration', 'scanScopes', 'authenticationConfiguration'])->get();

foreach ($scans as $scan) {
    echo "ID: {$scan->id} | Name: {$scan->name} | Target: {$scan->target_url} | Status: {$scan->status}\n";
    if ($scan->scanConfiguration) {
        echo "  Config: spider=" . ($scan->scanConfiguration->spider_enabled ? 'true' : 'false')
            . ", ajax=" . ($scan->scanConfiguration->ajax_spider_enabled ? 'true' : 'false')
            . ", passive=" . ($scan->scanConfiguration->passive_scan_enabled ? 'true' : 'false')
            . ", active=" . ($scan->scanConfiguration->active_scan_enabled ? 'true' : 'false') . "\n";
    } else {
        echo "  Config: NULL\n";
    }
    echo "  Scopes:\n";
    foreach ($scan->scanScopes as $scope) {
        echo "    - {$scope->type}: {$scope->path}\n";
    }
}
