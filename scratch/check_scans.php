<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$scans = App\Models\Scan::with(['scanConfiguration', 'authenticationConfiguration', 'scanScopes'])->get();
foreach ($scans as $s) {
    echo "Scan #{$s->id}: name={$s->name} url={$s->target_url} status={$s->status}\n";
    if ($s->scanConfiguration) {
        echo "   spider=" . ($s->scanConfiguration->spider_enabled ? '1' : '0')
           . " ajax_spider=" . ($s->scanConfiguration->ajax_spider_enabled ? '1' : '0')
           . " passive_scan=" . ($s->scanConfiguration->passive_scan_enabled ? '1' : '0')
           . " active_scan=" . ($s->scanConfiguration->active_scan_enabled ? '1' : '0') . "\n";
    }
}
