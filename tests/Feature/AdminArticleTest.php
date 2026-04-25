<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
