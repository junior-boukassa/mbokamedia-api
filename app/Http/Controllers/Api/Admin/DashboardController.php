<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ContentStatus;
use App\Enums\NewsletterStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\DashboardStatsResource;
use App\Models\Article;
use App\Models\Contact;
use App\Models\NewsletterSubscriber;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('view dashboard'), 403);

        return $this->success(new DashboardStatsResource([
            'articles_total' => Article::query()->count(),
            'articles_drafts' => Article::query()->where('status', ContentStatus::Draft)->count(),
            'articles_published' => Article::query()->where('status', ContentStatus::Published)->count(),
            'videos_total' => Video::query()->count(),
            'contacts_total' => Contact::query()->count(),
            'newsletter_subscribers_total' => NewsletterSubscriber::query()->where('status', NewsletterStatus::Subscribed)->count(),
        ]), 'Dashboard stats retrieved successfully.');
    }
}
