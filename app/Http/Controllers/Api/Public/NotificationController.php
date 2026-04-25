<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ArticleNotificationResource;
use App\Models\ArticleNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $notifications = ArticleNotification::query()
            ->whereHas('article', fn ($query) => $query->published())
            ->latest('published_at')
            ->paginate((int) $request->integer('per_page', 12));

        return $this->paginated(
            ArticleNotificationResource::collection($notifications),
            'Article notifications retrieved successfully.',
        );
    }
}
