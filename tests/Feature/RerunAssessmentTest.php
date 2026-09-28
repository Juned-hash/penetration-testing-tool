<?php

namespace Tests\Feature;

use App\Jobs\RunAssessment;
use App\Models\AuthenticationConfiguration;
use App\Models\Finding;
use App\Models\Scan;
use App\Models\ScanConfiguration;
use App\Models\ScanLog;
use App\Models\ScanScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RerunAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_rerun_completed_assessment(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $originalScan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Original Completed Assessment',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'completed',
            'authorization_confirmed_at' => now()->subDay(),
            'started_at' => now()->subDay(),
            'completed_at' => now()->subHours(23),
        ]);

        ScanConfiguration::create([
            'scan_id' => $originalScan->id,
            'spider_enabled' => true,
            'ajax_spider_enabled' => false,
            'passive_scan_enabled' => true,
            'active_scan_enabled' => true,
            'authentication_enabled' => true,
        ]);

        ScanScope::create([
            'scan_id' => $originalScan->id,
            'type' => 'include',
            'path' => '/api/*',
        ]);

        ScanScope::create([
            'scan_id' => $originalScan->id,
            'type' => 'exclude',
            'path' => '/logout',
        ]);

        AuthenticationConfiguration::create([
            'scan_id' => $originalScan->id,
            'mode' => 'form',
            'login_url' => 'https://target.example.com/login',
            'username_field' => 'email',
            'password_field' => 'password',
            'username' => 'testuser@example.com',
            'password' => 'SecretPass123!',
        ]);

        Finding::create([
            'scan_id' => $originalScan->id,
            'source' => 'owasp_zap',
            'name' => 'Original Vulnerability 1',
            'severity' => 'high',
            'risk' => 'High',
            'confidence' => 'High',
            'url' => 'https://target.example.com/api/test',
        ]);

        $response = $this->actingAs($user)->post("/scans/{$originalScan->id}/rerun");

        $newScan = Scan::where('parent_assessment_id', $originalScan->id)->first();

        $this->assertNotNull($newScan);
        $response->assertRedirect(route('scans.show', $newScan));

        // Original Scan must remain untouched
        $originalScan->refresh();
        $this->assertEquals('completed', $originalScan->status);
        $this->assertEquals(1, $originalScan->findings()->count());

        // New Scan attributes
        $this->assertEquals('queued', $newScan->status);
        $this->assertEquals($originalScan->name, $newScan->name);
        $this->assertEquals($originalScan->target_url, $newScan->target_url);
        $this->assertEquals($originalScan->environment, $newScan->environment);
        $this->assertNotNull($newScan->authorization_confirmed_at);
        $this->assertEquals(0, $newScan->findings()->count());

        // Configuration copied
        $this->assertNotNull($newScan->scanConfiguration);
        $this->assertTrue((bool)$newScan->scanConfiguration->spider_enabled);
        $this->assertTrue((bool)$newScan->scanConfiguration->authentication_enabled);

        // Scopes copied
        $this->assertEquals(2, $newScan->scanScopes()->count());
        $this->assertDatabaseHas('scan_scopes', [
            'scan_id' => $newScan->id,
            'type' => 'include',
            'path' => '/api/*',
        ]);

        // Authentication copied
        $this->assertNotNull($newScan->authenticationConfiguration);
        $this->assertEquals('form', $newScan->authenticationConfiguration->mode);
        $this->assertEquals('testuser@example.com', $newScan->authenticationConfiguration->username);

        // Queue job dispatched for new scan only
        Queue::assertPushedOn('assessments', RunAssessment::class, function ($job) use ($newScan) {
            return $job->scan->id === $newScan->id;
        });

        // Audit log created
        $this->assertDatabaseHas('scan_logs', [
            'scan_id' => $newScan->id,
            'phase' => 'queued',
        ]);
    }

    public function test_can_rerun_failed_and_cancelled_assessments(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $failedScan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Failed Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'failed',
            'failure_reason' => 'Connection timeout',
        ]);

        $response = $this->actingAs($user)->post("/scans/{$failedScan->id}/rerun");
        $newScanFromFailed = Scan::where('parent_assessment_id', $failedScan->id)->first();

        $this->assertNotNull($newScanFromFailed);
        $response->assertRedirect(route('scans.show', $newScanFromFailed));

        $cancelledScan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Cancelled Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'cancelled',
        ]);

        $response2 = $this->actingAs($user)->post("/scans/{$cancelledScan->id}/rerun");
        $newScanFromCancelled = Scan::where('parent_assessment_id', $cancelledScan->id)->first();

        $this->assertNotNull($newScanFromCancelled);
        $response2->assertRedirect(route('scans.show', $newScanFromCancelled));
    }

    public function test_cannot_rerun_active_running_or_queued_assessment(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $runningScan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Running Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'running',
        ]);

        $response = $this->actingAs($user)->post("/scans/{$runningScan->id}/rerun");
        $response->assertRedirect(route('scans.show', $runningScan));
        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('scans', [
            'parent_assessment_id' => $runningScan->id,
        ]);
    }

    public function test_unauthorized_user_cannot_rerun_another_users_assessment(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $owner->id,
            'name' => 'Owner Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($otherUser)->post("/scans/{$scan->id}/rerun");
        $response->assertStatus(403);

        $this->assertDatabaseMissing('scans', [
            'parent_assessment_id' => $scan->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_rerun_assessment(): void
    {
        $user = User::factory()->create();
        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Unauth Test Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'completed',
        ]);

        $response = $this->post("/scans/{$scan->id}/rerun");
        $response->assertRedirect('/login');
    }
}
