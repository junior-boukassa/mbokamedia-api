<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Live;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LiveAndReelsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_live_returns_active_live_only(): void
    {
        $this->seed();

        Live::query()->create([
            'title' => 'Scheduled live',
            'slug' => 'scheduled-live',
            'stream_type' => 'hls',
            'stream_url' => 'https://example.com/live.m3u8',
            'status' => 'scheduled',
            'scheduled_at' => now()->addHour(),
        ]);

        $active = Live::query()->create([
            'title' => 'Live journal',
            'slug' => 'live-journal',
            'description' => 'Journal du soir',
            'thumbnail' => 'media/lives/live.jpg',
            'stream_type' => 'hls',
            'stream_url' => 'https://example.com/hls/live.m3u8',
            'status' => 'live',
            'started_at' => now()->subMinutes(10),
        ]);

        $response = $this->getJson('/api/lives/current');

        $response->assertOk()
            ->assertJsonPath('data.id', $active->id)
            ->assertJsonPath('data.status', 'live');
    }

    public function test_current_live_returns_null_when_no_live_exists(): void
    {
        $this->seed();

        $this->getJson('/api/lives/current')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_public_reels_endpoint_returns_only_reels_and_published_items(): void
    {
        $this->seed();

        Video::query()->create([
            'title' => 'Classic video',
            'slug' => 'classic-video',
            'video_url' => 'https://example.com/video.mp4',
            'source_type' => 'upload',
            'status' => ContentStatus::Published->value,
            'published_at' => now()->subDay(),
            'thumbnail' => 'media/videos/classic.jpg',
            'is_reel' => false,
        ]);

        Video::query()->create([
            'title' => 'Reel featured',
            'slug' => 'reel-featured',
            'video_url' => 'https://example.com/reel.mp4',
            'source_type' => 'upload',
            'status' => ContentStatus::Published->value,
            'published_at' => now()->subHour(),
            'thumbnail' => 'media/reels/reel.jpg',
            'is_reel' => true,
            'is_featured' => true,
            'views_count' => 1250,
        ]);

        Video::query()->create([
            'title' => 'Draft reel',
            'slug' => 'draft-reel',
            'video_url' => 'https://example.com/draft.mp4',
            'source_type' => 'upload',
            'status' => ContentStatus::Draft->value,
            'published_at' => null,
            'thumbnail' => 'media/reels/draft.jpg',
            'is_reel' => true,
            'views_count' => 3,
        ]);

        $response = $this->getJson('/api/public/reels?per_page=5');

        $response->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.slug', 'reel-featured')
            ->assertJsonPath('data.0.source_type', 'upload')
            ->assertJsonPath('data.0.views_count', 1250);
    }

    public function test_admin_can_create_live(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/admin/lives', [
            'title' => 'Journal du soir',
            'description' => 'Le journal en direct.',
            'thumbnail' => 'media/lives/journal.jpg',
            'stream_type' => 'hls',
            'stream_url' => 'https://cdn.example.com/live.m3u8',
            'status' => 'live',
            'started_at' => now()->toISOString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Journal du soir')
            ->assertJsonPath('data.stream_type', 'hls');
    }

    public function test_invalid_live_stream_type_is_rejected(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        $this->postJson('/api/admin/lives', [
            'title' => 'Bad stream',
            'stream_type' => 'invalid-type',
            'stream_url' => 'https://example.com/live.m3u8',
            'status' => 'live',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['stream_type']);
    }
}
