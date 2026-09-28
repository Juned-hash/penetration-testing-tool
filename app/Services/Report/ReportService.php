<?php

namespace App\Services\Report;

use App\Models\Report;
use App\Models\Scan;
use App\Models\ScanLog;
use App\Services\Report\Contracts\ReportGeneratorInterface;
use InvalidArgumentException;

class ReportService
{
    /**
     * Generate and store an assessment report for the given Scan.
     *
     * @param Scan $scan
     * @param string $format
     * @return Report
     */
    /**
     * Queue an assessment report generation job asynchronously.
     *
     * @param Scan $scan
     * @param string $format
     * @return Report
     */
    public function generateReport(Scan $scan, string $format = 'pdf'): Report
    {
        $format = strtolower($format);

        // Prevent duplicate generation if a report for this scan is currently queued or generating
        $existingPendingReport = Report::where('scan_id', $scan->id)
            ->where('type', $format)
            ->whereIn('status', ['queued', 'generating'])
            ->first();

        if ($existingPendingReport) {
            return $existingPendingReport;
        }

        $report = Report::create([
            'scan_id' => $scan->id,
            'type' => $format,
            'file_path' => '',
            'status' => 'queued',
        ]);

        ScanLog::create([
            'scan_id' => $scan->id,
            'level' => 'info',
            'phase' => 'generating_report',
            'message' => 'Queued ' . strtoupper($format) . ' security assessment report generation job.',
        ]);

        \App\Jobs\GeneratePdfReport::dispatch($report)->onQueue('reports');

        return $report;
    }

    /**
     * Resolve appropriate ReportGeneratorInterface implementation.
     *
     * @param string $format
     * @return ReportGeneratorInterface
     */
    protected function resolveGenerator(string $format): ReportGeneratorInterface
    {
        return match (strtolower($format)) {
            'pdf' => app(PdfReportGenerator::class),
            default => throw new InvalidArgumentException("Unsupported report format [{$format}]."),
        };
    }
}
