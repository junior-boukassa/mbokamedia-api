<?php

namespace App\Http\Resources;

use App\Models\Article;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeaturedSectionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resource = match ($this->featureable_type) {
            Article::class => ArticleResource::make($this->whenLoaded('featureable')),
            Video::class => VideoResource::make($this->whenLoaded('featureable')),
            default => null,
        };

        return [
            'id' => $this->id,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'content' => $resource,
            'manual_title' => $this->manual_title,
            'manual_excerpt' => $this->manual_excerpt,
            'manual_url' => $this->manual_url,
            'manual_image' => $this->manual_image,
        ];
    }
}
