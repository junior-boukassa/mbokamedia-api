<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\ArticleNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleNotification>
 */
class ArticleNotificationFactory extends Factory
{
    protected $model = ArticleNotification::class;

    public function definition(): array
    {
        $title = fake()->sentence();

        return [
            'article_id' => Article::factory(),
            'title' => $title,
            'slug' => str($title)->slug()->value(),
            'excerpt' => fake()->paragraph(),
            'featured_image' => null,
            'published_at' => now(),
            'email_sent_at' => null,
            'push_sent_at' => null,
        ];
    }
}
