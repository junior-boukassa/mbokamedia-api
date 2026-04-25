<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreFeaturedSectionRequest;
use App\Http\Requests\Admin\UpdateFeaturedSectionRequest;
use App\Http\Resources\FeaturedSectionResource;
use App\Models\FeaturedSection;
use App\Services\AdminAuditLogger;
use App\Services\FeaturedSectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeaturedSectionController extends ApiController
{
    public function __construct(
        protected FeaturedSectionService $featuredSectionService,
        protected AdminAuditLogger $adminAuditLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FeaturedSection::class);

        $sections = FeaturedSection::query()
            ->with(['items.featureable'])
            ->orderBy('sort_order')
            ->paginate((int) $request->integer('per_page', config('api.pagination.per_page')));

        return $this->paginated(FeaturedSectionResource::collection($sections), 'Featured sections retrieved successfully.');
    }

    public function store(StoreFeaturedSectionRequest $request): JsonResponse
    {
        $this->authorize('create', FeaturedSection::class);

        $section = FeaturedSection::query()->create($request->safe()->except('items'));
        $this->featuredSectionService->syncItems($section, $request->input('items', []));
        $this->adminAuditLogger->log('featured_section.created', actor: $request->user(), request: $request, target: $section);

        return $this->success(
            FeaturedSectionResource::make($section->fresh()->load(['items.featureable'])),
            'Featured section created successfully.',
            201,
        );
    }

    public function show(FeaturedSection $featuredSection): JsonResponse
    {
        $this->authorize('view', $featuredSection);

        return $this->success(
            FeaturedSectionResource::make($featuredSection->load(['items.featureable'])),
            'Featured section retrieved successfully.',
        );
    }

    public function update(UpdateFeaturedSectionRequest $request, FeaturedSection $featuredSection): JsonResponse
    {
        $this->authorize('update', $featuredSection);

        $featuredSection->update($request->safe()->except('items'));

        if ($request->has('items')) {
            $this->featuredSectionService->syncItems($featuredSection, $request->input('items', []));
        }
        $this->adminAuditLogger->log('featured_section.updated', actor: $request->user(), request: $request, target: $featuredSection->fresh());

        return $this->success(
            FeaturedSectionResource::make($featuredSection->fresh()->load(['items.featureable'])),
            'Featured section updated successfully.',
        );
    }

    public function destroy(FeaturedSection $featuredSection): JsonResponse
    {
        $this->authorize('delete', $featuredSection);

        $this->adminAuditLogger->log('featured_section.deleted', actor: request()->user(), request: request(), target: $featuredSection);
        $featuredSection->delete();

        return $this->success(null, 'Featured section deleted successfully.');
    }
}
