<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminFeaturedSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_featured_section(): void
    {
        $this->seed();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $article = Article::query()->first();
        $video = Video::query()->first();

        $createResponse = $this->postJson('/api/admin/featured-sections', [
            'name' => 'Sidebar Home',
            'headline' => 'A lire',
            'description' => 'Selection secondaire',
            'is_active' => true,
            'sort_order' => 2,
            'max_items' => 3,
            'items' => [
                [
                    'featureable_type' => 'article',
                    'featureable_id' => $article->id,
                    'sort_order' => 1,
                    'is_active' => true,
                ],
                [
                    'featureable_type' => 'video',
                    'featureable_id' => $video->id,
                    'sort_order' => 2,
                    'is_active' => true,
                ],
            ],
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.name', 'Sidebar Home');

        $sectionId = $createResponse->json('data.id');

        $updateResponse = $this->putJson("/api/admin/featured-sections/{$sectionId}", [
            'headline' => 'A lire maintenant',
            'is_active' => false,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.headline', 'A lire maintenant')
            ->assertJsonPath('data.is_active', false);

        $deleteResponse = $this->deleteJson("/api/admin/featured-sections/{$sectionId}");

        $deleteResponse->assertOk();
        $this->assertDatabaseMissing('featured_sections', ['id' => $sectionId]);
    }
}
