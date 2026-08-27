<?php

namespace App\Jobs;

use App\Models\ArticleNotification;
use App\Services\Fcm\FcmClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pousse un article fraîchement publié vers le topic de diffusion.
 *
 * En file d'attente : l'API FCM ne doit pas faire attendre le journaliste qui
 * vient de cliquer sur « Publier ».
 */
class SendArticlePushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(protected ArticleNotification $notification) {}

    public function handle(FcmClient $fcm): void
    {
        if ($this->notification->push_sent_at !== null) {
            return;
        }

        if (! $fcm->isConfigured()) {
            Log::warning('Push ignoré : Firebase n\'est pas configuré.', [
                'article_id' => $this->notification->article_id,
            ]);

            return;
        }

        $topic = (string) config('services.firebase.broadcast_topic', 'all-users');

        $fcm->sendToTopic(
            $topic,
            $this->notification->title,
            $this->body(),
            [
                // `slug` est obligatoire : sans lui, le tap n'ouvre que l'accueil.
                'slug' => $this->notification->slug,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ...($this->notification->featured_image
                    ? ['image' => $this->notification->featured_image]
                    : []),
            ],
        );

        $this->notification->forceFill(['push_sent_at' => now()])->save();
    }

    /**
     * Le chapeau sert de corps, tronqué : au-delà, Android coupe de toute façon.
     */
    protected function body(): string
    {
        $excerpt = trim(strip_tags((string) $this->notification->excerpt));

        return $excerpt !== ''
            ? Str::limit($excerpt, 160)
            : 'Touchez pour lire l\'article sur Mboka Média.';
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Envoi du push impossible.', [
            'article_id' => $this->notification->article_id,
            'message' => $exception->getMessage(),
        ]);
    }
}
