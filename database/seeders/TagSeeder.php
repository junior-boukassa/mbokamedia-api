<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Kinshasa', 'RDC', 'Afrique', 'Urgent', 'Analyse', 'Interview'] as $name) {
            Tag::query()->updateOrCreate(['name' => $name], ['name' => $name]);
        }
    }
}
