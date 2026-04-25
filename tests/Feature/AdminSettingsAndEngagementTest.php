<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSettingsAndEngagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_update_settings(): void
    {
        $this->seed();

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        Sanctum::actingAs($superAdmin);

        $settingsResponse = $this->putJson('/api/admin/settings', [
            'site_name' => 'Mboka Media Updated',
            'site_slogan' => 'Media digital panafricain',
            'primary_email' => 'contact@mbokamedia.com',
            'primary_phone' => '+243000000000',
            'social_links' => [
                'facebook' => 'https://facebook.com/mbokamedia',
            ],
            'seo_title' => 'Mboka Media',
        ]);

        $settingsResponse->assertOk()
            ->assertJsonPath('data.site_name', 'Mboka Media Updated');
    }

    public function test_admin_can_manage_contact_and_newsletter_statuses_but_not_settings(): void
    {
        $this->seed();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        $settingsResponse = $this->putJson('/api/admin/settings', [
            'site_name' => 'Mboka Media Updated',
        ]);

        $settingsResponse->assertForbidden();

        $contact = Contact::query()->create([
            'name' => 'Contact test',
            'email' => 'contact@test.com',
            'subject' => 'Sujet',
            'message' => 'Message de test suffisamment long.',
        ]);

        $contactResponse = $this->patchJson("/api/admin/contacts/{$contact->id}", [
            'status' => 'read',
        ]);

        $contactResponse->assertOk()
            ->assertJsonPath('data.status', 'read');

        $subscriber = NewsletterSubscriber::query()->create([
            'email' => 'subscriber@test.com',
            'status' => 'subscribed',
            'subscribed_at' => now(),
        ]);

        $subscriberResponse = $this->patchJson("/api/admin/newsletter-subscribers/{$subscriber->id}", [
            'status' => 'unsubscribed',
        ]);

        $subscriberResponse->assertOk()
            ->assertJsonPath('data.status', 'unsubscribed');
    }

    public function test_settings_validation_requires_site_name(): void
    {
        $this->seed();

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        Sanctum::actingAs($superAdmin);

        $response = $this->putJson('/api/admin/settings', [
            'site_name' => '',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['site_name']);
    }
}
