<?php

namespace App\Services;

use App\Models\ReelTranscode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

class ReelTranscoder
{
    /**
     * @return array{
     *     video_url: string,
     *     duration_seconds: ?int,
     *     width: ?int,
     *     height: ?int,
     *     generated_path: ?string
     * }
     */
    public function process(ReelTranscode $transcode): array
    {
        $disk = Storage::disk('public');
        $sourcePath = $transcode->source_path;
        $sourceAbsolutePath = $this->resolvePublicPath($sourcePath);
        $this->validateSourceFile($sourcePath, $sourceAbsolutePath);

        $source = $this->probe($sourceAbsolutePath);
        $sourceVideo = $this->videoStream($source);
        $sourceAudio = $this->audioStreams($source);
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $requiresTranscode = $extension === 'mov' || $this->requiresTranscode($sourceVideo, $sourceAudio);

        if (! $requiresTranscode) {
            return $this->result($sourcePath, $source, null);
        }

        $token = $transcode->lock_token;

        if (! is_string($token) || ! Str::isUuid($token)) {
            throw new RuntimeException('Le verrou du traitement vidéo est invalide.');
        }

        $temporaryDirectory = config('reel-transcoding.temporary_directory');
        if (! is_string($temporaryDirectory) || $temporaryDirectory === '') {
            throw new RuntimeException('Le répertoire temporaire de conversion n’est pas configuré.');
        }

        if (! is_dir($temporaryDirectory) && ! mkdir($temporaryDirectory, 0700, true) && ! is_dir($temporaryDirectory)) {
            throw new RuntimeException('Impossible de créer le répertoire temporaire de conversion.');
        }

        $temporaryPath = $temporaryDirectory.'/reel-transcode-'.Str::uuid().'.mp4';
        $generatedPath = 'media/reels/processed/'.$transcode->video_id.'-'.$token.'.mp4';
        $generatedAbsolutePath = $disk->path($generatedPath);
        $generated = false;

        try {
            $this->run([
                $this->binary('ffmpeg'),
                '-nostdin',
                '-hide_banner',
                '-loglevel',
                'error',
                '-xerror',
                '-y',
                '-filter_threads',
                '1',
                '-threads',
                '1',
                '-i',
                $sourceAbsolutePath,
                '-map',
                '0:v:0',
                '-map',
                '0:a?',
                '-sn',
                '-dn',
                '-vf',
                "fps=30,scale=w='if(gte(iw,ih),min(1280,iw),min(720,iw))':h='if(gte(iw,ih),min(720,ih),min(1280,ih))':force_original_aspect_ratio=decrease:force_divisible_by=2",
                '-c:v',
                'libx264',
                '-preset',
                'veryfast',
                '-threads:v',
                '1',
                '-crf',
                '24',
                '-profile:v',
                'main',
                '-level:v',
                '4.1',
                '-pix_fmt',
                'yuv420p',
                '-c:a',
                'aac',
                '-ar',
                '48000',
                '-ac',
                '2',
                '-b:a',
                '128k',
                '-movflags',
                '+faststart',
                '-fs',
                (string) config('reel-transcoding.max_output_bytes'),
                $temporaryPath,
            ], (int) config('reel-transcoding.timeout_seconds'));

            if (! is_file($temporaryPath) || filesize($temporaryPath) === 0) {
                throw new RuntimeException('FFmpeg n’a produit aucun fichier exploitable.');
            }

            if (filesize($temporaryPath) > (int) config('reel-transcoding.max_output_bytes')) {
                throw new RuntimeException('La vidéo convertie dépasse la taille maximale autorisée.');
            }

            $output = $this->probe($temporaryPath);
            $outputVideo = $this->videoStream($output);
            $outputAudio = $this->audioStreams($output);

            if ($this->requiresTranscode($outputVideo, $outputAudio)) {
                throw new RuntimeException('Le fichier converti ne contient pas les codecs H.264/AAC attendus.');
            }

            if (! is_dir(dirname($generatedAbsolutePath))
                && ! mkdir(dirname($generatedAbsolutePath), 0755, true)
                && ! is_dir(dirname($generatedAbsolutePath))) {
                throw new RuntimeException('Impossible de préparer le stockage de la vidéo convertie.');
            }

            if (! rename($temporaryPath, $generatedAbsolutePath)) {
                throw new RuntimeException('Impossible de déplacer la vidéo convertie vers le stockage public.');
            }
            $generated = true;

            return $this->result($generatedPath, $output, $generatedPath);
        } catch (Throwable $exception) {
            if ($generated && $disk->exists($generatedPath)) {
                $disk->delete($generatedPath);
            }

            throw $exception;
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /**
     * Delete only an output generated by this worker when its database claim became stale.
     *
     * @param  array{generated_path: ?string}  $result
     */
    public function discard(array $result): void
    {
        if ($result['generated_path'] !== null) {
            Storage::disk('public')->delete($result['generated_path']);
        }
    }

    /**
     * Remove abandoned temporary outputs left by a terminated process.
     */
    public function cleanupTemporaryFiles(): void
    {
        $directory = config('reel-transcoding.temporary_directory');

        if (! is_string($directory) || ! is_dir($directory)) {
            return;
        }

        foreach (new \DirectoryIterator($directory) as $file) {
            if ($file->isDot()
                || ! $file->isFile()
                || ! str_starts_with($file->getFilename(), 'reel-transcode-')
                || $file->getMTime() > now()->subHour()->timestamp) {
                continue;
            }

            unlink($file->getPathname());
        }
    }

    private function resolvePublicPath(string $relativePath): string
    {
        if ($relativePath === '' || str_starts_with($relativePath, '/') || str_contains($relativePath, "\0")) {
            throw new RuntimeException('Le chemin du fichier vidéo est invalide.');
        }

        foreach (explode('/', str_replace('\\', '/', $relativePath)) as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new RuntimeException('Le chemin du fichier vidéo est invalide.');
            }
        }

        $disk = Storage::disk('public');
        $root = realpath($disk->path(''));
        $path = realpath($disk->path($relativePath));

        if ($root === false || $path === false || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR) || ! is_file($path)) {
            throw new RuntimeException('Le fichier vidéo source est introuvable dans le stockage public.');
        }

        return $path;
    }

