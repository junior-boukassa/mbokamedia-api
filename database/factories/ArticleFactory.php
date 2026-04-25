<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(6),
            'excerpt' => fake()->paragraph(),
            'content' => fake()->paragraphs(4, true),
            'featured_image' => 'media/articles/default.jpg',
            'status' => ContentStatus::Published,
            'category_id' => Category::factory(),
            'author_id' => User::factory(),
            'published_at' => now()->subDay(),
            'is_featured' => fake()->boolean(20),
            'seo_title' => fake()->sentence(6),
            'seo_description' => fake()->sentence(12),
            'views_count' => fake()->numberBetween(0, 5000),
        ];
    }
}
