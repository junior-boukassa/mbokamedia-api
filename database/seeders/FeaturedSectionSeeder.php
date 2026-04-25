<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\FeaturedSection;
use App\Models\Video;
use Illuminate\Database\Seeder;

class FeaturedSectionSeeder extends Seeder
{
    public function run(): void
    {
        $hero = FeaturedSection::query()->updateOrCreate(
            ['name' => 'Hero Home'],
            [
                'headline' => 'À la une',
                'description' => 'Les contenus prioritaires de la rédaction.',
                'is_active' => true,
                'sort_order' => 1,
                'max_items' => 4,
            ],
        );

        $hero->items()->delete();

        Article::query()->take(3)->get()->each(function (Article $article, int $index) use ($hero): void {
            $hero->items()->create([
                'featureable_type' => Article::class,
                'featureable_id' => $article->id,
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
        });

        $video = Video::query()->first();

        if ($video) {
            $hero->items()->create([
                'featureable_type' => Video::class,
                'featureable_id' => $video->id,
                'sort_order' => 4,
                'is_active' => true,
            ]);
        }
    }
}
