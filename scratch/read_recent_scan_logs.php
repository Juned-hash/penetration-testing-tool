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

$scans = App\Models\Scan::orderBy('id', 'desc')->take(5)->get();
foreach ($scans as $scan) {
    echo "================ Scan #{$scan->id}: {$scan->name} ({$scan->status}) ================\n";
    foreach ($scan->logs()->orderBy('id')->get() as $l) {
        echo "[{$l->created_at}] [{$l->phase}] {$l->message}\n";
    }
    echo "\n";
}
