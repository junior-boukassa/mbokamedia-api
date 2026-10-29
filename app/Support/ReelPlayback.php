<?php

namespace App\Support;

use App\Enums\VideoSourceType;
use App\Models\Video;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Ce que l'application mobile peut lire d'un reel, sans jamais passer par YouTube.
 *
 * Deux cas seulement :
 *  - un fichier envoyé depuis le dashboard (`source_type = upload`), en MP4 (ou
 *    M4V/MOV, que iOS comme Android lisent) ;
 *  - une adresse directe en `https` vers un `.mp4` ou un `.m3u8`.
 *
 * YouTube, `embed` et tout le reste donnent `null` : la vidéo reste visible sur
 * le site, pas dans l'application.
 *
 * La règle existe deux fois : en PHP ([urlFor], pour calculer `playback_url`) et
 * en SQL ([constrain], pour filtrer une liste paginée sans casser la pagination).
 * Les deux doivent toujours s'accorder ; un test le vérifie sur une série d'adresses.
 */
class ReelPlayback
{
    /** Formats de fichier envoyé que iOS et Android savent lire. */
    public const UPLOAD_EXTENSIONS = ['mp4', 'm4v', 'mov'];

    /** Adresses directes acceptées : fichier ou flux HLS. */
    public const EXTERNAL_EXTENSIONS = ['mp4', 'm3u8'];

    public static function urlFor(Video $video): ?string
    {
        $source = $video->source_type instanceof VideoSourceType
            ? $video->source_type->value
            : (string) $video->source_type;

        if ($source === VideoSourceType::Upload->value) {
            $path = trim((string) $video->video_url);

            if ($path === '' || ! self::hasExtension($path, self::UPLOAD_EXTENSIONS)) {
                return null;
            }

            return self::publicUrl($path);
        }

        if ($source === VideoSourceType::ExternalUrl->value) {
            foreach ([$video->external_url, $video->video_url] as $candidate) {
                if (self::isDirectHttpsFile($candidate)) {
                    return trim((string) $candidate);
                }
            }
        }

        return null;
    }

    /**
     * Restreint une requête aux reels dont [urlFor] donnerait une adresse.
     *
     * @param  Builder<Video>  $query
     * @return Builder<Video>
     */
    public static function constrain(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where(function (Builder $upload): void {
                $upload->where('source_type', VideoSourceType::Upload->value)
                    ->where(function (Builder $extension): void {
                        foreach (self::UPLOAD_EXTENSIONS as $ext) {
                            // Une extension en fin de chemin : le fichier envoyé n'a jamais de paramètres.
                            $extension->orWhere('video_url', 'like', "%.{$ext}");
                        }
                    });
            })->orWhere(function (Builder $external): void {
                $external->where('source_type', VideoSourceType::ExternalUrl->value)
                    ->where(function (Builder $column): void {
                        foreach (['external_url', 'video_url'] as $name) {
                            foreach (self::EXTERNAL_EXTENSIONS as $ext) {
                                $column->orWhere($name, 'like', "https://%.{$ext}")
                                    ->orWhere($name, 'like', "https://%.{$ext}?%");
                            }
                        }
                    });
            });
        });
    }

    public static function isDirectHttpsFile(?string $value): bool
    {
        $value = trim((string) $value);

        if ($value === '' || preg_match('#^https://#i', $value) !== 1) {
            return false;
        }

        return self::hasExtension($value, self::EXTERNAL_EXTENSIONS);
    }

    /**
     * @param  array<int, string>  $extensions
     */
    protected static function hasExtension(string $value, array $extensions): bool
    {
        $path = parse_url($value, PHP_URL_PATH);
        $path = is_string($path) ? $path : $value;

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $extensions, true);
    }

    protected static function publicUrl(string $path): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
