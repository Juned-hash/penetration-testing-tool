<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_empty_state_when_user_has_no_scans(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Security Assessment Dashboard');
        $response->assertSee('No Assessments Configured');
    }

    public function test_dashboard_displays_correct_database_metrics_and_findings_summary(): void
    {
        $user = User::factory()->create();

        $scanCompleted = Scan::create([
            'user_id' => $user->id,
            'name' => 'Staging App',
            'target_url' => 'https://staging.example.com',
            'environment' => 'staging',
            'status' => 'completed',
        ]);

        $scanRunning = Scan::create([
            'user_id' => $user->id,
            'name' => 'Dev App',
            'target_url' => 'https://dev.example.com',
            'environment' => 'development',
            'status' => 'running',
        ]);

        Finding::create([
            'scan_id' => $scanCompleted->id,
            'name' => 'SQL Injection Vulnerability',
            'severity' => 'Critical',
            'confidence' => 'High',
            'status' => 'Open',
        ]);

        Finding::create([
            'scan_id' => $scanCompleted->id,
            'name' => 'Cross-Site Scripting (XSS)',
            'severity' => 'High',
            'confidence' => 'Medium',
            'status' => 'Open',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('https://staging.example.com');
        $response->assertSee('https://dev.example.com');
        $response->assertSee('Completed');
        $response->assertSee('Running / Active');
        $response->assertViewHas('total_scans', 2);
        $response->assertViewHas('running_scans', 1);
        $response->assertViewHas('completed_scans', 1);
        $response->assertViewHas('findings_by_severity', [
            'Critical' => 1,
            'High' => 1,
            'Medium' => 0,
            'Low' => 0,
            'Informational' => 0,
        ]);
    }

    public function test_dashboard_does_not_display_other_users_scans(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Scan::create([
            'user_id' => $user1->id,
            'name' => 'User 1 App',
            'target_url' => 'https://user1.example.com',
            'environment' => 'production',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user2)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertDontSee('https://user1.example.com');
        $response->assertViewHas('total_scans', 0);
    }
}
