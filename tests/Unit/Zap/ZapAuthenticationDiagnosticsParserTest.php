<?php

namespace Tests\Unit\Zap;

use App\Models\AuthenticationConfiguration;
use App\Models\Scan;
use App\Models\User;
use App\Services\Zap\ZapAuthenticationDiagnosticsParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZapAuthenticationDiagnosticsParserTest extends TestCase
{
    use RefreshDatabase;

    public function test_parses_successful_authentication_diagnostics(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Auth Success Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'running',
        ]);

        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'browser',
            'login_url' => 'https://target.example.com/login',
            'username' => 'test-user@example.com',
            'password' => 'secret-password-123',
        ]);

        $result = [
            'output' => "Job authentication started\nFound username field P101_USERNAME\nFound password field P101_PASSWORD\nAttempting login for AssessmentUser\nBrowser authentication succeeded\nUser 'AssessmentUser' logged in successfully\nLogged in indicator matched\nSession management identified",
            'error' => "",
        ];

        $parser = new ZapAuthenticationDiagnosticsParser();
        $parsed = $parser->parse($result, $scan);

        $this->assertEquals('success', $parsed['status']);
        $this->assertEquals('SUCCESS', $parsed['status_label']);
        $this->assertTrue($parsed['username_detected']);
        $this->assertEquals('IDENTIFIED', $parsed['username_field_status']);
        $this->assertTrue($parsed['password_detected']);
        $this->assertEquals('IDENTIFIED', $parsed['password_field_status']);
        $this->assertTrue($parsed['login_attempted']);
        $this->assertEquals('YES', $parsed['login_attempt_status']);
        $this->assertGreaterThanOrEqual(1, $parsed['successful_login_count']);
        $this->assertEquals(0, $parsed['failed_login_count']);
        $this->assertTrue($parsed['session_management_detected']);
        $this->assertTrue($parsed['verification_detected']);
        $this->assertStringContainsString('Status: SUCCESS', $parsed['log_message']);
    }

    public function test_parses_failed_authentication_diagnostics(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Auth Failed Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'running',
        ]);

        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'browser',
            'login_url' => 'https://target.example.com/login',
            'username' => 'test-user@example.com',
            'password' => 'secret-password-123',
        ]);

        $result = [
            'output' => "Job authentication started\nFound username field\nBrowser authentication failed\nAuthentication verification failed\nFailed to find secret input",
            'error' => "Logged out indicator matched",
        ];

        $parser = new ZapAuthenticationDiagnosticsParser();
        $parsed = $parser->parse($result, $scan);

        $this->assertEquals('failed', $parsed['status']);
        $this->assertEquals('FAILED', $parsed['status_label']);
        $this->assertTrue($parsed['username_detected']);
        $this->assertFalse($parsed['password_detected']);
        $this->assertGreaterThanOrEqual(1, $parsed['failed_login_count']);
        $this->assertNotEmpty($parsed['failure_reasons']);
        $this->assertStringContainsString('Status: FAILED', $parsed['log_message']);
    }

    public function test_parses_unknown_authentication_diagnostics_when_ambiguous(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Auth Unknown Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'running',
        ]);

        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'browser',
            'login_url' => 'https://target.example.com/login',
            'username' => 'test-user@example.com',
            'password' => 'secret-password-123',
        ]);

        $result = [
            'output' => "Starting spider job...\nScanning target URLs...",
            'error' => "",
        ];

        $parser = new ZapAuthenticationDiagnosticsParser();
        $parsed = $parser->parse($result, $scan);

        $this->assertEquals('unknown', $parsed['status']);
        $this->assertEquals('UNKNOWN', $parsed['status_label']);
        $this->assertEquals(0, $parsed['successful_login_count']);
        $this->assertEquals(0, $parsed['failed_login_count']);
        $this->assertStringContainsString('Status: UNKNOWN', $parsed['log_message']);
    }

    public function test_sanitizes_credentials_and_session_tokens_from_diagnostics_output(): void
    {
        $parser = new ZapAuthenticationDiagnosticsParser();

        $rawOutput = "POST /login HTTP/1.1\nHost: target.example.com\nCookie: JSESSIONID=abc123secret; apex_session=999888\n\nusername=test-user@example.com&password=secret-password-123&token=my-secret-token";

        $sanitized = $parser->sanitizeOutput($rawOutput);

        $this->assertStringNotContainsString('secret-password-123', $sanitized);
        $this->assertStringNotContainsString('abc123secret', $sanitized);
        $this->assertStringNotContainsString('999888', $sanitized);
        $this->assertStringNotContainsString('my-secret-token', $sanitized);
        $this->assertStringContainsString('[REDACTED]', $sanitized);
    }

    public function test_1_summary_items_auth_summary_auth_passed_true_results_in_success(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Test 1 Scan',
            'target_url' => 'https://soapbox.cloud/admins',
            'environment' => 'production',
            'status' => 'running',
        ]);
        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'form',
            'login_url' => 'https://soapbox.cloud/login',
            'username' => 'admin@soapbox.cloud',
            'password' => 'secret123',
        ]);

        $result = ['output' => '', 'error' => ''];
        $authReport = [
            'summaryItems' => [
                [
                    'description' => 'Authentication appeared to work',
                    'passed' => true,
                    'key' => 'auth.summary.auth',
                ],
            ],
            'statistics' => [],
        ];

        $parser = new ZapAuthenticationDiagnosticsParser();
        $parsed = $parser->parse($result, $scan, $authReport);

        $this->assertEquals('success', $parsed['status']);
        $this->assertEquals('SUCCESS', $parsed['status_label']);
        $this->assertEquals('Authentication appeared to work.', $parsed['message']);
    }

    public function test_2_statistics_stats_auth_success_results_in_success(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Test 2 Scan',
            'target_url' => 'https://soapbox.cloud/admins',
            'environment' => 'production',
            'status' => 'running',
        ]);
        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'form',
            'login_url' => 'https://soapbox.cloud/login',
            'username' => 'admin@soapbox.cloud',
            'password' => 'secret123',
        ]);

        $result = ['output' => '', 'error' => ''];
        $authReport = [
            'summaryItems' => [],
            'statistics' => [
                [
                    'key' => 'stats.auth.success',
                    'scope' => 'site',
                    'site' => 'https://soapbox.cloud/',
                    'value' => 1,
                ],
            ],
        ];

        $parser = new ZapAuthenticationDiagnosticsParser();
        $parsed = $parser->parse($result, $scan, $authReport);

        $this->assertEquals('success', $parsed['status']);
        $this->assertEquals('SUCCESS', $parsed['status_label']);
        $this->assertGreaterThanOrEqual(1, $parsed['successful_login_count']);
    }

    public function test_3_statistics_stats_auth_state_loggedin_results_in_success(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Test 3 Scan',
            'target_url' => 'https://soapbox.cloud/admins',
            'environment' => 'production',
            'status' => 'running',
        ]);
        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'form',
            'login_url' => 'https://soapbox.cloud/login',
            'username' => 'admin@soapbox.cloud',
            'password' => 'secret123',
        ]);

        $result = ['output' => '', 'error' => ''];
        $authReport = [
            'summaryItems' => [],
            'statistics' => [
                [
                    'key' => 'stats.auth.state.loggedin',
                    'scope' => 'site',
                    'site' => 'https://soapbox.cloud/',
                    'value' => 1,
                ],
            ],
        ];

        $parser = new ZapAuthenticationDiagnosticsParser();
        $parsed = $parser->parse($result, $scan, $authReport);

        $this->assertEquals('success', $parsed['status']);
        $this->assertEquals('SUCCESS', $parsed['status_label']);
    }

    public function test_4_stats_auth_state_unknown_with_success_results_in_success_not_unknown(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Test 4 Scan',
            'target_url' => 'https://soapbox.cloud/admins',
            'environment' => 'production',
            'status' => 'running',
        ]);
        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'form',
            'login_url' => 'https://soapbox.cloud/login',
            'username' => 'admin@soapbox.cloud',
            'password' => 'secret123',
        ]);

        $result = ['output' => '', 'error' => ''];
        $authReport = [
            'summaryItems' => [
                [
                    'description' => 'Authentication appeared to work',
                    'passed' => true,
                    'key' => 'auth.summary.auth',
                ],
            ],
            'statistics' => [
                [
                    'key' => 'stats.auth.state.unknown',
                    'scope' => 'site',
                    'site' => 'https://soapbox.cloud/',
                    'value' => 2,
                ],
                [
                    'key' => 'stats.auth.success',
                    'scope' => 'site',
                    'site' => 'https://soapbox.cloud/',
                    'value' => 1,
                ],
            ],
        ];

        $parser = new ZapAuthenticationDiagnosticsParser();
        $parsed = $parser->parse($result, $scan, $authReport);

        $this->assertEquals('success', $parsed['status']);
        $this->assertEquals('SUCCESS', $parsed['status_label']);
    }

    public function test_5_explicit_failure_without_success_evidence_results_in_failed(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Test 5 Scan',
            'target_url' => 'https://soapbox.cloud/admins',
            'environment' => 'production',
            'status' => 'running',
        ]);
        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'form',
            'login_url' => 'https://soapbox.cloud/login',
            'username' => 'admin@soapbox.cloud',
            'password' => 'secret123',
        ]);

        $result = ['output' => '', 'error' => ''];
        $authReport = [
            'summaryItems' => [
                [
                    'description' => 'Authentication failed',
                    'passed' => false,
                    'key' => 'auth.summary.auth',
                ],
            ],
            'statistics' => [
                [
                    'key' => 'stats.auth.failure',
                    'value' => 1,
                ],
            ],
        ];

        $parser = new ZapAuthenticationDiagnosticsParser();
        $parsed = $parser->parse($result, $scan, $authReport);

        $this->assertEquals('failed', $parsed['status']);
        $this->assertEquals('FAILED', $parsed['status_label']);
    }

    public function test_6_no_success_or_failure_evidence_results_in_unknown(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Test 6 Scan',
            'target_url' => 'https://soapbox.cloud/admins',
            'environment' => 'production',
            'status' => 'running',
        ]);
        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'form',
            'login_url' => 'https://soapbox.cloud/login',
            'username' => 'admin@soapbox.cloud',
            'password' => 'secret123',
        ]);

        $result = ['output' => 'Scanning page...', 'error' => ''];
        $authReport = [
            'summaryItems' => [],
            'statistics' => [],
        ];

        $parser = new ZapAuthenticationDiagnosticsParser();
        $parsed = $parser->parse($result, $scan, $authReport);

        $this->assertEquals('unknown', $parsed['status']);
        $this->assertEquals('UNKNOWN', $parsed['status_label']);
    }

    public function test_7_auth_summary_session_passed_true_identifies_session(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Test 7 Scan',
            'target_url' => 'https://soapbox.cloud/admins',
            'environment' => 'production',
            'status' => 'running',
        ]);
        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'form',
            'login_url' => 'https://soapbox.cloud/login',
            'username' => 'admin@soapbox.cloud',
            'password' => 'secret123',
        ]);

        $result = ['output' => '', 'error' => ''];
        $authReport = [
            'summaryItems' => [
                ['key' => 'auth.summary.auth', 'passed' => true],
                ['key' => 'auth.summary.session', 'passed' => true],
            ],
            'statistics' => [],
        ];

        $parser = new ZapAuthenticationDiagnosticsParser();
        $parsed = $parser->parse($result, $scan, $authReport);

        $this->assertTrue($parsed['session_management_detected']);
        $this->assertEquals('IDENTIFIED', $parsed['session_management_status']);
    }

    public function test_8_auth_summary_verif_passed_true_identifies_verification(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Test 8 Scan',
            'target_url' => 'https://soapbox.cloud/admins',
            'environment' => 'production',
            'status' => 'running',
        ]);
        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'form',
            'login_url' => 'https://soapbox.cloud/login',
            'username' => 'admin@soapbox.cloud',
            'password' => 'secret123',
        ]);

        $result = ['output' => '', 'error' => ''];
        $authReport = [
            'summaryItems' => [
                ['key' => 'auth.summary.auth', 'passed' => true],
                ['key' => 'auth.summary.verif', 'passed' => true],
            ],
            'statistics' => [],
        ];

        $parser = new ZapAuthenticationDiagnosticsParser();
        $parsed = $parser->parse($result, $scan, $authReport);

        $this->assertTrue($parsed['verification_detected']);
        $this->assertEquals('IDENTIFIED', $parsed['verification_status']);
    }

    public function test_9_missing_auth_report_falls_back_to_stdout_stderr_parser(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Test 9 Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'running',
        ]);
        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'form',
            'login_url' => 'https://target.example.com/login',
            'username' => 'user@example.com',
            'password' => 'secret123',
        ]);

        $result = [
            'output' => "User 'AssessmentUser' logged in successfully\nFound username field\nFound password field",
            'error' => '',
        ];

        $parser = new ZapAuthenticationDiagnosticsParser();
        $parsed = $parser->parse($result, $scan, null);

        $this->assertEquals('success', $parsed['status']);
        $this->assertEquals('SUCCESS', $parsed['status_label']);
    }

    public function test_10_malformed_auth_report_does_not_crash_assessment(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Test 10 Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'running',
        ]);
        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'form',
            'login_url' => 'https://target.example.com/login',
            'username' => 'user@example.com',
            'password' => 'secret123',
        ]);

        $result = [
            'output' => "Scanning...",
            'error' => '',
        ];
        $malformedAuthReport = ['invalid_structure' => true];

        $parser = new ZapAuthenticationDiagnosticsParser();
        $parsed = $parser->parse($result, $scan, $malformedAuthReport);

        $this->assertIsArray($parsed);
        $this->assertEquals('unknown', $parsed['status']);
    }
}
