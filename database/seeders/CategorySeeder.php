<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Politique', 'description' => 'Actualites et analyses politiques.'],
            ['name' => 'Économie', 'description' => 'Finance, entreprises et développement.'],
            ['name' => 'Culture', 'description' => 'Arts, musique et patrimoine.'],
            ['name' => 'Sport', 'description' => 'Résultats, analyses et compétitions.'],
            ['name' => 'Société', 'description' => 'Faits de société et sujets de fond.'],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(['name' => $category['name']], $category);
        }
    }
}
