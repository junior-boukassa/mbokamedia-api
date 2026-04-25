<?php

namespace Tests\Feature;

use App\Models\BreakingNews;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBreakingNewsCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_manage_breaking_news(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        $createResponse = $this->postJson('/api/admin/breaking-news', [
            'title' => 'Flash Mboka',
            'link' => 'https://example.com/flash-mboka',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $createResponse->assertCreated();
        $breakingNewsId = $createResponse->json('data.id');

        $updateResponse = $this->putJson("/api/admin/breaking-news/{$breakingNewsId}", [
            'title' => 'Flash Mboka MAJ',
            'is_active' => false,
            'sort_order' => 5,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.title', 'Flash Mboka MAJ')
            ->assertJsonPath('data.is_active', false);

        $deleteResponse = $this->deleteJson("/api/admin/breaking-news/{$breakingNewsId}");

        $deleteResponse->assertOk();
        $this->assertDatabaseMissing('breaking_news', ['id' => $breakingNewsId]);
    }

    public function test_breaking_news_requires_valid_link_when_present(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/admin/breaking-news', [
            'title' => 'Flash invalide',
            'link' => 'bad-link',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['link']);
    }
}
