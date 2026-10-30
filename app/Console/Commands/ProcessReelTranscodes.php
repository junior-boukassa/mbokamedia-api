<?php

namespace App\Console\Commands;

use App\Models\ReelTranscode;
use App\Models\Video;
use App\Services\ReelPublicationNotifier;
use App\Services\ReelTranscoder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessReelTranscodes extends Command
{
    protected $signature = 'reels:process-transcode';

    protected $description = 'Vérifie et prépare un fichier vidéo Reel avant sa publication.';

    public function handle(ReelTranscoder $transcoder, ReelPublicationNotifier $notifier): int
    {
        $lockPath = config('reel-transcoding.worker_lock_file');
        if (! is_string($lockPath) || $lockPath === '') {
            throw new \RuntimeException('Le chemin du verrou de traitement des reels n’est pas configuré.');
        }

        $lockDirectory = dirname($lockPath);
        if (! is_dir($lockDirectory) && ! mkdir($lockDirectory, 0700, true) && ! is_dir($lockDirectory)) {
            throw new \RuntimeException('Impossible de créer le répertoire du verrou de traitement des reels.');
        }

        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            throw new \RuntimeException('Impossible d’ouvrir le verrou de traitement des reels.');
        }

        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return self::SUCCESS;
        }

        try {
            return $this->processNext($transcoder, $notifier);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function processNext(ReelTranscoder $transcoder, ReelPublicationNotifier $notifier): int
    {
        $transcoder->cleanupTemporaryFiles();
        $this->failExpiredClaims();

        $transcode = ReelTranscode::query()
            ->where('status', ReelTranscode::STATUS_QUEUED)
            ->orderBy('id')
            ->first();

        if ($transcode === null) {
            return self::SUCCESS;
        }

        $token = (string) Str::uuid();
        $claimed = ReelTranscode::query()
            ->whereKey($transcode->id)
            ->where('status', ReelTranscode::STATUS_QUEUED)
            ->update([
                'status' => ReelTranscode::STATUS_PROCESSING,
                'lock_token' => $token,
                'started_at' => now(),
                'attempts' => DB::raw('attempts + 1'),
                'last_error' => null,
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return self::SUCCESS;
        }

        $transcode = $transcode->fresh();
        $result = null;

        try {
            $result = $transcoder->process($transcode);

            $video = DB::transaction(function () use ($transcode, $token, $result): ?Video {
                $lockedTranscode = ReelTranscode::query()
                    ->whereKey($transcode->id)
                    ->lockForUpdate()
                    ->first();

                if ($lockedTranscode === null
                    || $lockedTranscode->status !== ReelTranscode::STATUS_PROCESSING
                    || $lockedTranscode->lock_token !== $token) {
                    return null;
                }

                $video = Video::query()->lockForUpdate()->find($lockedTranscode->video_id);
                if ($video === null) {
                    $lockedTranscode->update(['status' => ReelTranscode::STATUS_CANCELLED, 'lock_token' => null]);

                    return null;
                }

                $video->update([
                    'video_url' => $result['video_url'],
                    'status' => $lockedTranscode->target_status,
                    'published_at' => $lockedTranscode->target_published_at,
                    'notify_on_publish' => $lockedTranscode->target_notify_on_publish,
                    'duration_seconds' => $result['duration_seconds'],
                    'width' => $result['width'],
                    'height' => $result['height'],
                ]);
                $lockedTranscode->update([
                    'status' => ReelTranscode::STATUS_COMPLETED,
                    'lock_token' => null,
                    'started_at' => null,
                    'last_error' => null,
                ]);

                return $video;
            });

            if ($video === null) {
                $transcoder->discard($result);

                return self::SUCCESS;
            }

            $notifier->notifyIfDue($video);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if (is_array($result)) {
                $transcoder->discard($result);
            }

            Log::error('Reel video processing failed.', [
                'reel_transcode_id' => $transcode->id,
                'video_id' => $transcode->video_id,
                'exception' => $exception,
            ]);

            ReelTranscode::query()
                ->whereKey($transcode->id)
                ->where('lock_token', $token)
                ->where('status', ReelTranscode::STATUS_PROCESSING)
                ->update([
                    'status' => ReelTranscode::STATUS_FAILED,
                    'lock_token' => null,
                    'started_at' => null,
                    'last_error' => 'La vidéo n’a pas pu être vérifiée ou convertie. Réenregistrez le reel après avoir corrigé le fichier.',
                    'updated_at' => now(),
                ]);

            $this->error('Le traitement vidéo a échoué; le reel reste non publié.');

            return self::FAILURE;
        }
    }

    private function failExpiredClaims(): void
    {
        ReelTranscode::query()
            ->where('status', ReelTranscode::STATUS_PROCESSING)
            ->where('started_at', '<', now()->subMinutes(5))
            ->update([
                'status' => ReelTranscode::STATUS_FAILED,
                'lock_token' => null,
                'started_at' => null,
                'last_error' => 'Le traitement a été interrompu avant sa fin. Réenregistrez le reel pour réessayer.',
                'updated_at' => now(),
            ]);
    }
}
