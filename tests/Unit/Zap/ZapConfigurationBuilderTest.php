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

    public function test_browser_authentication_configuration_contains_diagnostic_parameters(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Browser Auth Scan',
            'target_url' => 'https://10.100.0.5:8443/ords/r/intg001/spbx-app-inc/dashboard',
            'environment' => 'staging',
            'status' => 'draft',
        ]);

        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'browser',
            'login_url' => 'https://10.100.0.5:8443/ords/r/intg001/soapboxcloud_landing_page/login',
            'username' => 'test-user@example.com',
            'password' => 'test-password',
            'authenticated_url' => 'https://10.100.0.5:8443/ords/r/intg001/soapboxcloud_landing_page/home',
        ]);

        $builder = new ZapConfigurationBuilder();
        $array = $builder->buildArray($scan, '/tmp/reports');

        $context = $array['env']['contexts'][0];
        $auth = $context['authentication'];

        $this->assertEquals('browser', $auth['method']);
        $this->assertEquals('https://10.100.0.5:8443/ords/r/intg001/soapboxcloud_landing_page/login', $auth['parameters']['loginPageUrl']);
        $this->assertEquals(10, $auth['parameters']['loginPageWait']);
        $this->assertEquals(2, $auth['parameters']['stepDelay']);
        $this->assertEquals('firefox-headless', $auth['parameters']['browserId']);
        $this->assertTrue($auth['parameters']['diagnostics']);

        $this->assertEquals([
            'method' => 'response',
            'loggedInRegex' => '(?i)My Incidents',
            'loggedOutRegex' => '(?i)Sign In',
        ], $auth['verification']);
        $this->assertEquals(['method' => 'autodetect'], $context['sessionManagement']);

        // Assert explicit authentication steps
        $this->assertArrayHasKey('steps', $auth['parameters']);
        $this->assertCount(4, $auth['parameters']['steps']);
        $this->assertEquals('WAIT', $auth['parameters']['steps'][0]['type']);
        $this->assertEquals(10000, $auth['parameters']['steps'][0]['timeout']);

        $this->assertEquals('USERNAME', $auth['parameters']['steps'][1]['type']);
        $this->assertEquals('#P9999_USERNAME', $auth['parameters']['steps'][1]['cssSelector']);

        $this->assertEquals('PASSWORD', $auth['parameters']['steps'][2]['type']);
        $this->assertEquals('#P9999_PASSWORD', $auth['parameters']['steps'][2]['cssSelector']);

        $this->assertEquals('CLICK', $auth['parameters']['steps'][3]['type']);
        $this->assertEquals('#B12056144829423636247', $auth['parameters']['steps'][3]['cssSelector']);

        // Check real credentials are present for ZAP execution
        $this->assertEquals('test-user@example.com', $context['users'][0]['credentials']['username']);
        $this->assertEquals('test-password', $context['users'][0]['credentials']['password']);

        // Test YAML output contains parameters and real credentials for ZAP execution
        $yaml = $builder->buildYaml($scan, '/tmp/reports');
        $this->assertStringContainsString('loginPageUrl:', $yaml);
        $this->assertStringContainsString('loginPageWait: 10', $yaml);
        $this->assertStringContainsString('stepDelay: 2', $yaml);
        $this->assertStringContainsString('browserId:', $yaml);
        $this->assertStringContainsString('firefox-headless', $yaml);
        $this->assertStringContainsString('diagnostics: true', $yaml);
        $this->assertStringContainsString('steps:', $yaml);
        $this->assertStringContainsString('WAIT', $yaml);
        $this->assertStringContainsString('10000', $yaml);
        $this->assertStringContainsString('USERNAME', $yaml);
        $this->assertStringContainsString('#P9999_USERNAME', $yaml);
        $this->assertStringContainsString('PASSWORD', $yaml);
        $this->assertStringContainsString('#P9999_PASSWORD', $yaml);
        $this->assertStringContainsString('CLICK', $yaml);
        $this->assertStringContainsString('#B12056144829423636247', $yaml);
        $this->assertStringContainsString('test-user@example.com', $yaml);
        $this->assertStringContainsString('test-password', $yaml);
    }

    public function test_sanitizes_yaml_credentials_for_safe_logging(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Sanitization Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'draft',
        ]);

        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'browser',
            'login_url' => 'https://target.example.com/login',
            'username' => 'secret-user@example.com',
            'password' => 'super-secret-password-123',
        ]);

        $builder = new ZapConfigurationBuilder();
        $yaml = $builder->buildYaml($scan, '/tmp/reports');

        $sanitizedYaml = $builder->sanitizeYamlForLogging($yaml);

        // Real YAML retains real credentials for ZAP
        $this->assertStringContainsString('secret-user@example.com', $yaml);
        $this->assertStringContainsString('super-secret-password-123', $yaml);

        // Sanitized YAML redacts credentials for safe logging
        $this->assertStringNotContainsString('secret-user@example.com', $sanitizedYaml);
        $this->assertStringNotContainsString('super-secret-password-123', $sanitizedYaml);
        $this->assertStringContainsString('[REDACTED]', $sanitizedYaml);
    }

    public function test_scope_regex_generation_relative_to_base_origin(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Scope Test Scan',
            'target_url' => 'https://10.100.0.5:8443/ords/r/intg001/spbx-app-inc/dashboard',
            'environment' => 'staging',
            'status' => 'draft',
        ]);

        ScanScope::create([
            'scan_id' => $scan->id,
            'type' => 'include',
            'path' => '/ords/r/intg001/spbx-app-inc/*',
        ]);

        $builder = new ZapConfigurationBuilder();
        $array = $builder->buildArray($scan, '/tmp/reports');

        $context = $array['env']['contexts'][0];
        $this->assertContains('https://10.100.0.5:8443/ords/r/intg001/spbx-app-inc/dashboard', $context['urls']);
        $this->assertContains(preg_quote('https://10.100.0.5:8443', '#') . '/ords/r/intg001/spbx\-app\-inc(?:/.*)?', $context['includePaths']);
        $this->assertStringNotContainsString('dashboard', $context['includePaths'][0]);
    }

    public function test_client_spider_configuration_is_present(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Client Spider Scan',
            'target_url' => 'https://target.example.com/dashboard',
            'environment' => 'staging',
            'status' => 'draft',
        ]);

        ScanConfiguration::create([
            'scan_id' => $scan->id,
            'spider_enabled' => true,
        ]);

        $builder = new ZapConfigurationBuilder();
        $array = $builder->buildArray($scan, '/tmp/reports');

        $jobTypes = array_column($array['jobs'], 'type');
        $this->assertContains('spiderClient', $jobTypes);

        $clientSpiderJob = current(array_filter($array['jobs'], fn($j) => $j['type'] === 'spiderClient'));
        $this->assertEquals('firefox-headless', $clientSpiderJob['parameters']['browserId']);
        $this->assertEquals('Flexible', $clientSpiderJob['parameters']['scopeCheck']);
        $this->assertEquals(5, $clientSpiderJob['parameters']['maxCrawlDepth']);
        $this->assertEquals(100, $clientSpiderJob['parameters']['maxChildren']);
    }

    public function test_browser_mode_generates_zap_authentication_method_browser(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Browser Auth Method Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'draft',
        ]);

        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'browser',
            'login_url' => 'https://target.example.com/login',
            'username' => 'user@example.com',
            'password' => 'secret123',
        ]);

        $builder = new ZapConfigurationBuilder();
        $array = $builder->buildArray($scan, '/tmp/reports');
        $yaml = $builder->buildYaml($scan, '/tmp/reports');

        $this->assertEquals('browser', $array['env']['contexts'][0]['authentication']['method']);
        $this->assertStringContainsString('method: browser', $yaml);
    }

    public function test_form_mode_generates_zap_authentication_method_form(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Form Auth Method Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'draft',
        ]);

        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'form',
            'login_url' => 'https://target.example.com/login',
            'username_field' => 'email',
            'password_field' => 'pass',
            'username' => 'user@example.com',
            'password' => 'secret123',
        ]);

        $builder = new ZapConfigurationBuilder();
        $array = $builder->buildArray($scan, '/tmp/reports');
        $yaml = $builder->buildYaml($scan, '/tmp/reports');

        $this->assertEquals('form', $array['env']['contexts'][0]['authentication']['method']);
        $this->assertStringContainsString('method: form', $yaml);
    }

    public function test_soapbox_style_laravel_form_authentication_configuration(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'SOAPBOX Laravel Form Auth Scan',
            'target_url' => 'https://soapbox.cloud/admins',
            'environment' => 'production',
            'status' => 'draft',
        ]);

        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'form',
            'login_url' => 'https://soapbox.cloud/login',
            'username_field' => 'email',
            'password_field' => 'password',
            'username' => 'admin@soapbox.cloud',
            'password' => 'secret-laravel-password-99',
            'authenticated_url' => 'https://soapbox.cloud/admins',
            'logged_in_indicator' => 'Sign Out',
            'logged_out_indicator' => 'Sign In',
        ]);

        $builder = new ZapConfigurationBuilder();
        $array = $builder->buildArray($scan, '/tmp/reports');
        $yaml = $builder->buildYaml($scan, '/tmp/reports');
        $sanitizedYaml = $builder->sanitizeYamlForLogging($yaml);

        $context = $array['env']['contexts'][0];
        $auth = $context['authentication'];

        // 1. Verify authentication method & parameters
        $this->assertEquals('form', $auth['method']);
        $this->assertEquals('https://soapbox.cloud/login', $auth['parameters']['loginPageUrl']);
        $this->assertEquals('https://soapbox.cloud/login', $auth['parameters']['loginRequestUrl']);
        $this->assertEquals('_token={%_token%}&email={%username%}&password={%password%}', $auth['parameters']['loginRequestBody']);

        // 2. Verify verification rules
        $this->assertEquals('response', $auth['verification']['method']);
        $this->assertEquals('(?i)Sign Out', $auth['verification']['loggedInRegex']);
        $this->assertEquals('(?i)Sign In', $auth['verification']['loggedOutRegex']);

        // 3. Verify session management
        $this->assertEquals(['method' => 'cookie'], $context['sessionManagement']);

        // 4. Verify credentials array
        $this->assertEquals('admin@soapbox.cloud', $context['users'][0]['credentials']['username']);
        $this->assertEquals('secret-laravel-password-99', $context['users'][0]['credentials']['password']);

        // 5. Verify YAML structure
        $this->assertStringContainsString('loginPageUrl: \'https://soapbox.cloud/login\'', $yaml);
        $this->assertStringContainsString('loginRequestUrl: \'https://soapbox.cloud/login\'', $yaml);
        $this->assertStringContainsString('_token={%_token%}&email={%username%}&password={%password%}', $yaml);
        $this->assertStringContainsString('method: cookie', $yaml);
        $this->assertStringContainsString('loggedInRegex: \'(?i)Sign Out\'', $yaml);

        // 6. Verify credentials are not exposed in sanitized logging YAML
        $this->assertStringNotContainsString('secret-laravel-password-99', $sanitizedYaml);
        $this->assertStringContainsString('[REDACTED]', $sanitizedYaml);
    }

    public function test_ajax_spider_job_order_and_configuration_for_apex(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'APEX AJAX Spider Scan',
            'target_url' => 'https://10.100.0.5:8443/ords/r/corex10/spbx-app-inc/dashboard',
            'environment' => 'staging',
            'status' => 'draft',
        ]);

        ScanScope::create([
            'scan_id' => $scan->id,
            'type' => 'include',
            'path' => '/ords/r/corex10/spbx-app-inc/*',
        ]);

        ScanConfiguration::create([
            'scan_id' => $scan->id,
            'spider_enabled' => true,
            'ajax_spider_enabled' => true,
            'passive_scan_enabled' => true,
            'active_scan_enabled' => true,
        ]);

        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'browser',
            'login_url' => 'https://10.100.0.5:8443/ords/r/corex10/soapboxcloud_landing_page/login',
            'username' => 'test-user@example.com',
            'password' => 'test-password',
            'authenticated_url' => 'https://10.100.0.5:8443/ords/r/corex10/spbx-app-inc/dashboard',
        ]);

        $builder = new ZapConfigurationBuilder();
        $array = $builder->buildArray($scan, '/tmp/reports');
        $yaml = $builder->buildYaml($scan, '/tmp/reports');

        $jobTypes = array_column($array['jobs'], 'type');

        // 1. Client Spider still exists.
        $this->assertContains('spiderClient', $jobTypes);

        // 2. AJAX Spider exists.
        $this->assertContains('spiderAjax', $jobTypes);

        $clientSpiderIndex = array_search('spiderClient', $jobTypes, true);
        $ajaxSpiderIndex = array_search('spiderAjax', $jobTypes, true);
        $traditionalSpiderIndex = array_search('spider', $jobTypes, true);
        $passiveScanIndex = array_search('passiveScan-wait', $jobTypes, true);
        $activeScanIndex = array_search('activeScan', $jobTypes, true);
        $reportIndex = array_search('report', $jobTypes, true);

        // 3. AJAX Spider occurs after Client Spider.
        $this->assertGreaterThan($clientSpiderIndex, $ajaxSpiderIndex);

        // 4. AJAX Spider occurs before Traditional Spider.
        $this->assertLessThan($traditionalSpiderIndex, $ajaxSpiderIndex);

        // Verify full order sequence
        $this->assertTrue(
            $clientSpiderIndex < $ajaxSpiderIndex &&
            $ajaxSpiderIndex < $traditionalSpiderIndex &&
            $traditionalSpiderIndex < $passiveScanIndex &&
            $passiveScanIndex < $activeScanIndex &&
            $activeScanIndex < $reportIndex,
            'Jobs are not in the expected order: spiderClient -> spiderAjax -> spider -> passiveScan-wait -> activeScan -> report'
        );

        // Verify AJAX Spider parameters
        $ajaxSpiderJob = current(array_filter($array['jobs'], fn($j) => $j['type'] === 'spiderAjax'));
        $this->assertEquals('Target Context', $ajaxSpiderJob['parameters']['context']);
        $this->assertEquals('https://10.100.0.5:8443/ords/r/corex10/spbx-app-inc/dashboard', $ajaxSpiderJob['parameters']['url']);
        $this->assertEquals(10, $ajaxSpiderJob['parameters']['maxDuration']);
        $this->assertEquals(5, $ajaxSpiderJob['parameters']['maxCrawlDepth']);
        $this->assertEquals(1, $ajaxSpiderJob['parameters']['numberOfBrowsers']);
        $this->assertEquals('firefox-headless', $ajaxSpiderJob['parameters']['browserId']);
        // 5. Existing browser authentication configuration remains unchanged.
        $context = $array['env']['contexts'][0];
        $auth = $context['authentication'];
        $this->assertEquals('browser', $auth['method']);
        $this->assertEquals('#P9999_USERNAME', $auth['parameters']['steps'][1]['cssSelector']);
        $this->assertEquals('#P9999_PASSWORD', $auth['parameters']['steps'][2]['cssSelector']);
        $this->assertEquals('#B12056144829423636247', $auth['parameters']['steps'][3]['cssSelector']);
        $this->assertEquals('(?i)My Incidents', $auth['verification']['loggedInRegex']);
        $this->assertEquals('(?i)Sign In', $auth['verification']['loggedOutRegex']);

        // 6. Existing Incident Management scope remains unchanged.
        $expectedScopeRegex = preg_quote('https://10.100.0.5:8443', '#') . '/ords/r/corex10/spbx\-app\-inc(?:/.*)?';
        $this->assertContains($expectedScopeRegex, $context['includePaths']);

        // 7. No /ords/.* global include is introduced.
        foreach ($context['includePaths'] as $includePath) {
            $this->assertStringNotContainsString('/ords/.*', $includePath);
        }

        // 8. Existing active scan/report jobs remain intact.
        $this->assertContains('activeScan', $jobTypes);
        $this->assertContains('report', $jobTypes);

        // Verify YAML output includes spiderAjax configuration
        $this->assertStringContainsString('spiderAjax', $yaml);
        $this->assertStringContainsString('inScopeOnly: true', $yaml);
    }

    public function test_ajax_spider_runs_only_when_explicitly_enabled(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'AJAX Spider Disabled Test',
            'target_url' => 'https://example.com',
            'environment' => 'staging',
            'status' => 'draft',
        ]);

        ScanConfiguration::create([
            'scan_id' => $scan->id,
            'spider_enabled' => true,
            'ajax_spider_enabled' => false,
            'passive_scan_enabled' => true,
            'active_scan_enabled' => false,
        ]);

        $builder = new ZapConfigurationBuilder();
        $array = $builder->buildArray($scan, '/tmp/reports');
        $jobTypes = array_column($array['jobs'], 'type');

        $this->assertContains('spiderClient', $jobTypes);
        $this->assertContains('spider', $jobTypes);
        $this->assertNotContains('spiderAjax', $jobTypes);
    }

    public function test_form_auth_includes_csrf_token_and_custom_field_names(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Custom Form Auth Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'draft',
        ]);

        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'form',
            'login_url' => 'https://target.example.com/login',
            'username_field' => 'user_email',
            'password_field' => 'user_password',
            'username' => 'test@example.com',
            'password' => 'pass123',
        ]);

        $builder = new ZapConfigurationBuilder();
        $array = $builder->buildArray($scan, '/tmp/reports');

        $auth = $array['env']['contexts'][0]['authentication'];
        $this->assertEquals('_token={%_token%}&user_email={%username%}&user_password={%password%}', $auth['parameters']['loginRequestBody']);
    }

    public function test_auth_enabled_scan_generates_diagnostics_and_auth_report_jobs(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Auth Diagnostics Jobs Scan',
            'target_url' => 'https://soapbox.cloud/admins',
            'environment' => 'production',
            'status' => 'draft',
        ]);

        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'form',
            'login_url' => 'https://soapbox.cloud/login',
            'username' => 'admin@soapbox.cloud',
            'password' => 'secret123',
        ]);

        $builder = new ZapConfigurationBuilder();
        $array = $builder->buildArray($scan, '/tmp/reports', 'report.json');

        $jobs = $array['jobs'];
        $jobTypes = array_column($jobs, 'type');

        // First job must be diagnostics enabled=true
        $this->assertEquals('diagnostics', $jobs[0]['type']);
        $this->assertTrue($jobs[0]['parameters']['enabled']);

        // Check report jobs
        $reportJobs = array_values(array_filter($jobs, fn($j) => $j['type'] === 'report'));
        $this->assertCount(2, $reportJobs);

        $authReportJob = $reportJobs[0];
        $this->assertEquals('auth-report-json', $authReportJob['parameters']['template']);
        $this->assertEquals('/zap/wrk', $authReportJob['parameters']['reportDir']);
        $this->assertEquals('auth-report.json', $authReportJob['parameters']['reportFile']);

        $traditionalReportJob = $reportJobs[1];
        $this->assertEquals('traditional-json', $traditionalReportJob['parameters']['template']);
        $this->assertEquals('report.json', $traditionalReportJob['parameters']['reportFile']);

        // Diagnostics disabled before report
        $diagJobs = array_values(array_filter($jobs, fn($j) => $j['type'] === 'diagnostics'));
        $this->assertCount(2, $diagJobs);
        $this->assertTrue($diagJobs[0]['parameters']['enabled']);
        $this->assertFalse($diagJobs[1]['parameters']['enabled']);
    }

    public function test_auth_disabled_scan_does_not_generate_auth_report_or_diagnostics_jobs(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Unauthenticated Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'draft',
        ]);

        $builder = new ZapConfigurationBuilder();
        $array = $builder->buildArray($scan, '/tmp/reports', 'report.json');

        $jobs = $array['jobs'];
        $jobTypes = array_column($jobs, 'type');

        $this->assertNotContains('diagnostics', $jobTypes);

        $reportJobs = array_values(array_filter($jobs, fn($j) => $j['type'] === 'report'));
        $this->assertCount(1, $reportJobs);
        $this->assertEquals('traditional-json', $reportJobs[0]['parameters']['template']);
        $this->assertEquals('report.json', $reportJobs[0]['parameters']['reportFile']);
    }

    public function test_target_url_path_is_preserved_in_context_urls_and_spider_seed_urls(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Target Path Preservation Scan',
            'target_url' => 'https://soapbox.cloud/admins',
            'environment' => 'production',
            'status' => 'draft',
        ]);

        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'form',
            'login_url' => 'https://soapbox.cloud/login',
            'username' => 'admin@soapbox.cloud',
            'password' => 'secret123',
        ]);

        $builder = new ZapConfigurationBuilder();
        $array = $builder->buildArray($scan, '/tmp/reports');

        $context = $array['env']['contexts'][0];
        $this->assertSame('https://soapbox.cloud/admins', $context['urls'][0]);
        $this->assertNotEquals('https://soapbox.cloud', $context['urls'][0]);

        $jobs = array_column($array['jobs'], null, 'type');
        $spider = $jobs['spider'];
        $spiderClient = $jobs['spiderClient'];
        $activeScan = $jobs['activeScan'];

        $this->assertSame('https://soapbox.cloud/admins', $spider['parameters']['url']);
        $this->assertSame('https://soapbox.cloud/admins', $spiderClient['parameters']['url']);
        $this->assertSame('AssessmentUser', $spider['parameters']['user']);
        $this->assertSame('AssessmentUser', $spiderClient['parameters']['user']);
        $this->assertSame('AssessmentUser', $activeScan['parameters']['user']);
    }

    public function test_generic_target_url_paths_are_supported(): void
    {
        $user = User::factory()->create();
        $builder = new ZapConfigurationBuilder();

        $targetUrls = [
            'https://example.com' => 'https://example.com',
            'https://example.com/admin' => 'https://example.com/admin',
            'https://example.com/admin/' => 'https://example.com/admin',
            'https://example.com/admins' => 'https://example.com/admins',
            'https://example.com/admins/' => 'https://example.com/admins',
            'https://example.com/app' => 'https://example.com/app',
        ];

        foreach ($targetUrls as $inputTargetUrl => $expectedTargetUrl) {
            $scan = Scan::create([
                'user_id' => $user->id,
                'name' => "Generic Target Path Scan {$inputTargetUrl}",
                'target_url' => $inputTargetUrl,
                'environment' => 'staging',
                'status' => 'draft',
            ]);

            $array = $builder->buildArray($scan, '/tmp/reports');
            $context = $array['env']['contexts'][0];

            $this->assertSame($expectedTargetUrl, $context['urls'][0]);

            $jobs = array_column($array['jobs'], null, 'type');
            if (isset($jobs['spider'])) {
                $this->assertSame($expectedTargetUrl, $jobs['spider']['parameters']['url']);
            }
            if (isset($jobs['spiderClient'])) {
                $this->assertSame($expectedTargetUrl, $jobs['spiderClient']['parameters']['url']);
            }
        }
    }
}


