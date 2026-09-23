<?php

namespace Tests\Feature;

use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuthorizationControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_cannot_start_without_authorization_confirmation(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Unconfirmed Assessment',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'draft',
            'authorization_confirmed_at' => null,
        ]);

        $response = $this->actingAs($user)->post("/scans/{$scan->id}/start");

        $response->assertRedirect(route('scans.show', $scan));
        $response->assertSessionHas('error');

        $scan->refresh();
        $this->assertEquals('draft', $scan->status);
    }

    public function test_assessment_authorization_can_be_confirmed_server_side(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Staging Assessment',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'draft',
            'authorization_confirmed_at' => null,
        ]);

        $response = $this->actingAs($user)->post("/scans/{$scan->id}/confirm-authorization", [
            'authorization_confirmed' => '1',
        ]);

        $response->assertRedirect(route('scans.show', $scan));
        $response->assertSessionHas('success');

        $scan->refresh();
        $this->assertNotNull($scan->authorization_confirmed_at);
    }

    public function test_assessment_can_start_after_authorization_confirmation(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Confirmed Assessment',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'draft',
            'authorization_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($user)->post("/scans/{$scan->id}/start");

        $response->assertRedirect(route('scans.show', $scan));
        $response->assertSessionHas('success', 'Assessment job dispatched to queue.');

        $scan->refresh();
        $this->assertEquals('queued', $scan->status);
    }

    public function test_unauthorized_user_cannot_confirm_or_start_another_users_assessment(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user1->id,
            'name' => 'User 1 Confidential Scan',
            'target_url' => 'https://target.example.com',
            'environment' => 'staging',
            'status' => 'draft',
            'authorization_confirmed_at' => null,
        ]);

        $confirmResponse = $this->actingAs($user2)->post("/scans/{$scan->id}/confirm-authorization", [
            'authorization_confirmed' => '1',
        ]);
        $confirmResponse->assertStatus(403);

        $startResponse = $this->actingAs($user2)->post("/scans/{$scan->id}/start");
        $startResponse->assertStatus(403);
    }

    public function test_production_environment_displays_warning_alert_on_review(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'Production System Scan',
            'target_url' => 'https://prod.example.com',
            'environment' => 'production',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)->get("/scans/{$scan->id}");

        $response->assertStatus(200);
        $response->assertSee('PRODUCTION ENVIRONMENT WARNING');
    }
}
