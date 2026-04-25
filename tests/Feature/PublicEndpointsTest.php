<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
