<?php

namespace Database\Factories;

use App\Models\BreakingNews;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BreakingNews>
 */
class BreakingNewsFactory extends Factory
{
    protected $model = BreakingNews::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(8),
            'link' => fake()->optional()->url(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 20),
            'created_by' => User::factory(),
        ];
    }
}
