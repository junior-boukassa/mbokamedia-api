<?php

namespace App\Services;

use App\Jobs\SendBriefingPushNotification;
use App\Models\Briefing;
use Throwable;

class BriefingPublicationNotifier
{
    public function notifyOnFirstPublication(?Briefing $briefing): bool
    {
        $briefing = $briefing?->fresh();
        if ($briefing === null
            || ! $briefing->notify_readers
            || $briefing->status !== 'published'
            || $briefing->audio_status !== 'ready'
            || $briefing->audio_path === null
            || $briefing->published_at === null
            || $briefing->published_at->isFuture()) {
            return false;
        }

        $claimed = Briefing::query()
            ->whereKey($briefing->getKey())
            ->whereNull('notification_sent_at')
            ->update(['notification_sent_at' => now()]);

        if ($claimed !== 1) {
            return false;
        }

        try {
            SendBriefingPushNotification::dispatch($briefing)
                ->onConnection(config('services.firebase.queue_connection', 'sync'));
        } catch (Throwable $exception) {
            Briefing::query()->whereKey($briefing->getKey())->update(['notification_sent_at' => null]);
            report($exception);

            return false;
        }

        return true;
    }
}
