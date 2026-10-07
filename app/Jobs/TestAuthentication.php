<?php

namespace App\Jobs;

use App\Models\Scan;
use App\Models\ScanLog;
use App\Services\Zap\ZapService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class TestAuthentication implements ShouldQueue
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
        return 300;
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Scan $scan
    ) {}

    /**
     * Execute the job on the queue_assessment worker.
     */
    public function handle(ZapService $zapService): void
    {
        ScanLog::create([
            'scan_id' => $this->scan->id,
            'level' => 'info',
            'phase' => 'zap_auth_test_started',
            'message' => 'ZAP Authentication Verification Test job started on queue worker.',
        ]);

        $zapService->testAuthentication($this->scan);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $message = $exception ? $exception->getMessage() : 'Authentication verification could not be executed by the assessment worker.';

        ScanLog::create([
            'scan_id' => $this->scan->id,
            'level' => 'error',
            'phase' => 'zap_auth_test_failed',
            'message' => 'ZAP Authentication Verification Test failed on worker: ' . $message,
        ]);
    }
}
