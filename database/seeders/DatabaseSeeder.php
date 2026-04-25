<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            AdminUserSeeder::class,
            CategorySeeder::class,
            TagSeeder::class,
            SettingSeeder::class,
            ArticleSeeder::class,
            VideoSeeder::class,
            BreakingNewsSeeder::class,
            FeaturedSectionSeeder::class,
        ]);
    }
}
