<?php

namespace App\Jobs;

use App\Models\Briefing;
use App\Services\Fcm\FcmClient;
use App\Support\FcmTopics;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBriefingPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(public Briefing $briefing) {}

    public function handle(FcmClient $fcm): void
    {
        if (! $fcm->isConfigured()) {
            Log::warning('Push briefing ignoré : Firebase n’est pas configuré.', [
                'briefing_id' => $this->briefing->id,
            ]);
            $this->releaseReservation();

            return;
        }

        $fcm->sendToTopics(
            [FcmTopics::ALL_USERS],
            $this->briefing->title,
            'Ton briefing du jour est disponible.',
            [
                'type' => 'briefing',
                'briefing_id' => (string) $this->briefing->id,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ],
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Envoi du push briefing impossible.', [
            'briefing_id' => $this->briefing->id,
            'message' => $exception->getMessage(),
        ]);
        $this->releaseReservation();
    }

    private function releaseReservation(): void
    {
        Briefing::query()->whereKey($this->briefing->getKey())->update(['notification_sent_at' => null]);
    }
}
