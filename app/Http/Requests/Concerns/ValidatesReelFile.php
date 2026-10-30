<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

/**
 * Les reels uploadés acceptent les conteneurs MP4 et MOV. Leur type réel et
 * leurs codecs sont vérifiés par le traitement asynchrone avant publication.
 *
 * iOS ne lit ni le WebM ni l'OGG, et beaucoup de téléphones Android d'entrée de
 * gamme peinent avec le HEVC des iPhone récents. La règle ne vise que les reels
 * envoyés : les vidéos classiques du site gardent leurs règles actuelles.
 */
trait ValidatesReelFile
{
    protected function ensureReelHasSupportedContainer(Validator $validator, bool $isReel, ?string $sourceType, ?string $videoUrl): void
    {
        if (! $isReel || $sourceType !== 'upload') {
            return;
        }

        $path = trim((string) $videoUrl);

        if ($path === '') {
            $validator->errors()->add('video_url', 'Importez un fichier MP4 ou MOV pour ce reel.');

            return;
        }

        if (parse_url($path, PHP_URL_SCHEME) !== null || str_starts_with($path, '/')) {
            $validator->errors()->add('video_url', 'Un reel uploadé doit utiliser le chemin du fichier stocké sur la plateforme.');

            return;
        }

        $path = parse_url($path, PHP_URL_PATH);
        $extension = strtolower(pathinfo(is_string($path) ? $path : '', PATHINFO_EXTENSION));

        if (! in_array($extension, ['mp4', 'mov'], true)) {
            $validator->errors()->add(
                'video_url',
                'Un reel importé doit être un fichier MP4 ou MOV. Son type et ses codecs seront vérifiés avant publication.',
            );
        }
    }
}