    private function validateSourceFile(string $relativePath, string $absolutePath): void
    {
        $size = filesize($absolutePath);
        if ($size === false || $size < 1 || $size > (int) config('reel-transcoding.max_input_bytes')) {
            throw new RuntimeException('La vidéo source est vide ou dépasse la limite de 100 Mo.');
        }

        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($absolutePath);
        $validType = match ($extension) {
            'mp4' => in_array($mimeType, ['video/mp4', 'application/mp4'], true),
            'mov' => $mimeType === 'video/quicktime',
            default => false,
        };

        if (! $validType) {
            throw new RuntimeException('Le type réel du fichier ne correspond pas à son extension MP4 ou MOV.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function probe(string $path): array
    {
        $result = $this->run([
            $this->binary('ffprobe'),
            '-v',
            'error',
            '-show_streams',
            '-show_format',
            '-of',
            'json',
            $path,
        ], (int) config('reel-transcoding.probe_timeout_seconds'));

        try {
            $probe = json_decode($result->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('FFprobe a renvoyé des métadonnées invalides.', previous: $exception);
        }

        if (! is_array($probe) || ! is_array($probe['streams'] ?? null)) {
            throw new RuntimeException('FFprobe ne trouve aucun flux vidéo exploitable.');
        }

        return $probe;
    }

    /**
     * @param  array<string, mixed>  $probe
     * @return array<string, mixed>
     */
    private function videoStream(array $probe): array
    {
        foreach ($probe['streams'] as $stream) {
            if (is_array($stream) && ($stream['codec_type'] ?? null) === 'video') {
                return $stream;
            }
        }

        throw new RuntimeException('Aucun flux vidéo valide n’a été trouvé.');
    }

    /**
     * @param  array<string, mixed>  $probe
     * @return array<int, array<string, mixed>>
     */
    private function audioStreams(array $probe): array
    {
        return collect($probe['streams'])
            ->filter(fn ($stream): bool => is_array($stream) && ($stream['codec_type'] ?? null) === 'audio')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $video
     * @param  array<int, array<string, mixed>>  $audio
     */
    private function requiresTranscode(array $video, array $audio): bool
    {
        $profile = strtolower((string) ($video['profile'] ?? ''));
        $supportedProfiles = ['baseline', 'constrained baseline', 'main', 'high'];
        $level = (int) ($video['level'] ?? 0);

        if (strtolower((string) ($video['codec_name'] ?? '')) !== 'h264'
            || ! in_array($profile, $supportedProfiles, true)
            || strtolower((string) ($video['pix_fmt'] ?? '')) !== 'yuv420p'
            || $level < 1
            || $level > 41
            || (int) ($video['width'] ?? 0) < 1
            || (int) ($video['height'] ?? 0) < 1
            || (int) ($video['width'] ?? 0) > 1920
            || (int) ($video['height'] ?? 0) > 1920) {
            return true;
        }

        foreach ($audio as $stream) {
            if (strtolower((string) ($stream['codec_name'] ?? '')) !== 'aac'
                || strtolower((string) ($stream['profile'] ?? '')) !== 'lc'
                || (int) ($stream['sample_rate'] ?? 0) < 1
                || (int) ($stream['sample_rate'] ?? 0) > 48000
                || (int) ($stream['channels'] ?? 0) < 1
                || (int) ($stream['channels'] ?? 0) > 2) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $probe
     * @return array{
     *     video_url: string,
     *     duration_seconds: ?int,
     *     width: ?int,
     *     height: ?int,
     *     generated_path: ?string
     * }
     */
    private function result(string $videoUrl, array $probe, ?string $generatedPath): array
    {
        $video = $this->videoStream($probe);
        $duration = $probe['format']['duration'] ?? null;

        return [
            'video_url' => $videoUrl,
            'duration_seconds' => is_numeric($duration) ? (int) round((float) $duration) : null,
            'width' => isset($video['width']) ? (int) $video['width'] : null,
            'height' => isset($video['height']) ? (int) $video['height'] : null,
            'generated_path' => $generatedPath,
        ];
    }

    private function binary(string $name): string
    {
        $path = config("reel-transcoding.{$name}");

        if (! is_string($path) || $path === '' || ! is_file($path) || ! is_executable($path)) {
            throw new RuntimeException("Le binaire {$name} n’est pas installé ou exécutable.");
        }

        return $path;
    }

    /**
     * @param  array<int, string>  $command
     */
    private function run(array $command, int $timeout): Process
    {
        $process = new Process($command);
        $process->setTimeout($timeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            throw new RuntimeException('Le traitement vidéo a dépassé son délai maximal.', previous: $exception);
        }

        if (! $process->isSuccessful()) {
            $detail = trim($process->getErrorOutput());
            throw new RuntimeException('Le traitement vidéo a échoué'.($detail !== '' ? ': '.mb_substr($detail, 0, 1000) : '.'));
        }

        return $process;
    }
}
