<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class LiveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'thumbnail' => $this->resolveMediaUrl($this->thumbnail),
            'stream_type' => $this->stream_type,
            'stream_url' => $this->resolveMediaUrl($this->stream_url),
            'external_url' => $this->resolveMediaUrl($this->external_url),
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
        ];
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
