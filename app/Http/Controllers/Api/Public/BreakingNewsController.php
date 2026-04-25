<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\BreakingNewsResource;
use App\Models\BreakingNews;
use Illuminate\Http\JsonResponse;

class BreakingNewsController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->success(
            BreakingNewsResource::collection(BreakingNews::query()->active()->get()),
            'Public breaking news retrieved successfully.',
        );
    }
}
