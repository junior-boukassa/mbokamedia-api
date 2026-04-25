<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminVideoCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_create_update_and_delete_video(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        $createResponse = $this->postJson('/api/admin/videos', [
            'title' => 'Video test Mboka',
            'description' => 'Description video',
            'thumbnail' => 'media/videos/test.jpg',
            'video_url' => 'https://example.com/video-test',
            'source_type' => 'external_url',
            'status' => 'published',
            'is_featured' => true,
            'published_at' => now()->toISOString(),
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.title', 'Video test Mboka');

        $videoId = $createResponse->json('data.id');

        $updateResponse = $this->putJson("/api/admin/videos/{$videoId}", [
            'title' => 'Video test Mboka MAJ',
            'status' => 'archived',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $deleteResponse = $this->deleteJson("/api/admin/videos/{$videoId}");

        $deleteResponse->assertOk();
        $this->assertSoftDeleted('videos', ['id' => $videoId]);
    }

    public function test_video_requires_valid_url(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/admin/videos', [
            'title' => 'Video invalide',
            'video_url' => 'not-a-url',
            'source_type' => 'external_url',
            'status' => 'published',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['video_url']);
    }
}
