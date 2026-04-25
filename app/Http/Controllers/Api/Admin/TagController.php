<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreTagRequest;
use App\Http\Requests\Admin\UpdateTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends ApiController
{
    public function __construct(
        protected AdminAuditLogger $adminAuditLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Tag::class);

        $tags = Tag::query()
            ->withCount('articles')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->latest()
            ->paginate((int) $request->integer('per_page', config('api.pagination.per_page')));

        return $this->paginated(TagResource::collection($tags), 'Tags retrieved successfully.');
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $this->authorize('create', Tag::class);

        $tag = Tag::query()->create($request->validated());
        $this->adminAuditLogger->log('tag.created', actor: $request->user(), request: $request, target: $tag);

        return $this->success(TagResource::make($tag), 'Tag created successfully.', 201);
    }

    public function show(Tag $tag): JsonResponse
    {
        $this->authorize('view', $tag);

        return $this->success(TagResource::make($tag->loadCount('articles')), 'Tag retrieved successfully.');
    }

    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse
    {
        $this->authorize('update', $tag);

        $tag->update($request->validated());
        $this->adminAuditLogger->log('tag.updated', actor: $request->user(), request: $request, target: $tag->fresh());

        return $this->success(TagResource::make($tag->fresh()->loadCount('articles')), 'Tag updated successfully.');
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $this->authorize('delete', $tag);

        $this->adminAuditLogger->log('tag.deleted', actor: request()->user(), request: request(), target: $tag);
        $tag->delete();

        return $this->success(null, 'Tag deleted successfully.');
    }
}
