<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\FeaturedSectionResource;
use App\Models\FeaturedSection;
use Illuminate\Http\JsonResponse;

class FeaturedSectionController extends ApiController
{
    public function index(): JsonResponse
    {
        $sections = FeaturedSection::query()
            ->active()
            ->with(['items' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'), 'items.featureable'])
            ->get();

        return $this->success(FeaturedSectionResource::collection($sections), 'Featured content retrieved successfully.');
    }
}
