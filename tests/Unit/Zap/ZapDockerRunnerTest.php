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

        // Verify non-localhost URLs are not modified
        $this->assertEquals(
            'https://app.example.com',
            $runner->translateTargetUrlForDocker('https://app.example.com')
        );
    }

    public function test_docker_command_construction(): void
    {
        Config::set('zap.docker_user', 'root');
        $runner = new ZapRunner();
        $workDir = storage_path('app/zap/scan_99');
        $normalizedWorkDir = $runner->toHostPath($workDir);

        $command = $runner->buildDockerCommand($workDir, 'assessment.yaml', 'ghcr.io/zaproxy/zaproxy:stable', false, 'pentest-zap-scan-99');

        $this->assertContains('run', $command);
        $this->assertContains('--rm', $command);
        $this->assertContains('--user', $command);
        $this->assertContains('root', $command);
        $this->assertContains('--name', $command);
        $this->assertContains('pentest-zap-scan-99', $command);
        $this->assertContains("-v", $command);
        $this->assertContains("{$normalizedWorkDir}:/zap/wrk:rw", $command);
        $this->assertContains('ghcr.io/zaproxy/zaproxy:stable', $command);
        $this->assertContains('zap.sh', $command);
        $this->assertContains('-autorun', $command);
        $this->assertContains('/zap/wrk/assessment.yaml', $command);
    }

    public function test_unique_container_names_for_concurrent_scans(): void
    {
        $runner = new ZapRunner();
        $cmd1 = $runner->buildDockerCommand('/var/www/html/storage/app/zap/scan_101', 'assessment.yaml', null, false, 'pentest-zap-scan-101');
        $cmd2 = $runner->buildDockerCommand('/var/www/html/storage/app/zap/scan_102', 'assessment.yaml', null, false, 'pentest-zap-scan-102');

        $this->assertContains('pentest-zap-scan-101', $cmd1);
        $this->assertContains('pentest-zap-scan-102', $cmd2);
        $this->assertNotEquals($cmd1, $cmd2);
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
        // Verify host.docker.internal was normalized back to original user target host
        $this->assertEquals('http://127.0.0.1:4200/index.html', $finding->url);
    }

    public function test_timeout_exception_handling(): void
    {
        Config::set('zap.timeout', 1);

        $workDir = storage_path('app/zap/scan_timeout_test');
        File::ensureDirectoryExists($workDir);
        $yamlPath = $workDir . '/assessment.yaml';
        File::put($yamlPath, 'env: {}');

        $runner = new ZapRunner();
        $result = $runner->runAutomationFramework($yamlPath, $workDir, false, 'pentest-zap-scan-timeout-test');

        File::deleteDirectory($workDir);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('timedOut', $result);
    }
}
