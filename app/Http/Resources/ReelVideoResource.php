<?php

namespace App\Http\Resources;

use App\Support\ReelPlayback;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ReelVideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'video_url' => $this->resolveMediaUrl($this->video_url),
            'thumbnail' => $this->resolveMediaUrl($this->thumbnail),
            'source_type' => $this->normalizeSourceType($this->source_type),
            'youtube_url' => $this->youtube_url,
            'external_url' => $this->resolveMediaUrl($this->external_url),
            'views_count' => (int) ($this->views_count ?? 0),
            'is_featured' => (bool) $this->is_featured,
            // Ajouts : l'application lit `playback_url` et rien d'autre pour
            // jouer la vidéo. Les champs ci-dessus restent tels quels : les
            // versions de l'application déjà installées s'en servent encore.
            'playback_url' => ReelPlayback::urlFor($this->resource),
            'duration_seconds' => $this->duration_seconds,
            'width' => $this->width,
            'height' => $this->height,
            'share_url' => $this->shareUrl(),
            'published_at' => $this->published_at?->toISOString(),
        ];
    }

    /**
     * Lien web à partager. Le site n'a pas de page par vidéo (`/videos` est une
     * liste) : on partage cette page. Rien si l'adresse du site n'est pas une
     * adresse https réelle (valeur de développement dans `.env`) : l'application
     * retombe alors sur sa propre adresse.
     */
    protected function shareUrl(): ?string
    {
        $base = rtrim((string) config('api.frontend.url'), '/');

        return str_starts_with($base, 'https://') ? $base.'/videos' : null;
    }

    protected function normalizeSourceType(mixed $sourceType): ?string
    {
        if ($sourceType === null) {
            return null;
        }

        $value = $sourceType instanceof \BackedEnum
            ? $sourceType->value
            : (is_string($sourceType) ? $sourceType : (string) $sourceType);

        return match ($value) {
            'external_url' => 'upload',
            'embed' => 'youtube',
            'upload' => 'upload',
            'youtube' => 'youtube',
            'external' => 'external',
            default => $value,
        };
    }

    protected function resolveMediaUrl(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }
}
