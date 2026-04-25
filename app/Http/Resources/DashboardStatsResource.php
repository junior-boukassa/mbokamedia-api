<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardStatsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'articles_total' => $this['articles_total'],
            'articles_drafts' => $this['articles_drafts'],
            'articles_published' => $this['articles_published'],
            'videos_total' => $this['videos_total'],
            'contacts_total' => $this['contacts_total'],
            'newsletter_subscribers_total' => $this['newsletter_subscribers_total'],
        ];
    }
}
