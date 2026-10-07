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

$scan = App\Models\Scan::with('logs')->find(4);
if ($scan) {
    echo "Scan #4 Status: {$scan->status}\n";
    echo "Failure Reason: {$scan->failure_reason}\n\n";
    echo "LOGS:\n";
    foreach ($scan->logs as $log) {
        echo "[{$log->created_at}] [{$log->phase}]\n{$log->message}\n-------------------\n";
    }
}
