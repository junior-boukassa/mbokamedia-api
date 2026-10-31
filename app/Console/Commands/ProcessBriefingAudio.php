<?php

namespace App\Console\Commands;

use App\Models\Briefing;
use App\Models\BriefingAudioTranscode;
use App\Services\BriefingAudioTranscoder;
use App\Services\BriefingPublicationNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessBriefingAudio extends Command
{
    protected $signature = 'briefings:process-audio';

    protected $description = 'Vérifie et prépare un fichier audio de briefing avant sa publication.';

    public function handle(
        BriefingAudioTranscoder $transcoder,
        BriefingPublicationNotifier $notifier,
    ): int {
        $lockPath = config('reel-transcoding.worker_lock_file');
        if (! is_string($lockPath) || $lockPath === '') {
            throw new \RuntimeException('Le verrou partagé de traitement des médias n’est pas configuré.');
        }
        $directory = dirname($lockPath);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le répertoire du verrou de traitement des médias.');
        }
        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            throw new \RuntimeException('Impossible d’ouvrir le verrou partagé de traitement des médias.');
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

    private function processNext(
        BriefingAudioTranscoder $transcoder,
        BriefingPublicationNotifier $notifier,
    ): int {
        $transcoder->cleanupTemporaryFiles();
        $this->failExpiredClaims();

        $transcode = BriefingAudioTranscode::query()
            ->where('status', BriefingAudioTranscode::STATUS_QUEUED)
            ->orderBy('id')
            ->first();
        if ($transcode === null) {
            return self::SUCCESS;
        }

        $token = (string) Str::uuid();
        $claimed = BriefingAudioTranscode::query()
            ->whereKey($transcode->id)
            ->where('status', BriefingAudioTranscode::STATUS_QUEUED)
            ->where('attempts', '<', (int) config('briefing-audio.max_attempts'))
            ->update([
                'status' => BriefingAudioTranscode::STATUS_PROCESSING,
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
            $briefing = DB::transaction(function () use ($transcode, $token, $result): ?Briefing {
                $lockedTranscode = BriefingAudioTranscode::query()
                    ->whereKey($transcode->id)
                    ->lockForUpdate()
                    ->first();
                if ($lockedTranscode === null
                    || $lockedTranscode->status !== BriefingAudioTranscode::STATUS_PROCESSING
                    || $lockedTranscode->lock_token !== $token) {
                    return null;
                }

                $briefing = Briefing::query()->lockForUpdate()->find($lockedTranscode->briefing_id);
                if ($briefing === null) {
                    $lockedTranscode->update([
                        'status' => BriefingAudioTranscode::STATUS_FAILED,
                        'lock_token' => null,
                    ]);

                    return null;
                }

                $isPublishable = $lockedTranscode->target_status === 'published'
                    && trim($briefing->text) !== '';
                $briefing->update([
                    'audio_path' => $result['audio_path'],
                    'audio_duration_seconds' => $result['duration_seconds'],
                    'audio_size_bytes' => $result['size_bytes'],
                    'audio_status' => BriefingAudioTranscode::STATUS_READY,
                    'status' => $isPublishable ? 'published' : 'draft',
                    'notify_readers' => $lockedTranscode->target_notify_readers,
                    'published_at' => $isPublishable ? ($briefing->published_at ?? now()) : null,
                ]);
                $lockedTranscode->update([
                    'status' => BriefingAudioTranscode::STATUS_READY,
                    'previous_audio_path' => null,
                    'lock_token' => null,
                    'started_at' => null,
                    'last_error' => null,
                ]);

                return $briefing;
            });

            if ($briefing === null) {
                $transcoder->discard($result);

                return self::SUCCESS;
            }

            $this->deleteManagedFile($transcode->previous_audio_path, 'briefings/audio/');
            $this->deleteManagedFile($transcode->source_path, 'briefings/sources/');
            $notifier->notifyOnFirstPublication($briefing);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if (is_array($result)) {
                $transcoder->discard($result);
            }
            Log::error('Briefing audio processing failed.', [
                'briefing_audio_transcode_id' => $transcode->id,
                'briefing_id' => $transcode->briefing_id,
                'exception' => $exception,
            ]);

            BriefingAudioTranscode::query()
                ->whereKey($transcode->id)
                ->where('lock_token', $token)
                ->where('status', BriefingAudioTranscode::STATUS_PROCESSING)
                ->update([
                    'status' => BriefingAudioTranscode::STATUS_FAILED,
                    'lock_token' => null,
                    'started_at' => null,
                    'last_error' => 'Le fichier audio n’a pas pu être vérifié ou converti. Réessayez ou importez un autre fichier.',
                    'updated_at' => now(),
                ]);
            Briefing::query()
                ->whereKey($transcode->briefing_id)
                ->update(['audio_status' => BriefingAudioTranscode::STATUS_FAILED, 'status' => 'draft', 'published_at' => null]);

            $this->error('Le traitement audio a échoué; le briefing reste non publié.');

            return self::FAILURE;
        }
    }

    private function failExpiredClaims(): void
    {
        $expired = BriefingAudioTranscode::query()
            ->where('status', BriefingAudioTranscode::STATUS_PROCESSING)
            ->where('started_at', '<', now()->subMinutes(5))
            ->get();

        foreach ($expired as $transcode) {
            DB::transaction(function () use ($transcode): void {
                $locked = BriefingAudioTranscode::query()->whereKey($transcode->id)->lockForUpdate()->first();
                if ($locked === null
                    || $locked->status !== BriefingAudioTranscode::STATUS_PROCESSING
                    || $locked->started_at === null
                    || $locked->started_at->greaterThanOrEqualTo(now()->subMinutes(5))) {
                    return;
                }
                $locked->update([
                    'status' => BriefingAudioTranscode::STATUS_FAILED,
                    'lock_token' => null,
                    'started_at' => null,
                    'last_error' => 'Le traitement a dépassé son délai maximal. Vous pouvez le relancer.',
                ]);
                Briefing::query()
                    ->whereKey($locked->briefing_id)
                    ->update(['audio_status' => BriefingAudioTranscode::STATUS_FAILED, 'status' => 'draft', 'published_at' => null]);
            });
        }
    }

    private function deleteManagedFile(?string $path, string $prefix): void
    {
        if ($path !== null
            && str_starts_with($path, $prefix)
            && ! str_contains($path, '..')
            && ! str_contains($path, "\0")) {
            Storage::disk('public')->delete($path);
        }
    }
}
