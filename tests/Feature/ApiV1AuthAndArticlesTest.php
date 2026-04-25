<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiV1AuthAndArticlesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('jwt.secret', str_repeat('a', 64));
    }

    public function test_user_can_register_login_and_manage_articles_via_v1_api(): void
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

        $createResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/articles', [
                'title' => 'Mon premier article',
                'content' => 'Contenu de demonstration',
                'status' => 'published',
            ]);

        $articleId = $createResponse->json('data.id');

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.title', 'Mon premier article')
            ->assertJsonPath('data.status', 'published');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'api@example.com',
            'password' => 'Password123',
        ])->assertOk();

        $this->getJson('/api/v1/articles')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/articles/{$articleId}", [
                'title' => 'Article modifie',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Article modifie');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/v1/articles/{$articleId}")
            ->assertOk();
    }
}
