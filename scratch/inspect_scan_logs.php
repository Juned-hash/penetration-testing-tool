

<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$logs = App\Models\ScanLog::whereIn('scan_id', [1, 3])->orderBy('id', 'asc')->get();

foreach ($logs as $log) {
    echo "[Scan {$log->scan_id}] [{$log->created_at}] [{$log->level}] [{$log->phase}]: {$log->message}\n";
}
