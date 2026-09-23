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

    public function test_user_can_cancel_active_or_queued_assessment(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Cancellable Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'queued',
            'authorization_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($user)->post("/scans/{$scan->id}/cancel");

        $response->assertRedirect(route('scans.show', $scan));
        $this->assertEquals('cancelled', $scan->fresh()->status);
        $this->assertNotNull($scan->fresh()->completed_at);
    }

    public function test_unauthorized_user_cannot_cancel_another_users_assessment(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user1->id,
            'name' => 'User 1 Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'running',
            'authorization_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($user2)->post("/scans/{$scan->id}/cancel");

        $response->assertStatus(403);
        $this->assertEquals('running', $scan->fresh()->status);
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
