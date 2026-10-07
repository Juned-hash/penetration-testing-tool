<?php

namespace Tests\Unit\Zap;

use App\Models\Scan;
use App\Models\User;
use App\Services\Zap\ZapConfigurationBuilder;
use App\Services\Zap\ZapResultParser;
use App\Services\Zap\ZapRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ZapDockerRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_docker_configuration_loading(): void
    {
        Config::set('zap.docker_image', 'ghcr.io/zaproxy/zaproxy:stable');
        Config::set('zap.docker_binary', 'docker');
        Config::set('zap.timeout', 3600);
        Config::set('zap.active_scan_max_duration', 20);

        $this->assertEquals('ghcr.io/zaproxy/zaproxy:stable', config('zap.docker_image'));
        $this->assertEquals('docker', config('zap.docker_binary'));
        $this->assertEquals(3600, config('zap.timeout'));
        $this->assertEquals(20, config('zap.active_scan_max_duration'));
    }

    public function test_localhost_target_translation(): void
    {
        $runner = new ZapRunner();

        $this->assertEquals(
            'http://host.docker.internal:4200',
            $runner->translateTargetUrlForDocker('http://127.0.0.1:4200')
        );

        $this->assertEquals(
            'http://host.docker.internal:4200/api/v1',
            $runner->translateTargetUrlForDocker('http://localhost:4200/api/v1')
        );

        $this->assertEquals(
            'https://app.example.com',
            $runner->translateTargetUrlForDocker('https://app.example.com')
        );
    }

    public function test_named_volume_and_container_name_generation_per_scan(): void
    {
        $runner = new ZapRunner();

        $vol1 = $runner->getVolumeName(101);
        $vol2 = $runner->getVolumeName(102);

        $this->assertEquals('zap_scan_101', $vol1);
        $this->assertEquals('zap_scan_102', $vol2);
        $this->assertNotEquals($vol1, $vol2);

        $container1 = $runner->getContainerName(101);
        $container2 = $runner->getContainerName(102);

        $this->assertEquals('zap-scan-101', $container1);
        $this->assertEquals('zap-scan-102', $container2);
        $this->assertNotEquals($container1, $container2);
    }

    public function test_docker_volume_creation_command(): void
    {
        $runner = new ZapRunner();
        $cmd = $runner->buildVolumeCreateCommand('zap_scan_101');

        $this->assertContains('volume', $cmd);
        $this->assertContains('create', $cmd);
        $this->assertContains('zap_scan_101', $cmd);
    }

    public function test_volume_initialization_command_uses_root_chown_1000(): void
    {
        $runner = new ZapRunner();
        $cmd = $runner->buildVolumeInitCommand('zap_scan_101', 'ghcr.io/zaproxy/zaproxy:stable');

        $this->assertContains('run', $cmd);
        $this->assertContains('--rm', $cmd);
        $this->assertContains('--user', $cmd);
        $this->assertContains('root', $cmd);
        $this->assertContains('-v', $cmd);
        $this->assertContains('zap_scan_101:/zap/wrk', $cmd);
        $this->assertContains('chown -R 1000:1000 /zap/wrk', $cmd);
    }

    public function test_zap_container_creation_command_uses_non_root_zap_user_and_named_volume(): void
    {
        Config::set('zap.docker_user', 'zap');
        $runner = new ZapRunner();
        $cmd = $runner->buildCreateContainerCommand('zap_scan_101', 'zap-scan-101', 'assessment.yaml', 'ghcr.io/zaproxy/zaproxy:stable', false);

        $this->assertContains('create', $cmd);
        $this->assertContains('--name', $cmd);
        $this->assertContains('zap-scan-101', $cmd);
        $this->assertContains('--user', $cmd);
        $this->assertContains('zap', $cmd);
        $this->assertContains('-v', $cmd);
        $this->assertContains('zap_scan_101:/zap/wrk', $cmd);

        // Container MUST NOT be created with --rm so artifacts can be copied out after completion
        $this->assertNotContains('--rm', $cmd);

        // Host Windows filesystem path MUST NOT be mounted as /zap/wrk
        $hostPathString = storage_path('app/zap/scan_101');
        $this->assertNotContains("{$hostPathString}:/zap/wrk", $cmd);
        $this->assertNotContains("{$hostPathString}:/zap/wrk:rw", $cmd);
    }

    public function test_assessment_yaml_copy_command(): void
    {
        $runner = new ZapRunner();
        $cmd = $runner->buildCopyYamlCommand('zap-scan-101', '/host/storage/app/zap/scan_101/assessment.yaml');

        $this->assertContains('cp', $cmd);
        $this->assertContains('/host/storage/app/zap/scan_101/assessment.yaml', $cmd);
        $this->assertContains('zap-scan-101:/zap/wrk/assessment.yaml', $cmd);
    }

    public function test_export_artifacts_command(): void
    {
        $runner = new ZapRunner();
        $cmd = $runner->buildExportArtifactsCommand('zap-scan-101', '/host/storage/app/zap/scan_101');

        $this->assertContains('cp', $cmd);
        $this->assertContains('zap-scan-101:/zap/wrk/.', $cmd);
        $this->assertContains('/host/storage/app/zap/scan_101/', $cmd);
    }

    public function test_queue_timeout_hierarchy_configuration(): void
    {
        $zapTimeout = config('zap.timeout', 3600);
        $redisRetryAfter = config('queue.connections.redis.retry_after', 4200);

        $this->assertGreaterThanOrEqual(3600, $zapTimeout);
        $this->assertGreaterThan($zapTimeout, $redisRetryAfter);
    }

    public function test_working_directory_creation_and_yaml_generation(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Docker YAML Test Scan',
            'target_url' => 'http://127.0.0.1:4200',
            'environment' => 'development',
            'status' => 'draft',
        ]);

        $runner = new ZapRunner();
        $translatedTarget = $runner->translateTargetUrlForDocker($scan->target_url);

        $workDir = storage_path('app/zap/test_scan_' . $scan->id);
        File::ensureDirectoryExists($workDir);

        $builder = new ZapConfigurationBuilder();
        $yamlContent = $builder->buildYaml($scan, '/zap/wrk', 'report.json', $translatedTarget);

        $yamlPath = $workDir . '/assessment.yaml';
        File::put($yamlPath, $yamlContent);

        $this->assertTrue(File::exists($yamlPath));
        $this->assertStringContainsString('http://host.docker.internal:4200', $yamlContent);
        $this->assertStringContainsString('reportDir: /zap/wrk', $yamlContent);

        File::deleteDirectory($workDir);
    }

    public function test_result_file_handling(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Docker Result File Test',
            'target_url' => 'http://127.0.0.1:4200',
            'environment' => 'development',
            'status' => 'processing_results',
        ]);

        $mockJson = [
            'site' => [
                [
                    '@name' => 'http://host.docker.internal:4200',
                    'alerts' => [
                        [
                            'pluginid' => '10021',
                            'alert' => 'X-Content-Type-Options Header Missing',
                            'riskcode' => '1',
                            'confidence' => '2',
                            'riskdesc' => 'Low (Medium)',
                            'desc' => 'MIME sniffing header missing',
                            'instances' => [
                                [
                                    'uri' => 'http://host.docker.internal:4200/index.html',
                                    'method' => 'GET',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $reportPath = storage_path('app/test_docker_report.json');
        File::put($reportPath, json_encode($mockJson));

        $parser = new ZapResultParser();
        $count = $parser->parseAndStore($scan, $reportPath);

        File::delete($reportPath);

        $this->assertEquals(1, $count);
        $finding = $scan->findings()->first();
        $this->assertNotNull($finding);
        $this->assertEquals('http://127.0.0.1:4200/index.html', $finding->url);
    }

    public function test_failed_zap_execution_retains_temporary_directory_and_logs_sanitized_failure_details(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Failed Execution Test Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'draft',
        ]);

        $mockRunner = $this->createMock(ZapRunner::class);
        $mockRunner->method('isAvailable')->willReturn(true);
        $mockRunner->method('isDockerImageAvailable')->willReturn(true);
        $mockRunner->method('translateTargetUrlForDocker')->willReturn('https://target.example.com');
        $mockRunner->method('toHostPath')->willReturnCallback(fn($p) => $p);
        $mockRunner->method('getVolumeName')->willReturn('zap_scan_' . $scan->id);
        $mockRunner->method('getContainerName')->willReturn('zap-scan-' . $scan->id);
        $mockRunner->method('runAutomationFramework')->willReturn([
            'success' => false,
            'exitCode' => 1,
            'output' => "FATAL ERROR: Failed to connect to host\nCookie: JSESSIONID=secret123\npassword=super-secret-password-99",
            'error' => "Process crashed unexpectedly\nAuthorization: Bearer my-secret-jwt-token",
            'timedOut' => false,
        ]);

        $mockBuilder = new ZapConfigurationBuilder();
        $mockParser = new ZapResultParser();
        $mockScanService = app(\App\Services\ScanService::class);
        $mockAuthParser = new \App\Services\Zap\ZapAuthenticationDiagnosticsParser();

        $service = new \App\Services\Zap\ZapService(
            $mockBuilder,
            $mockRunner,
            $mockParser,
            $mockScanService,
            $mockAuthParser
        );

        $result = $service->runAssessment($scan);

        $this->assertFalse($result);
        $this->assertEquals('failed', $scan->fresh()->status);

        $workDir = str_replace('\\', '/', storage_path("app/zap/scan_{$scan->id}"));
        $this->assertTrue(File::exists($workDir));

        $retainedLog = $scan->logs()->where('phase', 'zap_debug_artifacts')->first();
        $this->assertNotNull($retainedLog);
        $this->assertStringContainsString('ZAP_DEBUG_ARTIFACTS_RETAINED', $retainedLog->message);
        $this->assertStringContainsString($workDir, $retainedLog->message);

        $failureLog = $scan->logs()->where('phase', 'zap_execution_failure')->first();
        $this->assertNotNull($failureLog);
        $this->assertStringContainsString('ZAP_EXECUTION_FAILURE Exit Code: 1', $failureLog->message);
        $this->assertStringNotContainsString('super-secret-password-99', $failureLog->message);
        $this->assertStringNotContainsString('secret123', $failureLog->message);
        $this->assertStringNotContainsString('my-secret-jwt-token', $failureLog->message);
        $this->assertStringContainsString('[REDACTED]', $failureLog->message);

        File::deleteDirectory($workDir);
    }

    public function test_successful_zap_execution_cleans_up_temporary_files(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Success Execution Cleanup Test Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'draft',
        ]);

        $workDir = str_replace('\\', '/', storage_path("app/zap/scan_{$scan->id}"));
        File::ensureDirectoryExists($workDir);
        $jsonReportPath = $workDir . '/report.json';
        File::put($jsonReportPath, json_encode(['site' => []]));

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
            'output' => "ZAP execution completed successfully.",
            'error' => "",
            'timedOut' => false,
        ]);

        $mockBuilder = new ZapConfigurationBuilder();
        $mockParser = new ZapResultParser();
        $mockScanService = app(\App\Services\ScanService::class);
        $mockAuthParser = new \App\Services\Zap\ZapAuthenticationDiagnosticsParser();

        $service = new \App\Services\Zap\ZapService(
            $mockBuilder,
            $mockRunner,
            $mockParser,
            $mockScanService,
            $mockAuthParser
        );

        $result = $service->runAssessment($scan);

        $this->assertTrue($result);
        $this->assertEquals('completed', $scan->fresh()->status);

        $this->assertFalse(File::exists($workDir . '/assessment.yaml'));
        $this->assertFalse(File::exists($jsonReportPath));

        if (File::exists($workDir)) {
            File::deleteDirectory($workDir);
        }
    }
}
