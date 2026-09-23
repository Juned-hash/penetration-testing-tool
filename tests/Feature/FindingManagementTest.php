<?php

namespace Tests\Feature;

use App\Models\AuthenticationConfiguration;
use App\Models\Finding;
use App\Models\Scan;
use App\Models\User;
use App\Services\Zap\ZapResultParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FindingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_result_parser_preserves_all_finding_attributes(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Full Ingestion Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'processing_results',
        ]);

        $mockJson = [
            'site' => [
                [
                    '@name' => 'https://target.example.com',
                    'alerts' => [
                        [
                            'pluginid' => '90001',
                            'alertRef' => '90001-1',
                            'name' => 'SQL Injection Vulnerability',
                            'riskcode' => '3',
                            'confidence' => '3',
                            'riskdesc' => 'High (High)',
                            'desc' => '<p>SQL injection in id param</p>',
                            'otherinfo' => '<p>Allows database access</p>',
                            'solution' => '<p>Use parameterized queries</p>',
                            'reference' => 'https://owasp.org/sql-injection',
                            'cweid' => '89',
                            'wascid' => '19',
                            'wstg' => 'WSTG-INJV-05',
                            'instances' => [
                                [
                                    'uri' => 'https://target.example.com/item',
                                    'method' => 'POST',
                                    'param' => 'id',
                                    'attack' => "1' OR '1'='1",
                                    'evidence' => 'syntax error in SQL query',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $jsonPath = storage_path('app/test_zap_full_report.json');
        File::put($jsonPath, json_encode($mockJson));

        $parser = new ZapResultParser();
        $count = $parser->parseAndStore($scan, $jsonPath);

        File::delete($jsonPath);

        $this->assertEquals(1, $count);

        $finding = Finding::where('scan_id', $scan->id)->first();
        $this->assertNotNull($finding);
        $this->assertEquals('owasp_zap', $finding->source);
        $this->assertEquals('90001', $finding->external_id);
        $this->assertEquals('SQL Injection Vulnerability', $finding->name);
        $this->assertEquals('High', $finding->risk);
        $this->assertEquals('High', $finding->confidence);
        $this->assertEquals('high', $finding->severity);
        $this->assertEquals('https://target.example.com/item', $finding->url);
        $this->assertEquals('POST', $finding->method);
        $this->assertEquals('id', $finding->parameter);
        $this->assertEquals("1' OR '1'='1", $finding->attack);
        $this->assertEquals('syntax error in SQL query', $finding->evidence);
        $this->assertEquals('SQL injection in id param', $finding->description);
        $this->assertEquals('Allows database access', $finding->impact);
        $this->assertEquals('Use parameterized queries', $finding->solution);
        $this->assertEquals('https://owasp.org/sql-injection', $finding->reference);
        $this->assertEquals('89', $finding->cwe_id);
        $this->assertEquals('19', $finding->wasc_id);
        $this->assertEquals('WSTG-INJV-05', $finding->wstg_id);
    }

    public function test_user_can_view_findings_index_for_own_scan(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'User Scan Findings',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'completed',
        ]);

        Finding::create([
            'scan_id' => $scan->id,
            'source' => 'owasp_zap',
            'name' => 'XSS Finding',
            'severity' => 'high',
            'risk' => 'High',
            'confidence' => 'High',
            'url' => 'https://target.example.com/search',
        ]);

        $response = $this->actingAs($user)->get("/scans/{$scan->id}/findings");

        $response->assertStatus(200);
        $response->assertSee('XSS Finding');
        $response->assertSee('https://target.example.com/search');
    }

    public function test_findings_filtering_and_sorting(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Filtered Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'completed',
        ]);

        Finding::create([
            'scan_id' => $scan->id,
            'source' => 'owasp_zap',
            'name' => 'High XSS',
            'severity' => 'high',
            'risk' => 'High',
            'confidence' => 'High',
            'status' => 'open',
            'url' => 'https://target.example.com/a',
        ]);

        Finding::create([
            'scan_id' => $scan->id,
            'source' => 'owasp_zap',
            'name' => 'Low Header Missing',
            'severity' => 'low',
            'risk' => 'Low',
            'confidence' => 'Medium',
            'status' => 'open',
            'url' => 'https://target.example.com/b',
        ]);

        // Filter by High severity
        $response = $this->actingAs($user)->get("/scans/{$scan->id}/findings?severity=high");
        $response->assertStatus(200);
        $response->assertSee('High XSS');
        $response->assertDontSee('Low Header Missing');

        // Filter by Low severity
        $responseLow = $this->actingAs($user)->get("/scans/{$scan->id}/findings?severity=low");
        $responseLow->assertStatus(200);
        $responseLow->assertSee('Low Header Missing');
        $responseLow->assertDontSee('High XSS');
    }

    public function test_user_can_view_finding_detail(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Detail Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'completed',
        ]);

        $finding = Finding::create([
            'scan_id' => $scan->id,
            'source' => 'owasp_zap',
            'name' => 'CSRF Vulnerability',
            'severity' => 'medium',
            'risk' => 'Medium',
            'confidence' => 'High',
            'url' => 'https://target.example.com/profile',
            'method' => 'POST',
            'parameter' => 'email',
            'evidence' => 'No anti-CSRF token present',
            'description' => 'State changing POST request lacks anti-CSRF token',
            'solution' => 'Implement SameSite cookie attributes and anti-CSRF tokens',
            'cwe_id' => '352',
            'wasc_id' => '9',
        ]);

        $response = $this->actingAs($user)->get("/scans/{$scan->id}/findings/{$finding->id}");

        $response->assertStatus(200);
        $response->assertSee('CSRF Vulnerability');
        $response->assertSee('No anti-CSRF token present');
        $response->assertSee('CWE-352');
        $response->assertSee('WASC-9');
    }

    public function test_unauthorized_user_cannot_view_another_users_scan_findings(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user1->id,
            'name' => 'Secret Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'completed',
        ]);

        $finding = Finding::create([
            'scan_id' => $scan->id,
            'source' => 'owasp_zap',
            'name' => 'Confidential Vulnerability',
            'severity' => 'high',
            'url' => 'https://target.example.com',
        ]);

        $indexResponse = $this->actingAs($user2)->get("/scans/{$scan->id}/findings");
        $indexResponse->assertStatus(403);

        $showResponse = $this->actingAs($user2)->get("/scans/{$scan->id}/findings/{$finding->id}");
        $showResponse->assertStatus(403);
    }

    public function test_cannot_access_finding_belonging_to_different_scan(): void
    {
        $user = User::factory()->create();

        $scan1 = Scan::create([
            'user_id' => $user->id,
            'name' => 'Scan 1',
            'target_url' => 'https://target1.example.com',
            'environment' => 'staging',
            'status' => 'completed',
        ]);

        $scan2 = Scan::create([
            'user_id' => $user->id,
            'name' => 'Scan 2',
            'target_url' => 'https://target2.example.com',
            'environment' => 'staging',
            'status' => 'completed',
        ]);

        $findingOfScan2 = Finding::create([
            'scan_id' => $scan2->id,
            'source' => 'owasp_zap',
            'name' => 'Finding on Scan 2',
            'severity' => 'medium',
            'url' => 'https://target2.example.com',
        ]);

        // Trying to access findingOfScan2 via scan1 route should return 404
        $response = $this->actingAs($user)->get("/scans/{$scan1->id}/findings/{$findingOfScan2->id}");
        $response->assertStatus(404);
    }

    public function test_finding_views_never_display_authentication_credentials(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Auth Finding Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'completed',
        ]);

        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'form',
            'username' => 'admin_user',
            'password' => 'SECRET_SUPER_PASSWORD_999!',
        ]);

        $finding = Finding::create([
            'scan_id' => $scan->id,
            'source' => 'owasp_zap',
            'name' => 'Unauthenticated Sensitive Finding',
            'severity' => 'high',
            'url' => 'https://target.example.com',
        ]);

        $indexResponse = $this->actingAs($user)->get("/scans/{$scan->id}/findings");
        $indexResponse->assertStatus(200);
        $indexResponse->assertDontSee('SECRET_SUPER_PASSWORD_999!');

        $showResponse = $this->actingAs($user)->get("/scans/{$scan->id}/findings/{$finding->id}");
        $showResponse->assertStatus(200);
        $showResponse->assertDontSee('SECRET_SUPER_PASSWORD_999!');
    }
}
