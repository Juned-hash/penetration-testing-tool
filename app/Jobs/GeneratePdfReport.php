<?php

namespace App\Jobs;

use App\Models\Report;
use App\Models\ScanLog;
use App\Services\Report\PdfReportGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeneratePdfReport implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * Indicate if the job should fail when a timeout occurs.
     */
    public bool $failOnTimeout = true;

    /**
     * Calculate the number of seconds the job can run before timing out.
     */
    public function timeout(): int
    {
        return 600; // 10 minutes timeout for large report generation
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Report $report
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PdfReportGenerator $pdfGenerator): void
    {
        $startTime = microtime(true);
        $report = $this->report->fresh();

        if (!$report) {
            return;
        }

        $scan = $report->scan;

        if (!$scan) {
            $report->update([
                'status' => 'failed',
                'error_message' => 'Associated scan model not found.',
            ]);
            return;
        }

        // 1. Mark report generation as generating
        $report->update([
            'status' => 'generating',
            'started_at' => now(),
        ]);

        ScanLog::create([
            'scan_id' => $scan->id,
            'level' => 'info',
            'phase' => 'generating_report',
            'message' => 'PDF report generation started by background worker.',
        ]);

        try {
            // 2. Load required relationships
            $scan->loadMissing([
                'user',
                'scanScopes',
                'scanConfiguration',
                'authenticationConfiguration',
                'findings',
            ]);

            $findingCount = $scan->findings->count();

            // 3. Render HTML & measure size
            $renderedHtml = view('reports.pdf', ['scan' => $scan])->render();
            $htmlBytes = strlen($renderedHtml);

            Log::info("PDF_GENERATION_STARTED: Scan #{$scan->id} ('{$scan->name}') - Findings: {$findingCount}, HTML Size: {$htmlBytes} bytes.");

            // 4. Render PDF
            $relativePath = $pdfGenerator->generate($scan);
            $fullPath = storage_path('app/' . $relativePath);

            if (!File::exists($fullPath) || filesize($fullPath) === 0) {
                throw new \Exception('Generated PDF report file was empty or not written to disk.');
            }

            $pdfBytes = filesize($fullPath);
            $durationSeconds = round(microtime(true) - $startTime, 2);

            // 5. Mark report as completed
            $report->update([
                'status' => 'completed',
                'file_path' => $relativePath,
                'completed_at' => now(),
                'generated_at' => now(),
                'error_message' => null,
            ]);

            ScanLog::create([
                'scan_id' => $scan->id,
                'level' => 'info',
                'phase' => 'generating_report',
                'message' => "PDF report generated successfully in {$durationSeconds}s ({$pdfBytes} bytes).",
            ]);

            Log::info("PDF_GENERATION_COMPLETED: Scan #{$scan->id} - Duration: {$durationSeconds}s, Findings: {$findingCount}, HTML: {$htmlBytes}B, PDF: {$pdfBytes}B.");
        } catch (Throwable $e) {
            $durationSeconds = round(microtime(true) - $startTime, 2);
            $safeError = mb_strimwidth($e->getMessage(), 0, 255, '...');

            $report->update([
                'status' => 'failed',
                'error_message' => "PDF generation failure after {$durationSeconds}s: {$safeError}",
            ]);

            ScanLog::create([
                'scan_id' => $scan->id,
                'level' => 'error',
                'phase' => 'generating_report',
                'message' => "PDF report generation failed: {$safeError}",
            ]);

            Log::error("PDF_GENERATION_FAILED: Scan #{$scan->id} - Error: {$e->getMessage()}", [
                'exception' => $e,
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $report = $this->report->fresh();
        if ($report && $report->status !== 'completed') {
            $report->update([
                'status' => 'failed',
                'error_message' => $exception ? mb_strimwidth($exception->getMessage(), 0, 255, '...') : 'Worker process failed unexpectedly.',
            ]);
        }
    }
}
