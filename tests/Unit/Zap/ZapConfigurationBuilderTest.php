<?php

namespace Tests\Unit\Zap;

use App\Models\AuthenticationConfiguration;
use App\Models\Scan;
use App\Models\ScanConfiguration;
use App\Models\ScanScope;
use App\Models\User;
use App\Services\Zap\ZapConfigurationBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZapConfigurationBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_valid_zap_automation_framework_config_array(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Config Builder Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'draft',
        ]);

        ScanScope::create([
            'scan_id' => $scan->id,
            'type' => 'include',
            'path' => '/api/*',
        ]);

        ScanScope::create([
            'scan_id' => $scan->id,
            'type' => 'exclude',
            'path' => '/logout',
        ]);

        ScanConfiguration::create([
            'scan_id' => $scan->id,
            'spider_enabled' => true,
            'passive_scan_enabled' => true,
            'active_scan_enabled' => true,
        ]);

        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'form',
            'login_url' => 'https://target.example.com/login',
            'username_field' => 'email',
            'password_field' => 'pass',
            'username' => 'tester@example.com',
            'password' => 'Secret123!',
        ]);

        $builder = new ZapConfigurationBuilder();
        $array = $builder->buildArray($scan, '/tmp/reports', 'report.json');

        $this->assertArrayHasKey('env', $array);
        $this->assertArrayHasKey('jobs', $array);

        $context = $array['env']['contexts'][0];
        $this->assertEquals('Target Context', $context['name']);
        $this->assertContains('https://target.example.com', $context['urls']);
        $this->assertEquals('form', $context['authentication']['method']);
        $this->assertEquals('tester@example.com', $context['users'][0]['credentials']['username']);
        $this->assertEquals('Secret123!', $context['users'][0]['credentials']['password']);

        $jobTypes = array_column($array['jobs'], 'type');
        $this->assertContains('spider', $jobTypes);
        $this->assertContains('passiveScan-wait', $jobTypes);
        $this->assertContains('activeScan', $jobTypes);
        $this->assertContains('report', $jobTypes);
    }

    public function test_generates_valid_yaml_string(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'YAML Output Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'draft',
        ]);

        $builder = new ZapConfigurationBuilder();
        $yaml = $builder->buildYaml($scan, '/tmp/reports');

        $this->assertStringContainsString('env:', $yaml);
        $this->assertStringContainsString('contexts:', $yaml);
        $this->assertStringContainsString('Target Context', $yaml);
        $this->assertStringContainsString('traditional-json', $yaml);
    }
}
