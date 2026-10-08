<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config([
    'database.connections.mysql.port' => 33066,
    'database.connections.mysql.username' => 'pentest_user',
    'database.connections.mysql.password' => 'pentest_password',
]);
DB::purge('mysql');
DB::reconnect('mysql');

foreach (App\Models\ScanLog::where('scan_id', 21)->get() as $l) {
    echo "[{$l->phase}] {$l->message}\n";
}
