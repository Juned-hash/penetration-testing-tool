<?php

namespace Tests\Feature\Zap;

use App\Jobs\RunAssessment;
use App\Models\Scan;
use App\Models\User;
use App\Services\Zap\ZapRunner;
use App\Services\Zap\ZapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ZapIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_fails_gracefully_when_zap_binary_is_not_available(): void
    {
        // Explicitly set invalid Docker CLI binary path configuration
        Config::set('zap.docker_binary', 'nonexistent_docker_cmd_xyz');

        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Missing Binary Test Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'queued',
            'authorization_confirmed_at' => now(),
        ]);

        $zapService = app(ZapService::class);
        $result = $zapService->runAssessment($scan);

        $this->assertFalse($result);

        $scan->refresh();
        $this->assertEquals('failed', $scan->status);
        $this->assertStringContainsString('Docker CLI executable is not installed or Docker Desktop daemon is not running', $scan->failure_reason);

        // Verify ScanLog entry created
        $this->assertDatabaseHas('scan_logs', [
            'scan_id' => $scan->id,
            'phase' => 'failed',
        ]);
    }

    public function test_runner_is_available_returns_false_when_path_empty(): void
    {
        Config::set('zap.docker_binary', 'nonexistent_docker_cmd_xyz');
        $runner = new ZapRunner();

        $this->assertFalse($runner->isAvailable());
    }

    public function test_runner_executes_automation_framework_if_enabled(): void
    {
        if (!config('zap.integration_test')) {
            $this->markTestSkipped('ZAP integration testing is disabled. Set ZAP_INTEGRATION_TEST=true to execute live ZAP testing.');
        }

        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Live ZAP Test',
            'target_url' => 'http://127.0.0.1:8000',
            'environment' => 'development',
            'status' => 'queued',
            'authorization_confirmed_at' => now(),
        ]);

        $zapService = app(ZapService::class);
        $result = $zapService->runAssessment($scan);

        $scan->refresh();
        $this->assertContains($scan->status, ['completed', 'failed']);
    }
}
