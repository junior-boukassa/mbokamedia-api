<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Token;

class ApiV1AuthAndArticlesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('jwt.secret', str_repeat('a', 64));
    }

    public function test_public_registration_does_not_grant_article_permissions(): void
    {
        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'API User',
            'email' => 'api@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $registerResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'refresh_token',
                    'token_type',
                    'expires_in',
                    'refresh_expires_in',
                    'user' => ['id', 'name', 'email'],
                ],
            ]);

        $token = $registerResponse->json('data.access_token');

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/articles', [
                'title' => 'Mon premier article',
                'content' => 'Contenu de demonstration',
                'status' => 'published',
            ])
            ->assertForbidden();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'api@example.com',
            'password' => 'Password123',
        ])->assertOk();

        $this->assertDatabaseCount('articles', 0);
    }

    public function test_public_v1_article_endpoints_only_return_published_articles(): void
    {
        $published = Article::factory()->create([
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $draft = Article::factory()->create([
            'status' => 'draft',
            'published_at' => null,
        ]);
        $scheduled = Article::factory()->create([
            'status' => 'scheduled',
            'published_at' => now()->addDay(),
        ]);

        $this->getJson('/api/v1/articles')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id);

        $this->getJson("/api/v1/articles/{$published->id}")->assertOk();
        $this->getJson("/api/v1/articles/{$draft->id}")->assertNotFound();
        $this->getJson("/api/v1/articles/{$scheduled->id}")->assertNotFound();
    }

    public function test_journalist_can_create_own_drafts_but_cannot_publish_or_mutate_another_article(): void
    {
        $this->seed();
        $category = Category::factory()->create();

        $journalist = User::factory()->create();
        $journalist->assignRole('journalist');
        $journalistToken = auth('api')->login($journalist);

        $draftResponse = $this->withToken($journalistToken)->postJson('/api/v1/articles', [
            'title' => 'Brouillon journaliste',
            'content' => 'Contenu de brouillon.',
            'category_id' => $category->id,
        ]);
        $draftResponse->assertCreated()->assertJsonPath('data.status', 'draft');
        $draftId = $draftResponse->json('data.id');

        $this->withToken($journalistToken)->patchJson("/api/v1/articles/{$draftId}", [
            'status' => 'published',
        ])->assertForbidden();

        $otherAuthor = User::factory()->create();
        $otherAuthor->assignRole('editor');
        $otherArticle = Article::factory()->create([
            'author_id' => $otherAuthor->id,
            'status' => 'draft',
            'published_at' => null,
        ]);
        $this->withToken($journalistToken)->patchJson("/api/v1/articles/{$otherArticle->id}", [
            'title' => 'Modification interdite',
        ])->assertForbidden();
        $this->withToken($journalistToken)->deleteJson("/api/v1/articles/{$otherArticle->id}")->assertForbidden();
    }

    public function test_journalist_cannot_edit_the_content_of_an_own_published_article(): void
    {
        $this->seed();
        $journalist = User::factory()->create();
        $journalist->assignRole('journalist');
        $journalistToken = auth('api')->login($journalist);
        $published = Article::factory()->create([
            'author_id' => $journalist->id,
            'title' => 'Titre publié',
            'content' => 'Texte déjà publié.',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $this->withToken($journalistToken)
            ->patchJson("/api/v1/articles/{$published->id}", [
                'content' => 'Modification non autorisée.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('articles', [
            'id' => $published->id,
            'content' => 'Texte déjà publié.',
            'status' => 'published',
        ]);
        $this->getJson("/api/v1/articles/{$published->id}")
            ->assertOk()
            ->assertJsonPath('data.content', 'Texte déjà publié.');
    }

    public function test_editor_with_article_permissions_can_publish_and_delete_v1_articles(): void
    {
        $this->seed();
        $category = Category::factory()->create();
        $editor = User::factory()->create();
        $editor->assignRole('editor');
        $editorToken = auth('api')->login($editor);

        $created = $this->withToken($editorToken)->postJson('/api/v1/articles', [
            'title' => 'Article éditeur',
            'content' => 'Contenu éditorial.',
            'category_id' => $category->id,
            'status' => 'published',
        ]);
        $created->assertCreated()->assertJsonPath('data.status', 'published');
        $articleId = $created->json('data.id');

        $this->withToken($editorToken)->patchJson("/api/v1/articles/{$articleId}", [
            'title' => 'Article modifié',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Article modifié')
            ->assertJsonPath('data.status', 'published');
        $this->withToken($editorToken)->deleteJson("/api/v1/articles/{$articleId}")->assertOk();
    }

    public function test_v1_article_pagination_is_capped(): void
    {
        $this->getJson('/api/v1/articles?per_page=5000')->assertOk()
            ->assertJsonPath('meta.pagination.per_page', 50);
    }

    public function test_inactive_accounts_cannot_use_existing_jwt_access_tokens(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = auth('api')->login($user);

        User::query()->whereKey($user->id)->update(['is_active' => false]);
        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'This account is inactive.');
    }

    public function test_inactive_accounts_cannot_refresh_jwt_tokens(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $token = auth('api')->login($user);

        $this->withToken($token)
            ->postJson('/api/v1/auth/refresh')
            ->assertForbidden()
            ->assertJsonPath('message', 'This account is inactive.')
            ->assertJsonMissingPath('data.access_token');
    }

    public function test_jwt_logout_revokes_the_access_token(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = auth('api')->login($user);

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_jwt_refresh_rotates_the_access_token(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = auth('api')->login($user);

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/refresh')
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer');
        $refreshedToken = $response->json('data.access_token');
        $this->assertNotSame($token, $refreshedToken);
        $oldPayload = app('tymon.jwt.manager')->decode(new Token($token), false);
        $this->assertTrue(app('tymon.jwt.blacklist')->has($oldPayload));
    }

    public function test_v1_registration_is_rate_limited(): void
    {
        config()->set('api.rate_limits.auth_sensitive', 1);

        $payload = [
            'name' => 'API User',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ];

        $this->postJson('/api/v1/auth/register', [...$payload, 'email' => 'first@example.com'])
            ->assertCreated();
        $this->postJson('/api/v1/auth/register', [...$payload, 'email' => 'second@example.com'])
            ->assertStatus(429);
    }

    public function test_authenticated_article_mutations_are_rate_limited(): void
    {
        $this->seed();
        config()->set('api.rate_limits.api', 1);
        $editor = User::factory()->create();
        $editor->assignRole('editor');
        $token = auth('api')->login($editor);
        $payload = [
            'title' => 'Article éditeur',
            'content' => 'Contenu éditorial.',
            'status' => 'draft',
        ];

        $this->withToken($token)->postJson('/api/v1/articles', $payload)
            ->assertCreated();
        $this->withToken($token)->postJson('/api/v1/articles', [...$payload, 'title' => 'Autre article'])
            ->assertStatus(429);
    }
}
