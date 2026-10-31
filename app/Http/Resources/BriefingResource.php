<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BriefingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'edition_date' => $this->edition_date?->toDateString(),
            'presenter' => $this->presenter,
            'text' => $this->text,
            'status' => $this->status,
            'notify_readers' => (bool) $this->notify_readers,
            'published_at' => $this->published_at?->toISOString(),
            'audio_url' => $this->audio_status === 'ready' && $this->audio_path !== null
                ? url('/api/public/briefings/'.$this->id.'/audio')
                : null,
            'audio_preview_url' => $request->user() !== null
                && $this->audio_status === 'ready'
                && $this->audio_path !== null
                    ? url('/api/admin/briefings/'.$this->id.'/audio')
                    : null,
            'audio_duration_seconds' => $this->audio_duration_seconds,
            'audio_size_bytes' => $this->audio_size_bytes,
            'audio_status' => $this->audio_status,
            'audio_error' => $this->whenLoaded('transcode', fn () => $this->transcode?->last_error),
            'notification_sent_at' => $this->notification_sent_at?->toISOString(),
            'articles' => $this->whenLoaded('articles', fn () => $this->articles->map(fn ($article): array => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'featured_image' => $article->featured_image,
                'published_at' => $article->published_at?->toISOString(),
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
