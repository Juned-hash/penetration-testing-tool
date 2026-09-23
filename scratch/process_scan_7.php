<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Scan;
use App\Services\Zap\ZapResultParser;
use App\Services\ScanService;

$scan = Scan::find(7);
if (!$scan) {
    echo "Scan 7 not found.\n";
    exit(1);
}

$reportPath = storage_path('app/zap/scan_7/report.json');
echo "Report Path: {$reportPath}\n";

$parser = app(ZapResultParser::class);
$scanService = app(ScanService::class);

$count = $parser->parseAndStore($scan, $reportPath);
echo "Imported Findings Count: {$count}\n";

$scanService->updateStatus($scan, 'completed', "OWASP ZAP assessment completed successfully. Imported {$count} finding(s).");

$scan->refresh();
echo "Updated Scan Status: {$scan->status}\n";

