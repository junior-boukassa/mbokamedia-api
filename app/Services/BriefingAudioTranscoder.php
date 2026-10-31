<?php

namespace App\Services;

use App\Models\BriefingAudioTranscode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

class BriefingAudioTranscoder
{
    /**
     * @return array{audio_path: string, duration_seconds: int, size_bytes: int}
     */
    public function process(BriefingAudioTranscode $transcode): array
    {
        $source = $this->resolveManagedPath($transcode->source_path, 'briefings/sources/');
        $sourceProbe = $this->probe($source);
        $this->validateAudioProbe($sourceProbe, (string) pathinfo($transcode->source_path, PATHINFO_EXTENSION));

        $token = $transcode->lock_token;
        if (! is_string($token) || ! Str::isUuid($token)) {
            throw new RuntimeException('Le verrou du traitement audio est invalide.');
        }

        $temporaryDirectory = config('briefing-audio.temporary_directory');
        if (! is_string($temporaryDirectory) || $temporaryDirectory === '') {
            throw new RuntimeException('Le répertoire temporaire audio n’est pas configuré.');
        }
        if (! is_dir($temporaryDirectory) && ! mkdir($temporaryDirectory, 0700, true) && ! is_dir($temporaryDirectory)) {
            throw new RuntimeException('Impossible de créer le répertoire temporaire audio.');
        }

        $temporaryPath = $temporaryDirectory.'/briefing-audio-'.Str::uuid().'.m4a';
        $audioPath = 'briefings/audio/'.$transcode->briefing_id.'-'.$token.'.m4a';
        $audioAbsolutePath = Storage::disk('public')->path($audioPath);
        $created = false;

        try {
            $this->run([
                $this->binary('ffmpeg'),
                '-nostdin',
                '-hide_banner',
                '-loglevel',
                'error',
                '-xerror',
                '-y',
                '-threads',
                '1',
                '-i',
                $source,
                '-map',
                '0:a:0',
                '-vn',
                '-sn',
                '-dn',
                '-c:a',
                'aac',
                '-profile:a',
                'aac_low',
                '-ar',
                '48000',
                '-ac',
                '1',
                '-b:a',
                '80k',
                '-movflags',
                '+faststart',
                '-fs',
                (string) config('briefing-audio.max_output_bytes'),
                $temporaryPath,
            ], (int) config('briefing-audio.timeout_seconds'));

            if (! is_file($temporaryPath) || filesize($temporaryPath) < 1) {
                throw new RuntimeException('FFmpeg n’a produit aucun fichier audio exploitable.');
            }

            if (filesize($temporaryPath) > (int) config('briefing-audio.max_output_bytes')) {
                throw new RuntimeException('Le fichier audio converti dépasse la limite autorisée.');
            }

            $outputProbe = $this->probe($temporaryPath);
            $outputAudio = $this->audioStream($outputProbe);
            if ($outputAudio === null
                || ($outputAudio['codec_name'] ?? null) !== 'aac'
                || (int) ($outputAudio['channels'] ?? 0) !== 1
                || ! str_contains((string) ($outputProbe['format']['format_name'] ?? ''), 'mp4')) {
                throw new RuntimeException('Le fichier audio converti ne contient pas un flux AAC mono compatible.');
            }

            $duration = (float) ($outputProbe['format']['duration'] ?? 0);
            if ($duration <= 0) {
                throw new RuntimeException('La durée du fichier audio converti est invalide.');
            }

            if (! is_dir(dirname($audioAbsolutePath))
                && ! mkdir(dirname($audioAbsolutePath), 0755, true)
                && ! is_dir(dirname($audioAbsolutePath))) {
                throw new RuntimeException('Impossible de préparer le stockage audio.');
            }
            if (! rename($temporaryPath, $audioAbsolutePath)) {
                throw new RuntimeException('Impossible de déplacer le fichier audio vers son stockage définitif.');
            }
            $created = true;

            return [
                'audio_path' => $audioPath,
                'duration_seconds' => max(1, (int) round($duration)),
                'size_bytes' => (int) filesize($audioAbsolutePath),
            ];
        } catch (Throwable $exception) {
            if ($created) {
                Storage::disk('public')->delete($audioPath);
            }
            throw $exception;
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /**
     * Verify the actual container and codec; the client-supplied MIME type is not trusted.
     *
     * @return array<string, mixed>
     */
    public function inspectUpload(string $path, string $extension): array
    {
        if (! in_array(strtolower($extension), ['mp3', 'm4a', 'aac'], true)) {
            throw new RuntimeException('Formats acceptés : MP3, M4A ou AAC.');
        }

        $probe = $this->probe($path);
        $this->validateAudioProbe($probe, $extension);

        return $probe;
    }

    public function discard(array $result): void
    {
        Storage::disk('public')->delete($result['audio_path']);
    }

    public function cleanupTemporaryFiles(): void
    {
        $directory = config('briefing-audio.temporary_directory');
        if (! is_string($directory) || ! is_dir($directory)) {
            return;
        }

        foreach (new \DirectoryIterator($directory) as $file) {
            if ($file->isDot()
                || ! $file->isFile()
                || ! str_starts_with($file->getFilename(), 'briefing-audio-')
                || $file->getMTime() > now()->subHour()->timestamp) {
                continue;
            }
            unlink($file->getPathname());
        }
    }

    private function resolveManagedPath(string $relativePath, string $prefix): string
    {
        if (! str_starts_with($relativePath, $prefix)
            || str_contains($relativePath, '..')
            || str_contains($relativePath, "\0")) {
            throw new RuntimeException('Le chemin du fichier audio est invalide.');
        }

        $disk = Storage::disk('public');
        $root = realpath($disk->path(''));
        $path = realpath($disk->path($relativePath));
        if ($root === false || $path === false
            || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)
            || ! is_file($path)) {
            throw new RuntimeException('Le fichier audio source est introuvable.');
        }

        $size = filesize($path);
        if ($size === false || $size < 1 || $size > (int) config('briefing-audio.max_upload_bytes')) {
            throw new RuntimeException('Le fichier audio source est vide ou dépasse la limite autorisée.');
        }

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function probe(string $path): array
    {
        $process = new Process([
            $this->binary('ffprobe'),
            '-v',
            'error',
            '-show_streams',
            '-show_format',
            '-of',
            'json',
            $path,
        ]);
        $process->setTimeout((int) config('briefing-audio.probe_timeout_seconds'));

        try {
            $process->mustRun();
        } catch (ProcessTimedOutException $exception) {
            throw new RuntimeException('La vérification du fichier audio a dépassé son délai maximal.', previous: $exception);
        } catch (Throwable $exception) {
            throw new RuntimeException('Le fichier ne peut pas être vérifié comme audio MP3, M4A ou AAC.', previous: $exception);
        }

        try {
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('La réponse de ffprobe est invalide.', previous: $exception);
        }

        if (! is_array($result) || ! is_array($result['streams'] ?? null)) {
            throw new RuntimeException('Le fichier ne contient pas de flux audio lisible.');
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $probe
     */
    private function validateAudioProbe(array $probe, string $extension): void
    {
        $streams = $probe['streams'] ?? [];
        $audio = $this->audioStream($probe);
        $hasVideo = collect($streams)->contains(fn ($stream): bool => ($stream['codec_type'] ?? null) === 'video');
        $format = (string) ($probe['format']['format_name'] ?? '');
        $extension = strtolower($extension);
        $codec = $audio['codec_name'] ?? null;

        $validMp3 = $extension === 'mp3' && $codec === 'mp3' && str_contains($format, 'mp3');
        $validAac = in_array($extension, ['m4a', 'aac'], true)
            && $codec === 'aac'
            && ($extension === 'aac'
                ? str_contains($format, 'aac')
                : str_contains($format, 'mp4'));

        if ($hasVideo || $audio === null || (! $validMp3 && ! $validAac)
            || (float) ($probe['format']['duration'] ?? 0) <= 0) {
            throw new RuntimeException('Le contenu ou le codec ne correspond pas à un vrai MP3, M4A ou AAC audio.');
        }
    }

    /**
     * @param  array<string, mixed>  $probe
     * @return array<string, mixed>|null
     */
    private function audioStream(array $probe): ?array
    {
        foreach ($probe['streams'] ?? [] as $stream) {
            if (is_array($stream) && ($stream['codec_type'] ?? null) === 'audio') {
                return $stream;
            }
        }

        return null;
    }

    private function binary(string $key): string
    {
        $binary = config('reel-transcoding.'.$key);
        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Le binaire '.$key.' n’est pas configuré.');
        }

        return $binary;
    }

    /**
     * @param  array<int, string>  $command
     */
    private function run(array $command, int $timeout): void
    {
        $process = new Process($command);
        $process->setTimeout($timeout);

        try {
            $process->mustRun();
        } catch (ProcessTimedOutException $exception) {
            throw new RuntimeException('Le traitement audio a dépassé son délai maximal.', previous: $exception);
        } catch (Throwable $exception) {
            throw new RuntimeException('FFmpeg n’a pas pu préparer le fichier audio.', previous: $exception);
        }
    }
}
