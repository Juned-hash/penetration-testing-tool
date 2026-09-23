<?php

namespace Tests\Unit\Zap;

use App\Models\Finding;
use App\Models\Scan;
use App\Models\User;
use App\Services\Zap\ZapResultParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ZapResultParserTest extends TestCase
{
    use RefreshDatabase;

    public function test_parses_zap_json_report_and_creates_findings(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Report Parser Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'processing_results',
        ]);

        $mockJson = [
            '@version' => '2.14.0',
            'site' => [
                [
                    '@name' => 'https://target.example.com',
                    'alerts' => [
                        [
                            'pluginid' => '10021',
                            'alert' => 'X-Content-Type-Options Header Missing',
                            'riskcode' => '1',
                            'confidence' => '2',
                            'riskdesc' => 'Low (Medium)',
                            'desc' => '<p>MIME sniffing header missing</p>',
                            'solution' => '<p>Add X-Content-Type-Options: nosniff</p>',
                            'cweid' => '693',
                            'wascid' => '15',
                            'instances' => [
                                [
                                    'uri' => 'https://target.example.com/api',
                                    'method' => 'GET',
                                    'param' => '',
                                    'evidence' => 'nosniff missing',
                                ],
                            ],
                        ],
                        [
                            'pluginid' => '40012',
                            'alert' => 'Cross-Site Scripting (Reflected)',
                            'riskcode' => '3',
                            'confidence' => '3',
                            'riskdesc' => 'High (High)',
                            'desc' => '<p>Reflected XSS in search parameter</p>',
                            'solution' => '<p>Sanitize output</p>',
                            'cweid' => '79',
                            'wascid' => '8',
                            'instances' => [
                                [
                                    'uri' => 'https://target.example.com/search',
                                    'method' => 'GET',
                                    'param' => 'q',
                                    'attack' => "<script>alert(1)</script>",
                                    'evidence' => "<script>alert(1)</script>",
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $tempPath = storage_path('app/test_zap_report.json');
        File::put($tempPath, json_encode($mockJson));

        $parser = new ZapResultParser();
        $count = $parser->parseAndStore($scan, $tempPath);

        File::delete($tempPath);

        $this->assertEquals(2, $count);
        $this->assertDatabaseHas('findings', [
            'scan_id' => $scan->id,
            'name' => 'X-Content-Type-Options Header Missing',
            'risk' => 'Low',
            'severity' => 'low',
            'cwe_id' => '693',
        ]);

        $this->assertDatabaseHas('findings', [
            'scan_id' => $scan->id,
            'name' => 'Cross-Site Scripting (Reflected)',
            'risk' => 'High',
            'severity' => 'high',
            'parameter' => 'q',
            'cwe_id' => '79',
        ]);
    }
}
