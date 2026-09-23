<?php

namespace App\Jobs;

use App\Models\Scan;
use App\Services\ScanService;
use App\Services\Zap\ZapService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunAssessment implements ShouldQueue
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
        return (int) (config('zap.timeout', 3600) + 200);
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Scan $scan
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ZapService $zapService): void
    {
        if ($this->scan->fresh()->status === 'cancelled') {
            return;
        }

        $zapService->runAssessment($this->scan);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $scanService = app(ScanService::class);
        $message = $exception ? $exception->getMessage() : 'Unknown worker failure occurred.';
        $scanService->updateStatus($this->scan, 'failed', 'Job failed: ' . $message);
        $this->scan->update(['failure_reason' => $message]);
    }
}
