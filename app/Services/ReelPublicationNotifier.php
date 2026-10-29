<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Jobs\SendReelPushNotification;
use App\Models\Video;
use Throwable;

class ReelPublicationNotifier
{
    /**
     * Envoie la notification d'un reel publié, une seule fois.
     *
     * Appelée à l'enregistrement dans le dashboard, et toutes les 5 minutes par
     * `reels:notify-due` pour les reels programmés arrivés à échéance.
     */
    public function notifyIfDue(?Video $video): bool
    {
        // Relit la ligne : un reel tout juste créé n'a pas encore les valeurs
        // par défaut de la base (`notify_on_publish`).
        $video = $video?->fresh();

        if ($video === null || ! $this->isDue($video)) {
            return false;
        }

        // Réservation atomique : une seule requête obtient la ligne.
        $claimed = Video::query()
            ->whereKey($video->getKey())
            ->whereNull('push_sent_at')
            ->update(['push_sent_at' => now()]);

        if ($claimed !== 1) {
            return false;
        }

        try {
            SendReelPushNotification::dispatch($video)
                ->onConnection(config('services.firebase.queue_connection', 'sync'));
        } catch (Throwable $exception) {
            // Un échec d'envoi n'annule jamais la publication.
            Video::query()->whereKey($video->getKey())->update(['push_sent_at' => null]);
            report($exception);

            return false;
        }

        return true;
    }

    protected function isDue(Video $video): bool
    {
        return $video->is_reel
            && $video->notify_on_publish
            && $video->push_sent_at === null
            && $video->status === ContentStatus::Published
            && $video->published_at !== null
            && $video->published_at->isPast();
    }
}
