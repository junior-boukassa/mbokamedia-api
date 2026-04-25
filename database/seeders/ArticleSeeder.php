<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->first();
        $categories = Category::query()->pluck('id')->all();
        $tagIds = Tag::query()->pluck('id')->all();

        Article::factory(8)
            ->recycle($author)
            ->sequence(fn ($sequence) => [
                'category_id' => $categories[$sequence->index % max(count($categories), 1)] ?? Category::factory(),
            ])
            ->create()
            ->each(fn (Article $article) => $article->tags()->sync(collect($tagIds)->shuffle()->take(3)));
    }
}
