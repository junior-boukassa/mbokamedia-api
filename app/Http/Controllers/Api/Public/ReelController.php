<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ReelVideoResource;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReelController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', config('api.pagination.public_per_page', 12));
        $perPage = max(1, min(20, $perPage));

        $videos = Video::query()
            ->published()
            ->where('is_reel', true)
            ->latest('published_at')
            ->paginate($perPage);

        return $this->paginated(ReelVideoResource::collection($videos), 'Reels retrieved successfully.');
    }
}
