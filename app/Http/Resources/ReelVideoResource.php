<?php

namespace App\Http\Resources;

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
            'published_at' => $this->published_at?->toISOString(),
        ];
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
