<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'video_url' => $this->video_url,
            'source_type' => $this->source_type?->value,
            'status' => $this->status?->value,
            'is_featured' => $this->is_featured,
            'published_at' => $this->published_at,
            'author' => UserResource::make($this->whenLoaded('author')),
            'created_at' => $this->created_at,
        ];
    }
}
