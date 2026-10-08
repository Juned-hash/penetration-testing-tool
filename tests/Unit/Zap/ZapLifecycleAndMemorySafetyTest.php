<?php

namespace Tests\Unit\Zap;

use App\Models\Scan;
use App\Models\User;
use App\Services\ScanService;
use App\Services\Zap\ZapAuthenticationDiagnosticsParser;
use App\Services\Zap\ZapConfigurationBuilder;
use App\Services\Zap\ZapResultParser;
use App\Services\Zap\ZapRunner;
use App\Services\Zap\ZapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ZapLifecycleAndMemorySafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_scan_id_cannot_start_two_simultaneous_or_repeated_zap_executions(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Single Execution Scan Test',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'running',
        ]);

        $mockRunner = $this->createMock(ZapRunner::class);
        $mockRunner->expects($this->never())->method('runAutomationFramework');

        $service = new ZapService(
            new ZapConfigurationBuilder(),
            $mockRunner,
            new ZapResultParser(),
            app(ScanService::class),
            new ZapAuthenticationDiagnosticsParser()
        );

        $result = $service->runAssessment($scan);
        $this->assertFalse($result);

        $preventedLog = $scan->logs()->where('phase', 'execution_prevented')->first();
        $this->assertNotNull($preventedLog);
        $this->assertStringContainsString('Cannot launch duplicate ZAP execution', $preventedLog->message);
    }

    public function test_active_execution_lock_prevents_duplicate_concurrent_zap_launches(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Lock Protection Scan Test',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'queued',
        ]);

        $lockKey = "scan_execution_{$scan->id}";
        $lock = Cache::lock($lockKey, 3600);
        $lock->get();

        $mockRunner = $this->createMock(ZapRunner::class);
        $mockRunner->expects($this->never())->method('runAutomationFramework');

        $service = new ZapService(
            new ZapConfigurationBuilder(),
            $mockRunner,
            new ZapResultParser(),
            app(ScanService::class),
            new ZapAuthenticationDiagnosticsParser()
        );

        $result = $service->runAssessment($scan);
        $this->assertFalse($result);

        $preventedLog = $scan->logs()->where('phase', 'execution_prevented')->first();
        $this->assertNotNull($preventedLog);
        $this->assertStringContainsString('Active ZAP execution lock is already held', $preventedLog->message);

        $lock->release();
    }

    public function test_post_processing_exception_marks_scan_failed_instead_of_retrying_job(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Post Processing Exception Test',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'queued',
        ]);

        $mockRunner = $this->createMock(ZapRunner::class);
        $mockRunner->method('isAvailable')->willReturn(true);
        $mockRunner->method('isDockerImageAvailable')->willReturn(true);
        $mockRunner->method('translateTargetUrlForDocker')->willReturn('https://target.example.com');
        $mockRunner->method('getVolumeName')->willReturn('zap_scan_' . $scan->id);
        $mockRunner->method('getContainerName')->willReturn('zap-scan-' . $scan->id);
        $mockRunner->method('runAutomationFramework')->willReturn([
            'success' => true,
            'exitCode' => 0,
            'output' => "ZAP execution completed",
            'error' => "",
            'timedOut' => false,
        ]);

        $mockParser = $this->createMock(ZapResultParser::class);
        $mockParser->method('parseAndStore')->willThrowException(new \Exception('Database write error during findings import'));

        $service = new ZapService(
            new ZapConfigurationBuilder(),
            $mockRunner,
            $mockParser,
            app(ScanService::class),
            new ZapAuthenticationDiagnosticsParser()
        );

        $result = $service->runAssessment($scan);

        $this->assertFalse($result);
        $this->assertEquals('failed', $scan->fresh()->status);
        $this->assertStringContainsString('Database write error during findings import', $scan->fresh()->failure_reason);
    }

    public function test_auth_report_summary_extraction_is_memory_safe(): void
    {
        $runner = new ZapRunner();

        $mockJsonBuffer = '{
            "summaryItems": [
                {"key": "auth.summary.auth", "passed": true},
                {"key": "auth.summary.session", "passed": true}
            ],
            "statistics": [
                {"key": "stats.auth.success", "value": 7},
                {"key": "stats.auth.state.loggedin", "value": 7},
                {"key": "stats.auth.failure", "value": 0}
            ],
            "events": [
                {"huge_log_payload": "repeated 1000000 times..."}
            ]
        }';

        $summary = $runner->extractSummaryFromBuffer($mockJsonBuffer);

        $this->assertNotNull($summary);
        $this->assertArrayHasKey('summaryItems', $summary);
        $this->assertArrayHasKey('statistics', $summary);
        $this->assertCount(2, $summary['summaryItems']);
        $this->assertCount(3, $summary['statistics']);

        $parser = new ZapAuthenticationDiagnosticsParser();
        $scan = new Scan();
        $scan->setRelation('authenticationConfiguration', new \App\Models\AuthenticationConfiguration(['mode' => 'form']));

        $diagnostics = $parser->parse(['output' => '', 'error' => ''], $scan, $summary);
        $this->assertEquals('success', $diagnostics['status']);
        $this->assertEquals('SUCCESS', $diagnostics['status_label']);
    }

    public function test_successful_assessment_leaves_no_persistent_auth_report_json(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Clean Persistent Storage Test',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'queued',
        ]);

        $workDir = str_replace('\\', '/', storage_path("app/zap/scan_{$scan->id}"));
        File::ensureDirectoryExists($workDir);
        $jsonReportPath = $workDir . '/report.json';
        File::put($jsonReportPath, json_encode(['site' => []]));
        $authReportPath = $workDir . '/auth-report.json';
        File::put($authReportPath, json_encode(['summaryItems' => []]));

        $mockRunner = $this->createMock(ZapRunner::class);
        $mockRunner->method('isAvailable')->willReturn(true);
        $mockRunner->method('isDockerImageAvailable')->willReturn(true);
        $mockRunner->method('translateTargetUrlForDocker')->willReturn('https://target.example.com');
        $mockRunner->method('toHostPath')->willReturnCallback(fn($p) => $p);
        $mockRunner->method('getVolumeName')->willReturn('zap_scan_' . $scan->id);
        $mockRunner->method('getContainerName')->willReturn('zap-scan-' . $scan->id);
        $mockRunner->method('runAutomationFramework')->willReturn([
            'success' => true,
            'exitCode' => 0,
            'output' => "ZAP execution finished",
            'error' => "",
            'timedOut' => false,
            'authReportSummary' => ['summaryItems' => [['key' => 'auth.summary.auth', 'passed' => true]]],
        ]);

        $service = new ZapService(
            new ZapConfigurationBuilder(),
            $mockRunner,
            new ZapResultParser(),
            app(ScanService::class),
            new ZapAuthenticationDiagnosticsParser()
        );

        $result = $service->runAssessment($scan);

        $this->assertTrue($result);
        $this->assertEquals('completed', $scan->fresh()->status);

        $this->assertFalse(File::exists($authReportPath));
        $this->assertFalse(File::exists($jsonReportPath));

        if (File::exists($workDir)) {
            File::deleteDirectory($workDir);
        }
    }

    public function test_summaryItems_and_statistics_located_after_1MB_can_be_extracted_memory_safely(): void
    {
        $runner = new ZapRunner();
        $tempPath = storage_path('app/temp_test_large_auth_report.json');

        // Build a mock file where first 1.5MB is filler events and summaryItems/statistics appear at 1.5MB offset
        $filler = str_repeat('{"event":"spider_request","url":"https://target.example.com/item/100"},' . "\n", 20000);
        $jsonContent = '{"siteReports": [' . $filler . '{"last":"event"}],"summaryItems": [{"key": "auth.summary.auth", "passed": true}],"statistics": [{"key": "stats.auth.success", "value": 1},{"key": "stats.auth.state.unknown", "value": 5}]}';

        File::put($tempPath, $jsonContent);

        $summary = $runner->extractAuthReportSummaryFromFile($tempPath);
        if (File::exists($tempPath)) {
            File::delete($tempPath);
        }

        $this->assertNotNull($summary);
        $this->assertArrayHasKey('summaryItems', $summary);
        $this->assertArrayHasKey('statistics', $summary);
        $this->assertTrue($summary['summaryItems'][0]['passed']);
        $this->assertEquals(1, $summary['statistics'][0]['value']);

        $parser = new ZapAuthenticationDiagnosticsParser();
        $scan = new Scan();
        $scan->setRelation('authenticationConfiguration', new \App\Models\AuthenticationConfiguration(['mode' => 'form']));

        $diagnostics = $parser->parse(['output' => '', 'error' => ''], $scan, $summary);
        $this->assertEquals('success', $diagnostics['status']);
        $this->assertEquals('SUCCESS', $diagnostics['status_label']);
    }

    public function test_summaryItems_extraction_ignores_preceding_log_string_matches(): void
    {
        $runner = new ZapRunner();
        $tempPath = storage_path('app/temp_test_log_string_preceding_auth_report.json');

        $jsonContent = '{
            "logFile": [
                {"message": "Job report set template = auth-report-json"},
                {"message": "Job report set reportTitle = OWASP ZAP summaryItems test"}
            ],
            "summaryItems": [
                {"key": "auth.summary.auth", "passed": true},
                {"key": "auth.summary.session", "passed": true}
            ],
            "statistics": [
                {"key": "stats.auth.success", "value": 3}
            ]
        }';

        File::put($tempPath, $jsonContent);

        $summary = $runner->extractAuthReportSummaryFromFile($tempPath);
        if (File::exists($tempPath)) {
            File::delete($tempPath);
        }

        $this->assertNotNull($summary);
        $this->assertArrayHasKey('summaryItems', $summary);
        $this->assertArrayHasKey('statistics', $summary);
        $this->assertTrue($summary['summaryItems'][0]['passed']);
        $this->assertEquals(3, $summary['statistics'][0]['value']);
    }
}
