<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'site_name' => $this->site_name,
            'site_slogan' => $this->site_slogan,
            'primary_email' => $this->primary_email,
            'primary_phone' => $this->primary_phone,
            'logo' => $this->logo,
            'favicon' => $this->favicon,
            'social_links' => $this->social_links ?? [],
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'seo_keywords' => $this->seo_keywords,
            'updated_at' => $this->updated_at,
        ];
    }
}
