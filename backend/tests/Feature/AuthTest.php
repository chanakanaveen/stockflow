<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_me_logout_and_secret_redaction(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['password' => 'test-password-123']);
        $user->assignRole('Inventory Manager');
        $this->postJson('/api/v1/auth/login', ['email' => strtoupper($user->email), 'password' => 'test-password-123'])
            ->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonMissingPath('data.password')
            ->assertJsonPath('data.roles.0', 'Inventory Manager')->assertJsonStructure(['meta' => ['request_id']]);
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        Auth::forgetGuards();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_wrong_password_and_inactive_account_cannot_login(): void
    {
        $user = User::factory()->create(['password' => 'test-password-123']);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
        $user->forceFill(['is_active' => false])->save();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'test-password-123'])->assertUnprocessable();
        $this->assertGuest('web');
    }

    public function test_existing_session_is_denied_after_deactivation(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->actingAs($user)->getJson('/api/v1/auth/me')->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');
        $this->assertGuest('web');
    }

    public function test_api_errors_are_json_without_accept_header_and_correlated(): void
    {
        $response = $this->get('/api/v1/auth/me')->assertUnauthorized();
        $this->assertSame($response->json('meta.request_id'), $response->headers->get('X-Request-ID'));
        $this->getJson('/api/v1/unknown')->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND')->assertJsonMissingPath('trace');
        $this->postJson('/api/v1/auth/login', [])->assertUnprocessable()->assertJsonPath('error.details.0.field', 'email');
    }

    public function test_bearer_token_is_not_an_alternative_to_a_session(): void
    {
        $this->withToken('1|untrusted')->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_csrf_is_required_outside_the_test_middleware_bypass(): void
    {
        $this->app->instance('env', 'local');
        $this->postJson('/api/v1/auth/login', ['email' => 'user@example.test', 'password' => 'anything'])
            ->assertStatus(419)->assertJsonPath('error.code', 'SESSION_EXPIRED');
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'incorrect'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'incorrect'])
            ->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMITED')->assertHeader('Retry-After');
    }
}
