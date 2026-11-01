<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_ranks_published_articles_by_cumulative_views(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        Sanctum::actingAs($admin);

        $popular = Article::factory()->create([
            'title' => 'Article populaire',
            'views_count' => 480,
        ]);
        $lessPopular = Article::factory()->create([
            'title' => 'Article suivi',
            'views_count' => 23,
        ]);
        Article::factory()->create([
            'status' => ContentStatus::Draft,
            'published_at' => null,
            'views_count' => 9000,
        ]);
        Article::factory()->create([
            'published_at' => now()->addDay(),
            'views_count' => 12000,
        ]);

        $response = $this->getJson('/api/admin/dashboard/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'top_articles' => [
                        ['id', 'title', 'slug', 'featured_image', 'views_count'],
                    ],
                ],
            ])
            ->assertJsonPath('data.top_articles.0.id', $popular->id)
            ->assertJsonPath('data.top_articles.0.views_count', 480)
            ->assertJsonPath('data.top_articles.1.id', $lessPopular->id)
            ->assertJsonCount(2, 'data.top_articles');
    }

    public function test_dashboard_statistics_remain_permission_protected(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $reader = User::factory()->create();
        Sanctum::actingAs($reader);

        $this->getJson('/api/admin/dashboard/stats')->assertForbidden();
    }
}
