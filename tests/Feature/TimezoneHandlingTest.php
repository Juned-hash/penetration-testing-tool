<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\Report;
use App\Models\Scan;
use App\Models\ScanLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TimezoneHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_timezone_is_configured_to_asia_kolkata(): void
    {
        $this->assertEquals('Asia/Kolkata', config('app.timezone'));
        $this->assertEquals('Asia/Kolkata', date_default_timezone_get());
        $this->assertEquals('Asia/Kolkata', now()->getTimezone()->getName());
    }

    public function test_now_evaluates_in_ist_offset(): void
    {
        $now = now();
        $this->assertEquals('+05:30', $now->format('P'));
    }

    public function test_scan_timestamps_are_cast_and_formatted_in_ist(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'IST Test Scan',
            'target_url' => 'https://ist.example.com',
            'environment' => 'staging',
            'status' => 'completed',
            'authorization_confirmed_at' => now(),
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
        ]);

        $freshScan = $scan->fresh();

        $this->assertEquals('Asia/Kolkata', $freshScan->created_at->getTimezone()->getName());
        $this->assertEquals('Asia/Kolkata', $freshScan->started_at->getTimezone()->getName());
        $this->assertEquals('Asia/Kolkata', $freshScan->completed_at->getTimezone()->getName());
        $this->assertEquals('Asia/Kolkata', $freshScan->authorization_confirmed_at->getTimezone()->getName());

        $this->assertStringContainsString('+05:30', $freshScan->created_at->toIso8601String());
    }

    public function test_scan_log_timestamps_are_formatted_in_ist(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Scan Log IST Test',
            'target_url' => 'https://ist.example.com',
            'environment' => 'staging',
            'status' => 'running',
        ]);

        $log = ScanLog::create([
            'scan_id' => $scan->id,
            'level' => 'info',
            'phase' => 'scanning',
            'message' => 'Assessment phase running',
        ]);

        $freshLog = $log->fresh();

        $this->assertEquals('Asia/Kolkata', $freshLog->created_at->getTimezone()->getName());
        $this->assertEquals('+05:30', $freshLog->created_at->format('P'));
    }

    public function test_report_timestamps_are_formatted_in_ist(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Report IST Test',
            'target_url' => 'https://ist.example.com',
            'environment' => 'staging',
            'status' => 'completed',
        ]);

        $report = Report::create([
            'scan_id' => $scan->id,
            'type' => 'pdf',
            'status' => 'completed',
            'started_at' => now()->subSeconds(30),
            'completed_at' => now(),
            'generated_at' => now(),
        ]);

        $freshReport = $report->fresh();

        $this->assertEquals('Asia/Kolkata', $freshReport->started_at->getTimezone()->getName());
        $this->assertEquals('Asia/Kolkata', $freshReport->completed_at->getTimezone()->getName());
        $this->assertEquals('Asia/Kolkata', $freshReport->generated_at->getTimezone()->getName());

        $this->assertEquals('+05:30', $freshReport->completed_at->format('P'));
    }

    public function test_pdf_report_view_renders_ist_timestamps(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'PDF View Timezone Test',
            'target_url' => 'https://ist.example.com',
            'environment' => 'staging',
            'status' => 'completed',
            'authorization_confirmed_at' => now(),
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);

        Finding::create([
            'scan_id' => $scan->id,
            'source' => 'owasp_zap',
            'name' => 'Test Vulnerability',
            'severity' => 'high',
            'risk' => 'High',
            'confidence' => 'High',
            'url' => 'https://ist.example.com/api',
            'description' => 'Test vulnerability description',
        ]);

        $renderedHtml = view('reports.pdf', ['scan' => $scan])->render();

        $this->assertStringContainsString('Creation Date', $renderedHtml);
        $this->assertStringContainsString('Started Date', $renderedHtml);
        $this->assertStringContainsString('Completion Date', $renderedHtml);
        $this->assertStringContainsString('Explicit Authorization Date', $renderedHtml);
        $this->assertStringContainsString('IST', $renderedHtml);
    }
}
