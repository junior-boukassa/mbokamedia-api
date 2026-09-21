<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_articles_endpoint_returns_success_wrapper(): void
    {
        $this->seed();

        $response = $this->getJson('/api/public/articles');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta',
            ]);
    }

    public function test_public_article_author_includes_an_absolute_avatar_url(): void
    {
        config()->set('filesystems.disks.public.url', 'https://api.mbokamedia.com/storage');
        Storage::fake('public');
        Storage::disk('public')->put('avatars/profile.webp', 'avatar');

        $author = User::factory()->create([
            'avatar_path' => '/storage/avatars/profile.webp',
        ]);
        Article::factory()->create([
            'author_id' => $author->id,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/public/articles');

        $response->assertOk()->assertJsonPath(
            'data.0.author.avatar_url',
            'https://api.mbokamedia.com/storage/avatars/profile.webp',
        );
    }

    public function test_newsletter_subscription_is_idempotent(): void
    {
        $first = $this->postJson('/api/public/newsletter/subscribe', [
            'email' => 'newsletter@example.com',
        ]);

        $second = $this->postJson('/api/public/newsletter/subscribe', [
            'email' => 'newsletter@example.com',
        ]);

        $first->assertCreated();
        $second->assertCreated();
    }
}
