<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$count = App\Models\Scan::count();
echo "Total scans in DB: $count\n";

foreach (App\Models\Scan::latest()->take(10)->get() as $s) {
    echo "Scan #{$s->id}: name='{$s->name}', target='{$s->target_url}', status='{$s->status}'\n";
}
