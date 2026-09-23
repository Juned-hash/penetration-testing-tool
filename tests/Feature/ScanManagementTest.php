<?php

namespace Tests\Feature;

use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_scans_index(): void
    {
        $user = User::factory()->create();

        Scan::create([
            'user_id' => $user->id,
            'name' => 'Staging Portal Assessment',
            'target_url' => 'https://staging.example.com',
            'environment' => 'staging',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)->get('/scans');

        $response->assertStatus(200);
        $response->assertSee('Staging Portal Assessment');
        $response->assertSee('https://staging.example.com');
    }

    public function test_user_can_render_scan_creation_form(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/scans/create');

        $response->assertStatus(200);
        $response->assertSee('Configure New Security Assessment');
    }

    public function test_user_can_create_scan_with_scope_and_authentication(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/scans', [
            'name' => 'Production API Assessment',
            'target_url' => 'https://api.example.com',
            'environment' => 'production',
            'included_paths' => "/*\n/v1/*",
            'excluded_paths' => "/logout\n/admin/backup/*",
            'auth_mode' => 'form',
            'login_url' => 'https://api.example.com/login',
            'username_field' => 'email',
            'password_field' => 'password',
            'username' => 'tester@example.com',
            'password' => 'SecretPassword123!',
        ]);

        $scan = Scan::firstWhere('name', 'Production API Assessment');

        $this->assertNotNull($scan);
        $this->assertEquals($user->id, $scan->user_id);
        $this->assertEquals('https://api.example.com', $scan->target_url);
        $this->assertEquals('production', $scan->environment);

        // Verify scopes
        $this->assertCount(2, $scan->scanScopes->where('type', 'include'));
        $this->assertCount(2, $scan->scanScopes->where('type', 'exclude'));

        // Verify authentication config encryption
        $this->assertEquals('form', $scan->authenticationConfiguration->mode);
        $this->assertEquals('SecretPassword123!', $scan->authenticationConfiguration->password);

        $response->assertRedirect(route('scans.show', $scan));
    }

    public function test_server_side_validation_rejects_invalid_url(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/scans', [
            'name' => 'Invalid Assessment',
            'target_url' => 'not-a-valid-url',
            'environment' => 'staging',
            'auth_mode' => 'none',
        ]);

        $response->assertSessionHasErrors('target_url');
        $this->assertDatabaseMissing('scans', ['name' => 'Invalid Assessment']);
    }

    public function test_user_can_view_own_scan_detail(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'My Private Scan',
            'target_url' => 'https://my-app.example.com',
            'environment' => 'development',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)->get("/scans/{$scan->id}");

        $response->assertStatus(200);
        $response->assertSee('My Private Scan');
        $response->assertSee('https://my-app.example.com');
    }

    public function test_user_cannot_view_another_users_scan_detail(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user1->id,
            'name' => 'User 1 Confidential Scan',
            'target_url' => 'https://confidential.example.com',
            'environment' => 'production',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user2)->get("/scans/{$scan->id}");

        $response->assertStatus(403);
    }
}
