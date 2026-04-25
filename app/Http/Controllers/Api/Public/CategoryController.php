<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends ApiController
{
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->active()
            ->withCount('articles')
            ->orderBy('name')
            ->get();

        return $this->success(CategoryResource::collection($categories), 'Public categories retrieved successfully.');
    }
}
