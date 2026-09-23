<?php

namespace Tests\Feature;

use App\Models\AuthenticationConfiguration;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_authentication_is_stored_and_encrypted(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/scans', [
            'name' => 'Form Auth Scan',
            'target_url' => 'https://app.example.com',
            'environment' => 'staging',
            'auth_mode' => 'form',
            'login_url' => 'https://app.example.com/login',
            'username_field' => 'user_email',
            'password_field' => 'user_password',
            'username' => 'test_user',
            'password' => 'SuperSecretPass123!',
            'login_button_selector' => '#submit-btn',
            'logged_in_indicator' => 'Welcome Back',
            'logged_out_indicator' => 'Please Sign In',
            'authenticated_url' => 'https://app.example.com/dashboard',
        ]);

        $scan = Scan::firstWhere('name', 'Form Auth Scan');
        $this->assertNotNull($scan);

        $auth = $scan->authenticationConfiguration;
        $this->assertEquals('form', $auth->mode);
        $this->assertEquals('https://app.example.com/login', $auth->login_url);
        $this->assertEquals('user_email', $auth->username_field);
        $this->assertEquals('user_password', $auth->password_field);
        $this->assertEquals('test_user', $auth->username);
        $this->assertEquals('SuperSecretPass123!', $auth->password);
        $this->assertEquals('#submit-btn', $auth->login_button_selector);

        // Verify array serialization hides secret fields
        $array = $auth->toArray();
        $this->assertArrayNotHasKey('password', $array);
    }

    public function test_token_authentication_is_stored_and_encrypted(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/scans', [
            'name' => 'Token Auth Scan',
            'target_url' => 'https://api.example.com',
            'environment' => 'staging',
            'auth_mode' => 'token',
            'token_name' => 'X-API-Key',
            'token_value' => 'secret_api_token_value_999',
        ]);

        $scan = Scan::firstWhere('name', 'Token Auth Scan');
        $this->assertNotNull($scan);

        $auth = $scan->authenticationConfiguration;
        $this->assertEquals('token', $auth->mode);
        $this->assertEquals('X-API-Key', $auth->token_name);
        $this->assertEquals('secret_api_token_value_999', $auth->token_value);

        // Verify array serialization hides token secret
        $array = $auth->toArray();
        $this->assertArrayNotHasKey('token_value', $array);
    }

    public function test_ui_does_not_render_plaintext_passwords_or_tokens(): void
    {
        $user = User::factory()->create();

        $scan = Scan::create([
            'user_id' => $user->id,
            'name' => 'UI Secret Test',
            'target_url' => 'https://app.example.com',
            'environment' => 'staging',
            'status' => 'draft',
        ]);

        AuthenticationConfiguration::create([
            'scan_id' => $scan->id,
            'mode' => 'form',
            'login_url' => 'https://app.example.com/login',
            'username' => 'testuser',
            'password' => 'TOP_SECRET_PASSWORD_DO_NOT_EXPOSE',
        ]);

        $response = $this->actingAs($user)->get("/scans/{$scan->id}");

        $response->assertStatus(200);
        $response->assertDontSee('TOP_SECRET_PASSWORD_DO_NOT_EXPOSE');
        $response->assertSee('•••••••• [ENCRYPTED SECRET]');
    }
}
