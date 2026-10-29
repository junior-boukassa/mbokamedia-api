<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

/**
 * Un reel envoyé depuis le dashboard doit être un MP4 (H.264 + AAC).
 *
 * iOS ne lit ni le WebM ni l'OGG, et beaucoup de téléphones Android d'entrée de
 * gamme peinent avec le HEVC des iPhone récents. La règle ne vise que les reels
 * envoyés : les vidéos classiques du site gardent leurs règles actuelles.
 */
trait ValidatesReelFile
{
    protected function ensureReelIsMp4(Validator $validator, bool $isReel, ?string $sourceType, ?string $videoUrl): void
    {
        if (! $isReel || $sourceType !== 'upload') {
            return;
        }

        $path = trim((string) $videoUrl);

        if ($path === '') {
            return;
        }

        $path = parse_url($path, PHP_URL_PATH);
        $extension = strtolower(pathinfo(is_string($path) ? $path : '', PATHINFO_EXTENSION));

        if ($extension !== 'mp4') {
            $validator->errors()->add(
                'video_url',
                'Un reel doit être un fichier MP4 (H.264 + AAC). Exportez votre vidéo en MP4.',
            );
        }
    }
}
