<?php

namespace Tests\Unit;

use App\Models\AuthenticationConfiguration;
use App\Models\Finding;
use App\Models\Report;
use App\Models\Scan;
use App\Models\ScanConfiguration;
use App\Models\ScanLog;
use App\Models\ScanScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_belongs_to_user_and_has_related_models(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Staging Portal Assessment',
            'target_url' => 'https://staging.example.com',
            'environment' => 'staging',
            'status' => 'draft',
            'authorization_confirmed_at' => now(),
        ]);

        $this->assertTrue($user->scans->contains($scan));
        $this->assertEquals($user->id, $scan->user->id);

        $config = ScanConfiguration::create([
            'scan_id' => $scan->id,
            'spider_enabled' => true,
            'ajax_spider_enabled' => false,
            'passive_scan_enabled' => true,
            'active_scan_enabled' => true,
            'authentication_enabled' => true,
            'options' => ['depth' => 5],
        ]);

        $this->assertEquals($config->id, $scan->scanConfiguration->id);

        $scopeIn = ScanScope::create([
            'scan_id' => $scan->id,
            'type' => 'include',
            'path' => 'https://staging.example.com/*',
        ]);

        $scopeEx = ScanScope::create([
            'scan_id' => $scan->id,
            'type' => 'exclude',
            'path' => 'https://staging.example.com/logout',
        ]);

        $this->assertCount(2, $scan->scanScopes);

        $authConfig = AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'form',
            'login_url' => 'https://staging.example.com/login',
            'username_field' => 'email',
            'password_field' => 'password',
            'username' => 'tester@example.com',
            'password' => 'secret_password_123',
            'token_name' => 'api_token',
            'token_value' => 'secret_token_abc',
        ]);

        $this->assertEquals($authConfig->id, $scan->authenticationConfiguration->id);
        // Verify decryption transparently works via model casting
        $this->assertEquals('secret_password_123', $scan->authenticationConfiguration->password);
        $this->assertEquals('secret_token_abc', $scan->authenticationConfiguration->token_value);

        $finding = Finding::create([
            'scan_id' => $scan->id,
            'source' => 'ZAP',
            'external_id' => '1001',
            'name' => 'Missing Anti-clickjacking Header',
            'risk' => 'Medium',
            'confidence' => 'High',
            'severity' => 'Medium',
            'url' => 'https://staging.example.com/',
            'method' => 'GET',
            'description' => 'X-Frame-Options header missing',
            'status' => 'Open',
        ]);

        $this->assertCount(1, $scan->findings);

        $report = Report::create([
            'scan_id' => $scan->id,
            'type' => 'pdf',
            'file_path' => 'reports/assessment_1.pdf',
            'status' => 'completed',
            'generated_at' => now(),
        ]);

        $this->assertCount(1, $scan->reports);

        $log = ScanLog::create([
            'scan_id' => $scan->id,
            'level' => 'info',
            'phase' => 'starting',
            'message' => 'Assessment initialized',
        ]);

        $this->assertCount(1, $scan->logs);
    }
}
