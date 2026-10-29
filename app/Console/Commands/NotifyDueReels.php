<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\ReelPublicationNotifier;
use Illuminate\Console\Command;

class NotifyDueReels extends Command
{
    protected $signature = 'reels:notify-due';

    protected $description = 'Notifie les reels programmés arrivés à échéance et pas encore annoncés.';

    public function handle(ReelPublicationNotifier $notifier): int
    {
        $sent = 0;

        Video::query()
            ->published()
            ->where('is_reel', true)
            ->where('notify_on_publish', true)
            ->whereNull('push_sent_at')
            ->orderBy('id')
            ->each(function (Video $video) use ($notifier, &$sent): void {
                $sent += $notifier->notifyIfDue($video) ? 1 : 0;
            });

        $this->info("{$sent} reel(s) notifié(s).");

        return self::SUCCESS;
    }
}
