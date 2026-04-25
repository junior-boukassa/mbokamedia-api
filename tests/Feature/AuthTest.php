<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function defaultAdminEmail(): string
    {
        return env('DEFAULT_ADMIN_EMAIL', 'local-admin@example.test');
    }

    private function defaultAdminPassword(): string
    {
        return env('DEFAULT_ADMIN_PASSWORD', 'ChangeMe123!');
    }

    public function test_admin_can_login_and_receive_token(): void
    {
        $this->seed();

        $response = $this->postJson('/api/auth/login', [
            'email' => $this->defaultAdminEmail(),
            'password' => $this->defaultAdminPassword(),
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['token', 'token_type', 'user'],
            ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'auth.login_succeeded',
            'actor_email' => $this->defaultAdminEmail(),
            'status' => 'success',
        ]);
    }

    public function test_all_allowed_admin_roles_can_login(): void
    {
        $this->seed();

        foreach (config('admin.allowed_roles') as $roleName) {
            $user = User::factory()->create([
                'email' => sprintf('%s@example.com', $roleName),
                'password' => 'password123',
                'is_active' => true,
            ]);
            $user->assignRole($roleName);

            $response = $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'password123',
                'device_name' => 'phpunit',
            ]);

            $response->assertOk()
                ->assertJsonPath('data.user.roles.0', $roleName);
        }
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/auth/me');

        $response->assertOk()->assertJsonPath('data.email', $user->email);
    }

    public function test_active_user_without_admin_role_cannot_login_to_admin(): void
    {
        $user = User::factory()->create([
            'email' => 'reader@example.com',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
            'device_name' => 'phpunit',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'This account is not authorized to access the admin panel.');

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'auth.login_denied',
            'actor_email' => $user->email,
            'status' => 'denied',
        ]);
    }

    public function test_login_is_rate_limited_after_too_many_attempts(): void
    {
        foreach (range(1, 5) as $attempt) {
            $response = $this->postJson('/api/auth/login', [
                'email' => $this->defaultAdminEmail(),
                'password' => 'wrong-password',
                'device_name' => 'phpunit',
            ]);

            $response->assertUnauthorized();
        }

        $response = $this->postJson('/api/auth/login', [
            'email' => $this->defaultAdminEmail(),
            'password' => 'wrong-password',
            'device_name' => 'phpunit',
        ]);

        $response->assertStatus(429);
    }

    public function test_non_admin_authenticated_user_cannot_access_admin_routes(): void
    {
        $this->seed();

        $reader = User::factory()->create([
            'is_active' => true,
        ]);

        $response = $this->actingAs($reader, 'sanctum')->getJson('/api/admin/dashboard/stats');

        $response->assertForbidden()
            ->assertJsonPath('message', 'This account is not authorized to access the admin panel.');
    }

    public function test_logout_creates_audit_log(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->assignRole('editor');
        $token = $user->createToken('phpunit')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $response->assertOk();

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'auth.logout',
            'actor_id' => $user->id,
            'status' => 'success',
        ]);
    }
}
