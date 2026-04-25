<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreBreakingNewsRequest;
use App\Http\Requests\Admin\UpdateBreakingNewsRequest;
use App\Http\Resources\BreakingNewsResource;
use App\Models\BreakingNews;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BreakingNewsController extends ApiController
{
    public function __construct(
        protected AdminAuditLogger $adminAuditLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BreakingNews::class);

        $items = BreakingNews::query()
            ->when($request->filled('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->orderBy('sort_order')
            ->paginate((int) $request->integer('per_page', config('api.pagination.per_page')));

        return $this->paginated(BreakingNewsResource::collection($items), 'Breaking news retrieved successfully.');
    }

    public function store(StoreBreakingNewsRequest $request): JsonResponse
    {
        $this->authorize('create', BreakingNews::class);

        $breakingNews = BreakingNews::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);
        $this->adminAuditLogger->log('breaking_news.created', actor: $request->user(), request: $request, target: $breakingNews);

        return $this->success(BreakingNewsResource::make($breakingNews), 'Breaking news created successfully.', 201);
    }

    public function show(BreakingNews $breakingNews): JsonResponse
    {
        $this->authorize('view', $breakingNews);

        return $this->success(BreakingNewsResource::make($breakingNews), 'Breaking news retrieved successfully.');
    }

    public function update(UpdateBreakingNewsRequest $request, BreakingNews $breakingNews): JsonResponse
    {
        $this->authorize('update', $breakingNews);

        $breakingNews->update($request->validated());
        $this->adminAuditLogger->log('breaking_news.updated', actor: $request->user(), request: $request, target: $breakingNews->fresh());

        return $this->success(BreakingNewsResource::make($breakingNews), 'Breaking news updated successfully.');
    }

    public function destroy(BreakingNews $breakingNews): JsonResponse
    {
        $this->authorize('delete', $breakingNews);

        $this->adminAuditLogger->log('breaking_news.deleted', actor: request()->user(), request: request(), target: $breakingNews);
        $breakingNews->delete();

        return $this->success(null, 'Breaking news deleted successfully.');
    }
}
