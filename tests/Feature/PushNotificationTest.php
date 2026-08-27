<?php

namespace Tests\Feature;

use App\Jobs\SendArticlePushNotification;
use App\Models\ArticleNotification;
use App\Models\Category;
use App\Models\Device;
use App\Models\User;
use App\Services\Fcm\FcmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Enregistrement des appareils ────────────────────────────────────────

    public function test_device_registration_is_idempotent(): void
    {
        $payload = [
            'token' => str_repeat('a', 64),
            'platform' => 'android',
            'app_version' => '1.1.1+5',
            'locale' => 'fr-CD',
        ];

        $this->postJson('/api/public/devices', $payload)->assertCreated();
        $this->postJson('/api/public/devices', $payload)->assertCreated();

        // L'application ré-enregistre à chaque lancement : une seule ligne.
        $this->assertSame(1, Device::query()->count());
    }

    public function test_device_registration_rejects_unknown_platform(): void
    {
        $this->postJson('/api/public/devices', [
            'token' => str_repeat('a', 64),
            'platform' => 'windows',
        ])->assertStatus(422);
    }

    // ─── Traductions ────────────────────────────────────────────────────────

    public function test_validation_errors_are_returned_in_french(): void
    {
        // L'API renvoyait la clé brute « validation.email », que l'application
        // affichait telle quelle sous le champ.
        $response = $this->postJson('/api/public/newsletter/subscribe', [
            'email' => 'pas-un-email',
        ]);

        $response->assertStatus(422);

        $message = $response->json('errors.email.0');

        $this->assertIsString($message);
        $this->assertStringNotContainsString('validation.', $message);
        $this->assertStringContainsString('adresse e-mail', $message);
    }

    // ─── Déclenchement à la publication ─────────────────────────────────────

    public function test_publishing_an_article_queues_a_push(): void
    {
        $this->seed();
        Bus::fake();

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        $this->postJson('/api/admin/articles', [
            'title' => 'Kinshasa relance le transport fluvial',
            'excerpt' => 'Quatre embarcadères rouvrent dès lundi.',
            'content' => 'Contenu complet de l’article.',
            'status' => 'published',
            'category_id' => Category::factory()->create()->id,
            'published_at' => now()->toISOString(),
        ])->assertCreated();

        Bus::assertDispatched(SendArticlePushNotification::class);
    }

    public function test_draft_article_does_not_queue_a_push(): void
    {
        $this->seed();
        Bus::fake();

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        $this->postJson('/api/admin/articles', [
            'title' => 'Brouillon en cours',
            'excerpt' => 'Pas encore publié.',
            'content' => 'Contenu complet de l’article.',
            'status' => 'draft',
            'category_id' => Category::factory()->create()->id,
        ])->assertCreated();

        Bus::assertNotDispatched(SendArticlePushNotification::class);
    }

    // ─── Envoi effectif ─────────────────────────────────────────────────────

    public function test_job_sends_the_expected_payload_to_fcm(): void
    {
        $this->configureFirebase();

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-de-test']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/test/messages/1']),
        ]);

        $notification = ArticleNotification::factory()->create([
            'title' => 'Kinshasa relance le transport fluvial',
            'slug' => 'kinshasa-relance-transport-fluvial',
            'excerpt' => 'Quatre embarcadères rouvrent dès lundi.',
        ]);

        (new SendArticlePushNotification($notification))->handle(app(FcmClient::class));

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'fcm.googleapis.com')) {
                return false;
            }

            $message = $request->data()['message'];

            return $message['topic'] === 'all-users'
                && $message['data']['slug'] === 'kinshasa-relance-transport-fluvial'
                && $message['data']['click_action'] === 'FLUTTER_NOTIFICATION_CLICK'
                && $message['android']['notification']['channel_id'] === 'mboka_articles'
                && $message['notification']['title'] === 'Kinshasa relance le transport fluvial';
        });

        $this->assertNotNull($notification->fresh()->push_sent_at);
    }

    public function test_job_does_not_send_twice(): void
    {
        $this->configureFirebase();
        Http::fake();

        $notification = ArticleNotification::factory()->create([
            'push_sent_at' => now(),
        ]);

        (new SendArticlePushNotification($notification))->handle(app(FcmClient::class));

        Http::assertNothingSent();
    }

    public function test_job_is_skipped_when_firebase_is_not_configured(): void
    {
        config()->set('services.firebase.project_id', null);
        config()->set('services.firebase.credentials', null);
        Http::fake();

        $notification = ArticleNotification::factory()->create();

        (new SendArticlePushNotification($notification))->handle(app(FcmClient::class));

        // Aucune tentative réseau, et la publication n'est pas marquée envoyée.
        Http::assertNothingSent();
        $this->assertNull($notification->fresh()->push_sent_at);
    }

    /**
     * Écrit une fausse clé de compte de service, le temps du test.
     */
    protected function configureFirebase(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($key, $privateKey);

        $path = storage_path('framework/testing/firebase-test.json');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode([
            'client_email' => 'test@mboka-media.iam.gserviceaccount.com',
            'private_key' => $privateKey,
        ]));

        config()->set('services.firebase.project_id', 'mboka-media-test');
        config()->set('services.firebase.credentials', $path);
        config()->set('services.firebase.broadcast_topic', 'all-users');
        config()->set('services.firebase.android_channel', 'mboka_articles');
    }
}
