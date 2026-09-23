<?php

namespace Tests\Feature;

use App\Models\AuthenticationConfiguration;
use App\Models\Finding;
use App\Models\Report;
use App\Models\Scan;
use App\Models\User;
use App\Services\Report\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReportManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_report_generator_creates_valid_pdf_file_and_database_record(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'PDF Report Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'completed',
        ]);

        Finding::create([
            'scan_id' => $scan->id,
            'source' => 'owasp_zap',
            'name' => 'Real Finding 1',
            'severity' => 'high',
            'risk' => 'High',
            'confidence' => 'High',
            'url' => 'https://target.example.com/api',
            'description' => 'Real vulnerability description',
        ]);

        $reportService = app(ReportService::class);
        $report = $reportService->generateReport($scan, 'pdf');

        $this->assertNotNull($report);
        $this->assertEquals('pdf', $report->type);
        $this->assertEquals('completed', $report->status);
        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'scan_id' => $scan->id,
            'type' => 'pdf',
        ]);

        $fullPath = storage_path('app/' . $report->file_path);
        $this->assertTrue(File::exists($fullPath));
        $this->assertGreaterThan(0, File::size($fullPath));

        // Clean up test generated file
        File::delete($fullPath);
    }

    public function test_report_contains_only_stored_findings_and_never_exposes_passwords(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Secret Test Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'completed',
        ]);

        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'form',
            'login_url' => 'https://target.example.com/login',
            'username' => 'test_user_account',
            'password' => 'TOP_SECRET_PASSWORD_999!',
        ]);

        Finding::create([
            'scan_id' => $scan->id,
            'source' => 'owasp_zap',
            'name' => 'Database Stored Vulnerability',
            'severity' => 'medium',
            'risk' => 'Medium',
            'confidence' => 'High',
            'url' => 'https://target.example.com/item',
        ]);

        // Render PDF view directly to inspect HTML content safely
        $html = view('reports.pdf', ['scan' => $scan->fresh()])->render();

        $this->assertStringContainsString('Database Stored Vulnerability', $html);
        $this->assertStringContainsString('https://target.example.com/item', $html);

        // Verify secrets are NOT present
        $this->assertStringNotContainsString('TOP_SECRET_PASSWORD_999!', $html);
        $this->assertStringContainsString('ENCRYPTED AT REST', $html);
    }

    public function test_user_can_trigger_pdf_report_generation_via_post_endpoint(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Endpoint PDF Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user)->post("/scans/{$scan->id}/reports/pdf");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('reports', [
            'scan_id' => $scan->id,
            'type' => 'pdf',
        ]);
    }

    public function test_authorized_user_can_download_report(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Downloadable Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'completed',
        ]);

        $reportsDir = storage_path('app/reports');
        File::ensureDirectoryExists($reportsDir);
        $dummyFilePath = 'reports/dummy_test_' . time() . '.pdf';
        File::put(storage_path('app/' . $dummyFilePath), '%PDF-1.4 Dummy PDF Content');

        $report = Report::create([
            'scan_id' => $scan->id,
            'type' => 'pdf',
            'file_path' => $dummyFilePath,
            'status' => 'completed',
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get("/reports/{$report->id}/download");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');

        File::delete(storage_path('app/' . $dummyFilePath));
    }

    public function test_unauthorized_user_cannot_download_another_users_report(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user1->id,
            'name' => 'User 1 Confidential Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'completed',
        ]);

        $dummyFilePath = 'reports/dummy_user1_' . time() . '.pdf';
        File::ensureDirectoryExists(storage_path('app/reports'));
        File::put(storage_path('app/' . $dummyFilePath), '%PDF-1.4 Dummy Content');

        $report = Report::create([
            'scan_id' => $scan->id,
            'type' => 'pdf',
            'file_path' => $dummyFilePath,
            'status' => 'completed',
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($user2)->get("/reports/{$report->id}/download");

        $response->assertStatus(403);

        File::delete(storage_path('app/' . $dummyFilePath));
    }
}
