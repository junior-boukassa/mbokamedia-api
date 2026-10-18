<?php

namespace App\Jobs;

use App\Models\ArticleNotification;
use App\Services\Fcm\FcmClient;
use App\Support\FcmTopics;
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

    public function __construct(public ArticleNotification $notification) {}

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

        $fcm->sendToTopics(
            $this->topics(),
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
     * Sujets visés : le sujet général, et celui de la rubrique de l'article.
     *
     * Le lecteur qui n'a rien choisi est abonné au sujet général ; celui qui a
     * choisi des rubriques ne l'est plus et n'a que le sujet de sa rubrique.
     * L'envoi se fait en un seul message (voir FcmClient::sendToTopics).
     *
     * @return array<int, string>
     */
    protected function topics(): array
    {
        $topics = [(string) config('services.firebase.broadcast_topic', 'all-users')];

        $slug = $this->notification->loadMissing('article.category')->article?->category?->slug;

        if (is_string($slug) && $slug !== '') {
            $topics[] = FcmTopics::category($slug);
        }

        return $topics;
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
