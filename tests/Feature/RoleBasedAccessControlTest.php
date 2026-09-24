<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleBasedAccessControlTest extends TestCase
{
    use RefreshDatabase;

    // --- ADMIN TESTS ---

    public function test_admin_can_log_in(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin_user@example.com',
            'password' => Hash::make('AdminPass123!'),
        ]);

        $response = $this->post('/login', [
            'email' => 'admin_user@example.com',
            'password' => 'AdminPass123!',
        ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_admin_can_see_user_management_in_navigation(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('User Management');
    }

    public function test_admin_can_access_user_management_index(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/users');

        $response->assertStatus(200);
        $response->assertSee('Application Users');
    }

    public function test_admin_can_access_add_user_screen(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/users/create');

        $response->assertStatus(200);
        $response->assertSee('Add New User');
    }

    public function test_admin_can_create_an_admin_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'New Admin',
            'email' => 'new_admin@example.com',
            'password' => 'SecureAdmin123!',
            'password_confirmation' => 'SecureAdmin123!',
            'role' => 'admin',
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        $createdUser = User::where('email', 'new_admin@example.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertEquals('admin', $createdUser->role);
        $this->assertTrue($createdUser->isAdmin());
        $this->assertTrue(Hash::check('SecureAdmin123!', $createdUser->password));
    }

    public function test_admin_can_create_a_tester_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'New Tester',
            'email' => 'new_tester@example.com',
            'password' => 'SecureTester123!',
            'password_confirmation' => 'SecureTester123!',
            'role' => 'tester',
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        $createdUser = User::where('email', 'new_tester@example.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertEquals('tester', $createdUser->role);
        $this->assertTrue($createdUser->isTester());
        $this->assertTrue(Hash::check('SecureTester123!', $createdUser->password));
    }

    public function test_created_users_can_authenticate(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Created Tester',
            'email' => 'created_tester@example.com',
            'password' => 'ValidPassword123!',
            'password_confirmation' => 'ValidPassword123!',
            'role' => 'tester',
        ]);

        $this->post('/logout');

        $response = $this->post('/login', [
            'email' => 'created_tester@example.com',
            'password' => 'ValidPassword123!',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
    }

    // --- TESTER TESTS ---

    public function test_tester_can_log_in(): void
    {
        $tester = User::factory()->tester()->create([
            'email' => 'tester_user@example.com',
            'password' => Hash::make('TesterPass123!'),
        ]);

        $response = $this->post('/login', [
            'email' => 'tester_user@example.com',
            'password' => 'TesterPass123!',
        ]);

        $this->assertAuthenticatedAs($tester);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_tester_can_access_permitted_assessment_functionality(): void
    {
        $tester = User::factory()->tester()->create();

        $this->actingAs($tester)->get('/dashboard')->assertStatus(200);
        $this->actingAs($tester)->get('/scans')->assertStatus(200);
        $this->actingAs($tester)->get('/scans/create')->assertStatus(200);
        $this->actingAs($tester)->get('/reports')->assertStatus(200);
        $this->actingAs($tester)->get('/settings')->assertStatus(200);
    }

    public function test_tester_does_not_see_user_management_in_navigation(): void
    {
        $tester = User::factory()->tester()->create();

        $response = $this->actingAs($tester)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertDontSee('User Management');
    }

    public function test_tester_cannot_access_user_management_index_and_receives_403(): void
    {
        $tester = User::factory()->tester()->create();

        $response = $this->actingAs($tester)->get('/users');

        $response->assertStatus(403);
    }

    public function test_tester_cannot_access_add_user_screen_and_receives_403(): void
    {
        $tester = User::factory()->tester()->create();

        $response = $this->actingAs($tester)->get('/users/create');

        $response->assertStatus(403);
    }

    public function test_tester_cannot_create_users_via_post_request(): void
    {
        $tester = User::factory()->tester()->create();

        $response = $this->actingAs($tester)->post('/users', [
            'name' => 'Unauthorized User',
            'email' => 'unauthorized@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'admin',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'unauthorized@example.com']);
    }

    public function test_tester_cannot_edit_or_update_users(): void
    {
        $tester = User::factory()->tester()->create();
        $targetUser = User::factory()->create();

        $this->actingAs($tester)->get("/users/{$targetUser->id}/edit")->assertStatus(403);

        $response = $this->actingAs($tester)->put("/users/{$targetUser->id}", [
            'name' => 'Modified Name',
            'email' => $targetUser->email,
            'role' => 'admin',
        ]);

        $response->assertStatus(403);
        $this->assertEquals($targetUser->name, $targetUser->fresh()->name);
    }

    public function test_tester_cannot_delete_users(): void
    {
        $tester = User::factory()->tester()->create();
        $targetUser = User::factory()->create();

        $response = $this->actingAs($tester)->delete("/users/{$targetUser->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $targetUser->id]);
    }

    // --- UNAUTHENTICATED TESTS ---

    public function test_unauthenticated_users_cannot_access_protected_user_management_routes(): void
    {
        $this->get('/users')->assertRedirect('/login');
        $this->get('/users/create')->assertRedirect('/login');
        $this->post('/users', [])->assertRedirect('/login');
    }
}
