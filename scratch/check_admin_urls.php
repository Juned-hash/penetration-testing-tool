<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$scan = App\Models\Scan::latest()->first();

echo "Scan ID: {$scan->id}\n";
echo "Total Findings: " . App\Models\Finding::where('scan_id', $scan->id)->count() . "\n";

echo "\n--- Sample Findings with /admins URLs ---\n";
$adminFindings = App\Models\Finding::where('scan_id', $scan->id)
    ->where('url', 'like', '%/admins%')
    ->get();

echo "Total Findings on /admins routes: " . $adminFindings->count() . "\n";

$uniqueUrls = $adminFindings->pluck('url')->unique();
echo "Unique /admins URLs scanned: " . $uniqueUrls->count() . "\n";
foreach ($uniqueUrls->take(20) as $url) {
    echo " - " . $url . "\n";
}
