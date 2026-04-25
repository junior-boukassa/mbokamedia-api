<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_user_roles(): void
    {
        $this->seed();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $createResponse = $this->postJson('/api/admin/users', [
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => 'password123',
            'is_active' => true,
            'roles' => ['community_manager'],
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.email', 'new-user@example.com');

        $userId = $createResponse->json('data.id');

        $updateResponse = $this->putJson("/api/admin/users/{$userId}", [
            'name' => 'New User Updated',
            'is_active' => false,
            'roles' => ['editor'],
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'New User Updated')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_admin_cannot_assign_super_admin_role(): void
    {
        $this->seed();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/users', [
            'name' => 'Elevated User',
            'email' => 'elevated@example.com',
            'password' => 'password123',
            'is_active' => true,
            'roles' => ['super_admin'],
        ]);

        $response->assertForbidden();
    }

    public function test_non_super_admin_cannot_manage_users(): void
    {
        $this->seed();

        $journalist = User::factory()->create();
        $journalist->assignRole('journalist');
        Sanctum::actingAs($journalist);

        $response = $this->getJson('/api/admin/users');

        $response->assertForbidden();
    }

    public function test_admin_cannot_view_super_admin_accounts_in_user_listing(): void
    {
        $this->seed();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $superAdmin = User::factory()->create([
            'email' => 'super-admin@example.com',
        ]);
        $superAdmin->assignRole('super_admin');

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users');

        $response->assertOk();
        $this->assertNotContains($superAdmin->email, collect($response->json('data'))->pluck('email')->all());
    }

    public function test_role_change_revokes_existing_tokens(): void
    {
        $this->seed();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $managedUser = User::factory()->create();
        $managedUser->assignRole('editor');
        $managedUser->createToken('admin-panel');

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/admin/users/{$managedUser->id}", [
            'roles' => ['journalist'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.roles.0', 'journalist');
        $this->assertSame(0, $managedUser->fresh()->tokens()->count());
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'user.roles_changed',
            'target_id' => (string) $managedUser->id,
        ]);
    }

    public function test_deactivation_revokes_existing_tokens(): void
    {
        $this->seed();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $managedUser = User::factory()->create([
            'is_active' => true,
        ]);
        $managedUser->assignRole('editor');
        $managedUser->createToken('admin-panel');

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/admin/users/{$managedUser->id}", [
            'is_active' => false,
            'roles' => ['editor'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);
        $this->assertSame(0, $managedUser->fresh()->tokens()->count());
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'user.deactivated',
            'target_id' => (string) $managedUser->id,
        ]);
    }
}
