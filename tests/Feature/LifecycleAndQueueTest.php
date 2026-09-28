<?php

namespace Tests\Feature;

use App\Jobs\RunAssessment;
use App\Models\Scan;
use App\Models\User;
use App\Services\ScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LifecycleAndQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_assessment_dispatches_run_assessment_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Queued Scan Test',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'draft',
            'authorization_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($user)->post("/scans/{$scan->id}/start");

        $response->assertRedirect(route('scans.show', $scan));
        $this->assertEquals('queued', $scan->fresh()->status);

        Queue::assertPushed(RunAssessment::class, function ($job) use ($scan) {
            return $job->scan->id === $scan->id;
        });
    }

    public function test_assessment_cannot_be_restarted_if_already_queued_or_running(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Running Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'running',
            'authorization_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($user)->post("/scans/{$scan->id}/start");

        $response->assertSessionHas('error');
        Queue::assertNotPushed(RunAssessment::class);
    }

    public function test_cancel_route_is_no_longer_available(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Scan Test',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'running',
            'authorization_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($user)->post("/scans/{$scan->id}/cancel");

        $response->assertStatus(404);
        $this->assertEquals('running', $scan->fresh()->status);
    }

    public function test_admin_can_trigger_queue_worker_restart(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/settings/queue/restart');

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('success', 'Queue worker restart signal transmitted successfully.');
    }

    public function test_tester_cannot_trigger_queue_worker_restart_and_receives_403(): void
    {
        $tester = User::factory()->create(['role' => 'tester']);

        $response = $this->actingAs($tester)->post('/settings/queue/restart');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_trigger_queue_worker_restart(): void
    {
        $response = $this->post('/settings/queue/restart');

        $response->assertRedirect(route('login'));
    }

    public function test_status_endpoint_returns_json_response(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Status Test Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'running',
            'authorization_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson("/scans/{$scan->id}/status");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $scan->id,
            'status' => 'running',
        ]);
    }

    public function test_run_assessment_job_transitions_lifecycle_states(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Job Handler Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'queued',
            'authorization_confirmed_at' => now(),
        ]);

        $job = new RunAssessment($scan);
        $job->handle(app(\App\Services\Zap\ZapService::class));

        $this->assertContains($scan->fresh()->status, ['completed', 'failed']);
    }
}
