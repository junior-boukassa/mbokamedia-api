<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'image' => $this->resolveMediaUrl($this->image),
            'position' => (int) ($this->position ?? 0),
            // Image du dernier article publié de la rubrique : renseignée par
            // le contrôleur public, en une seule requête, pour les rubriques
            // sans image propre.
            'fallback_image' => $this->when(
                array_key_exists('fallback_image_path', $this->resource->getAttributes()),
                fn () => $this->resolveMediaUrl($this->fallback_image_path),
            ),
            'articles_count' => $this->whenCounted('articles', $this->articles_count),
            'created_at' => $this->created_at,
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
