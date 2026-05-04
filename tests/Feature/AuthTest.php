<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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

    public function test_admin_login_starts_otp_challenge(): void
    {
        $this->seed();
        Notification::fake();

        $response = $this->postJson('/api/auth/login', [
            'email' => $this->defaultAdminEmail(),
            'password' => $this->defaultAdminPassword(),
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['otp_required', 'email', 'masked_email', 'expires_in_minutes'],
            ])
            ->assertJsonPath('data.otp_required', true);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'auth.otp_challenge_sent',
            'actor_email' => $this->defaultAdminEmail(),
            'status' => 'success',
        ]);
    }

    public function test_all_allowed_admin_roles_can_start_otp_login(): void
    {
        $this->seed();
        Notification::fake();

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
                ->assertJsonPath('data.otp_required', true);
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
        Notification::fake();

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
        Notification::fake();

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

    public function test_admin_can_verify_valid_otp_and_receive_token(): void
    {
        $this->seed();

        $user = User::factory()->create([
            'email' => 'otp-valid@example.com',
            'password' => 'password123',
            'is_active' => true,
            'otp_code_hash' => Hash::make('123456'),
            'otp_expires_at' => now()->addMinutes(10),
            'otp_attempts' => 0,
        ]);
        $user->assignRole('admin');

        $response = $this->postJson('/api/auth/verify-otp', [
            'email' => $user->email,
            'code' => '123456',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['token', 'token_type', 'user'],
            ]);
    }

    public function test_expired_otp_is_rejected(): void
    {
        $this->seed();

        $user = User::factory()->create([
            'email' => 'otp-expired@example.com',
            'password' => 'password123',
            'is_active' => true,
            'otp_code_hash' => Hash::make('123456'),
            'otp_expires_at' => now()->subMinute(),
            'otp_attempts' => 0,
        ]);
        $user->assignRole('admin');

        $response = $this->postJson('/api/auth/verify-otp', [
            'email' => $user->email,
            'code' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'This OTP code has expired. Please request a new code.');
    }

    public function test_wrong_otp_increments_attempts_and_is_rejected(): void
    {
        $this->seed();

        $user = User::factory()->create([
            'email' => 'otp-wrong@example.com',
            'password' => 'password123',
            'is_active' => true,
            'otp_code_hash' => Hash::make('123456'),
            'otp_expires_at' => now()->addMinutes(10),
            'otp_attempts' => 0,
        ]);
        $user->assignRole('admin');

        $response = $this->postJson('/api/auth/verify-otp', [
            'email' => $user->email,
            'code' => '654321',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Invalid OTP code.');
        $this->assertSame(1, $user->fresh()->otp_attempts);
    }
}
