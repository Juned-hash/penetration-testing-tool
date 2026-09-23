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
    public function generateReport(Scan $scan, string $format = 'pdf'): Report
    {
        $generator = $this->resolveGenerator($format);

        $filePath = $generator->generate($scan);

        $report = Report::create([
            'scan_id' => $scan->id,
            'type' => strtolower($format),
            'file_path' => $filePath,
            'status' => 'completed',
            'generated_at' => now(),
        ]);

        ScanLog::create([
            'scan_id' => $scan->id,
            'level' => 'info',
            'phase' => 'generating_report',
            'message' => 'Generated ' . strtoupper($format) . ' security assessment report.',
        ]);

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
