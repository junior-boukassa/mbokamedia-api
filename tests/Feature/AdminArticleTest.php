<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Medium;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_create_article(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        $category = Category::factory()->create();

        $response = $this->postJson('/api/admin/articles', [
            'title' => 'Premier article Mboka',
            'excerpt' => 'Un résumé éditorial.',
            'content' => 'Contenu complet de test pour la rédaction.',
            'status' => 'published',
            'category_id' => $category->id,
            'published_at' => now()->toISOString(),
            'is_featured' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Premier article Mboka');

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'article.created',
            'target_type' => 'article',
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'article.published',
            'target_type' => 'article',
            'status' => 'success',
        ]);
    }

    public function test_articles_can_be_created_with_zero_one_or_two_additional_library_images(): void
    {
        $this->seed();
        Storage::fake('public');

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);
        $category = Category::factory()->create();
        $firstImage = $this->createImageMedia('media/first.jpg');
        $secondImage = $this->createImageMedia('media/second.jpg');

        $basePayload = [
            'content' => 'Contenu complet suffisant pour un article de test.',
            'status' => 'draft',
            'category_id' => $category->id,
        ];

        $this->postJson('/api/admin/articles', [
            ...$basePayload,
            'title' => 'Article sans image secondaire',
        ])->assertCreated()
            ->assertJsonCount(0, 'data.additional_images');

        $this->postJson('/api/admin/articles', [
            ...$basePayload,
            'title' => 'Article avec une image secondaire',
            'additional_images' => [
                [
                    'path' => $firstImage->path,
                    'alt_text' => 'Vue de la ville',
                    'caption' => 'Une légende éditoriale.',
                    'credit' => 'Mboka Média',
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.additional_images.0.path', $firstImage->path)
            ->assertJsonPath('data.additional_images.0.alt_text', 'Vue de la ville')
            ->assertJsonPath('data.additional_images.0.caption', 'Une légende éditoriale.')
            ->assertJsonPath('data.additional_images.0.credit', 'Mboka Média');

        $this->postJson('/api/admin/articles', [
            ...$basePayload,
            'title' => 'Article avec deux images secondaires',
            'additional_images' => [
                ['path' => $firstImage->path],
                ['path' => $secondImage->path],
            ],
        ])->assertCreated()
            ->assertJsonCount(2, 'data.additional_images');

        Storage::disk('public')->assertExists($firstImage->path);
        Storage::disk('public')->assertExists($secondImage->path);
    }

    public function test_replacing_or_removing_additional_images_never_deletes_shared_media(): void
    {
        $this->seed();
        Storage::fake('public');

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);
        $oldImage = $this->createImageMedia('media/shared-old.jpg');
        $replacementImage = $this->createImageMedia('media/shared-new.jpg');
        $article = Article::factory()->create([
            'author_id' => $user->id,
            'additional_images' => [
                ['path' => $oldImage->path, 'caption' => 'Ancienne légende'],
            ],
        ]);

        $this->putJson("/api/admin/articles/{$article->id}", [
            'additional_images' => [
                ['path' => $replacementImage->path, 'caption' => 'Nouvelle légende'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.additional_images.0.path', $replacementImage->path);

        $this->assertSame(
            [['path' => $replacementImage->path, 'caption' => 'Nouvelle légende']],
            $article->fresh()->additional_images,
        );

        $this->putJson("/api/admin/articles/{$article->id}", [
            'additional_images' => [],
        ])->assertOk()->assertJsonCount(0, 'data.additional_images');

        Storage::disk('public')->assertExists($oldImage->path);
        Storage::disk('public')->assertExists($replacementImage->path);
        $this->assertDatabaseHas('media', ['id' => $oldImage->id]);
        $this->assertDatabaseHas('media', ['id' => $replacementImage->id]);
    }

    public function test_additional_images_must_be_public_library_images_and_cannot_exceed_two(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);
        $category = Category::factory()->create();
        $firstImage = $this->createImageMedia('media/valid.jpg');
        $secondImage = $this->createImageMedia('media/valid-2.jpg');
        $video = Medium::query()->create([
            'name' => 'video.mp4',
            'disk' => 'public',
            'path' => 'media/video.mp4',
            'url' => 'https://api.mbokamedia.com/storage/media/video.mp4',
            'mime_type' => 'video/mp4',
            'extension' => 'mp4',
            'size' => 100,
            'type' => 'video',
        ]);
        $basePayload = [
            'title' => 'Article images invalides',
            'content' => 'Contenu suffisant pour valider les règles de la requête.',
            'status' => 'draft',
            'category_id' => $category->id,
        ];

        $this->postJson('/api/admin/articles', [
            ...$basePayload,
            'additional_images' => [
                ['path' => $firstImage->path],
                ['path' => $secondImage->path],
                ['path' => 'media/third.jpg'],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['additional_images']);

        $this->postJson('/api/admin/articles', [
            ...$basePayload,
            'additional_images' => [['path' => $video->path]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['additional_images.0.path']);

        $this->postJson('/api/admin/articles', [
            ...$basePayload,
            'additional_images' => [['path' => '../../outside.jpg']],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['additional_images.0.path']);
    }

    public function test_editor_can_update_and_delete_article(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        $article = Article::factory()->create([
            'author_id' => $user->id,
        ]);

        $updateResponse = $this->putJson("/api/admin/articles/{$article->id}", [
            'title' => 'Article mis a jour',
            'content' => 'Contenu mis a jour',
            'status' => 'archived',
            'category_id' => $article->category_id,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.title', 'Article mis a jour')
            ->assertJsonPath('data.status', 'archived');

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'article.updated',
            'target_id' => (string) $article->id,
        ]);

        $deleteResponse = $this->deleteJson("/api/admin/articles/{$article->id}");

        $deleteResponse->assertOk();
        $this->assertSoftDeleted('articles', ['id' => $article->id]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'article.deleted',
            'target_id' => (string) $article->id,
        ]);
    }

    public function test_article_creation_requires_category_and_valid_status(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/admin/articles', [
            'title' => 'Article invalide',
            'content' => 'Contenu incomplet',
            'status' => 'invalid-status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'category_id']);
    }

    public function test_journalist_cannot_override_article_author(): void
    {
        $this->seed();

        $journalist = User::factory()->create();
        $journalist->assignRole('journalist');
        Sanctum::actingAs($journalist);

        $category = Category::factory()->create();
        $otherAuthor = User::factory()->create();
        $otherAuthor->assignRole('editor');

        $response = $this->postJson('/api/admin/articles', [
            'title' => 'Article attribue au mauvais auteur',
            'content' => 'Contenu complet de test pour verifier la protection de author_id.',
            'status' => 'draft',
            'category_id' => $category->id,
            'author_id' => $otherAuthor->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.author.id', $journalist->id);
    }

    public function test_journalist_cannot_change_author_when_updating_own_article(): void
    {
        $this->seed();

        $journalist = User::factory()->create();
        $journalist->assignRole('journalist');
        Sanctum::actingAs($journalist);

        $otherAuthor = User::factory()->create();
        $otherAuthor->assignRole('editor');
        $article = Article::factory()->create([
            'author_id' => $journalist->id,
            'status' => 'draft',
            'published_at' => null,
        ]);

        $response = $this->putJson("/api/admin/articles/{$article->id}", [
            'title' => 'Article mis a jour par le journaliste',
            'content' => 'Contenu mis a jour de maniere legitime sans changer le veritable auteur.',
            'category_id' => $article->category_id,
            'author_id' => $otherAuthor->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.author.id', $journalist->id);
    }

    public function test_journalist_can_create_drafts_but_cannot_publish_them(): void
    {
        $this->seed();

        $journalist = User::factory()->create();
        $journalist->assignRole('journalist');
        Sanctum::actingAs($journalist);
        $category = Category::factory()->create();

        $draft = $this->postJson('/api/admin/articles', [
            'title' => 'Brouillon journaliste',
            'content' => 'Contenu de brouillon.',
            'status' => 'draft',
            'category_id' => $category->id,
        ]);
        $draft->assertCreated()->assertJsonPath('data.status', 'draft');

        $this->postJson('/api/admin/articles', [
            'title' => 'Publication interdite',
            'content' => 'Contenu non autorisé.',
            'status' => 'published',
            'category_id' => $category->id,
        ])->assertForbidden();

        $this->patchJson('/api/admin/articles/'.$draft->json('data.id'), [
            'status' => 'scheduled',
            'published_at' => now()->addDay()->toISOString(),
        ])->assertForbidden();
    }

    public function test_journalist_cannot_edit_own_published_article_and_editor_patch_preserves_publication(): void
    {
        $this->seed();
        $journalist = User::factory()->create();
        $journalist->assignRole('journalist');
        Sanctum::actingAs($journalist);
        $article = Article::factory()->create([
            'author_id' => $journalist->id,
            'title' => 'Article publié',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $this->patchJson("/api/admin/articles/{$article->id}", [
            'content' => 'Modification non autorisée.',
        ])->assertForbidden();
        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'title' => 'Article publié',
            'status' => 'published',
        ]);

        $editor = User::factory()->create();
        $editor->assignRole('editor');
        Sanctum::actingAs($editor);
        $publishedAt = $article->published_at->toISOString();

        $this->patchJson("/api/admin/articles/{$article->id}", [
            'title' => 'Titre corrigé',
        ])->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.title', 'Titre corrigé');

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'title' => 'Titre corrigé',
            'status' => 'published',
        ]);
        $this->assertSame($publishedAt, $article->fresh()->published_at->toISOString());
    }

    private function createImageMedia(string $path): Medium
    {
        Storage::disk('public')->put($path, 'isolated image test fixture');

        return Medium::query()->create([
            'name' => basename($path),
            'disk' => 'public',
            'path' => $path,
            'url' => 'https://api.mbokamedia.com/storage/'.$path,
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => 27,
            'type' => 'image',
        ]);
    }
}
