<?php

namespace Database\Seeders;

use App\Models\BreakingNews;
use App\Models\User;
use Illuminate\Database\Seeder;

class BreakingNewsSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->first();

        BreakingNews::factory(4)->recycle($author)->create();
    }
}
