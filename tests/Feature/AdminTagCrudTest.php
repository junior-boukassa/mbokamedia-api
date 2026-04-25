<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTagCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_manage_tags(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        $createResponse = $this->postJson('/api/admin/tags', [
            'name' => 'Edition Live',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.slug', 'edition-live');

        $tagId = $createResponse->json('data.id');

        $updateResponse = $this->putJson("/api/admin/tags/{$tagId}", [
            'name' => 'Edition Live Update',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Edition Live Update');

        $deleteResponse = $this->deleteJson("/api/admin/tags/{$tagId}");

        $deleteResponse->assertOk();
        $this->assertDatabaseMissing('tags', ['id' => $tagId]);
    }

    public function test_tag_requires_name(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/admin/tags', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }
}
