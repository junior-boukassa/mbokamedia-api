<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAdvertisingRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_request_can_be_created_and_admin_can_manage_it(): void
    {
        $this->seed();
        Notification::fake();

        $createResponse = $this->postJson('/api/advertising-requests', [
            'full_name' => 'Client Test',
            'email' => 'client@example.com',
            'phone' => '+243810000000',
            'company_name' => 'Entreprise Test',
            'website_url' => 'https://example.com',
            'article_title' => 'Lancement produit',
            'description' => 'Description detaillee du produit et de la campagne annonceur.',
            'image_url' => 'https://example.com/logo.png',
            'package_type' => 'standard',
            'message' => 'Message commercial complementaire.',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.package_price', 50);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $requestId = $createResponse->json('data.id');

        $this->getJson('/api/admin/advertising-requests')
            ->assertOk();

        $this->getJson("/api/admin/advertising-requests/{$requestId}")
            ->assertOk()
            ->assertJsonPath('data.company_name', 'Entreprise Test');

        $this->patchJson("/api/admin/advertising-requests/{$requestId}/status", [
            'status' => 'contacted',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'contacted');
    }
}
