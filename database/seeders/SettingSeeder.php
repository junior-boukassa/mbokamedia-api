<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::query()->updateOrCreate(
            ['site_name' => 'Mboka Media'],
            [
                'site_slogan' => 'Le média digital qui relie les voix du Congo et d’ailleurs.',
                'primary_email' => 'contact@mbokamedia.com',
                'primary_phone' => '+243000000000',
                'social_links' => [
                    'facebook' => 'https://facebook.com/mbokamedia',
                    'twitter' => 'https://x.com/mbokamedia',
                    'youtube' => 'https://youtube.com/@mbokamedia',
                ],
                'seo_title' => 'Mboka Media',
                'seo_description' => 'Actualités, vidéos et breaking news en continu.',
                'seo_keywords' => 'mboka media, actualites congo, video congo',
            ],
        );
    }
}
