<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\LiveResource;
use App\Models\Live;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveController extends ApiController
{
    public function current(): JsonResponse
    {
        $live = Live::query()
            ->where('status', 'live')
            ->latest('started_at')
            ->first();

        return $this->success($live ? LiveResource::make($live) : null, 'Current live retrieved successfully.');
    }

    public function index(Request $request): JsonResponse
    {
        $lives = Live::query()
            ->orderByDesc('started_at')
            ->paginate((int) $request->integer('per_page', config('api.pagination.public_per_page', 12)));

        return $this->paginated(LiveResource::collection($lives), 'Lives retrieved successfully.');
    }
}
