<?php

namespace App\Services;

use App\Models\Article;
use App\Models\FeaturedSection;
use App\Models\Video;
use App\Support\MediaPath;
use Illuminate\Support\Facades\DB;

class FeaturedSectionService
{
    public function syncItems(FeaturedSection $section, array $items): void
    {
        DB::transaction(function () use ($section, $items): void {
            $section->items()->delete();

            foreach ($items as $item) {
                [$featureableType, $featureableId] = $this->resolveFeatureable($item);

                $section->items()->create([
                    'featureable_type' => $featureableType,
                    'featureable_id' => $featureableId,
                    'manual_title' => $item['manual_title'] ?? null,
                    'manual_excerpt' => $item['manual_excerpt'] ?? null,
                    'manual_url' => $item['manual_url'] ?? null,
                    'manual_image' => MediaPath::normalize($item['manual_image'] ?? null),
                    'sort_order' => $item['sort_order'] ?? 0,
                    'is_active' => $item['is_active'] ?? true,
                ]);
            }
        });
    }

    protected function resolveFeatureable(array $item): array
    {
        return match ($item['featureable_type'] ?? null) {
            'article' => [Article::class, $item['featureable_id'] ?? null],
            'video' => [Video::class, $item['featureable_id'] ?? null],
            default => [null, null],
        };
    }
}
