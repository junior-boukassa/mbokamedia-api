<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_consult_audit_logs(): void
    {
        $this->seed();

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        AdminAuditLog::query()->create([
            'actor_id' => $superAdmin->id,
            'actor_name' => $superAdmin->name,
            'actor_email' => $superAdmin->email,
            'actor_roles' => ['super_admin'],
            'action' => 'auth.login_succeeded',
            'target_type' => 'auth',
            'status' => 'success',
            'created_at' => now(),
        ]);

        Sanctum::actingAs($superAdmin);

        $response = $this->getJson('/api/admin/audit-logs');

        $response->assertOk()
            ->assertJsonPath('data.0.action', 'auth.login_succeeded');
    }

    public function test_admin_cannot_consult_audit_logs(): void
    {
        $this->seed();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/audit-logs');

        $response->assertForbidden();
    }

    public function test_audit_logs_can_be_filtered_by_actor_action_and_date(): void
    {
        $this->seed();

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $otherUser = User::factory()->create();
        $otherUser->assignRole('editor');

        AdminAuditLog::query()->create([
            'actor_id' => $otherUser->id,
            'actor_name' => $otherUser->name,
            'actor_email' => $otherUser->email,
            'actor_roles' => ['editor'],
            'action' => 'article.updated',
            'target_type' => 'article',
            'target_id' => '12',
            'status' => 'success',
            'created_at' => now(),
        ]);
        AdminAuditLog::query()->create([
            'actor_id' => $superAdmin->id,
            'actor_name' => $superAdmin->name,
            'actor_email' => $superAdmin->email,
            'actor_roles' => ['super_admin'],
            'action' => 'user.updated',
            'target_type' => 'user',
            'target_id' => '44',
            'status' => 'success',
            'created_at' => now()->subDays(2),
        ]);

        Sanctum::actingAs($superAdmin);

        $response = $this->getJson(sprintf(
            '/api/admin/audit-logs?actor_id=%d&action=article.updated&date_from=%s',
            $otherUser->id,
            now()->toDateString(),
        ));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'article.updated');
    }
}
