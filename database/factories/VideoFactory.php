<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\VideoSourceType;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    protected $model = Video::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'thumbnail' => 'media/videos/default.jpg',
            'video_url' => fake()->url(),
            'source_type' => VideoSourceType::ExternalUrl,
            'status' => ContentStatus::Published,
            'is_featured' => fake()->boolean(15),
            'published_at' => now()->subHours(6),
            'author_id' => User::factory(),
        ];
    }
}
