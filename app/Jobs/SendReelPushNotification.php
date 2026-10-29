<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\Fcm\FcmClient;
use App\Support\FcmTopics;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Prévient les lecteurs qu'un nouveau reel est disponible.
 *
 * `push_sent_at` est posé AVANT l'envoi par ReelPublicationNotifier (réservation
 * atomique : deux requêtes simultanées n'envoient jamais deux fois). Si l'envoi
 * échoue pour de bon, ou si Firebase n'est pas configuré, la réservation est
 * levée pour qu'un nouvel essai reste possible.
 */
class SendReelPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(public Video $video) {}

    public function handle(FcmClient $fcm): void
    {
        if (! $fcm->isConfigured()) {
            Log::warning('Push reel ignoré : Firebase n\'est pas configuré.', ['video_id' => $this->video->id]);
            $this->releaseReservation();

            return;
        }

        $fcm->sendToTopics(
            [FcmTopics::REELS],
            (string) $this->video->title,
            'Nouveau Reel sur Mboka Media',
            [
                'type' => 'reel',
                'slug' => (string) $this->video->slug,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ...($this->imageUrl() ? ['image' => $this->imageUrl()] : []),
            ],
        );
    }

    protected function imageUrl(): ?string
    {
        $thumbnail = trim((string) $this->video->thumbnail);

        if ($thumbnail === '') {
            return null;
        }

        return preg_match('#^https?://#i', $thumbnail) === 1
            ? $thumbnail
            : Storage::disk('public')->url($thumbnail);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Envoi du push reel impossible.', [
            'video_id' => $this->video->id,
            'message' => $exception->getMessage(),
        ]);

        $this->releaseReservation();
    }

    protected function releaseReservation(): void
    {
        Video::query()->whereKey($this->video->getKey())->update(['push_sent_at' => null]);
    }
}
