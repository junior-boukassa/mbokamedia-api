<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Medium;
use App\Services\AdminAuditLogger;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends ApiController
{
    public function __construct(
        protected MediaService $mediaService,
        protected AdminAuditLogger $adminAuditLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Medium::class);

        $media = Medium::query()
            ->latestFirst()
            ->paginate((int) $request->integer('per_page', config('api.pagination.per_page')));

        return $this->paginated(MediaResource::collection($media), 'Media retrieved successfully.');
    }

    public function store(StoreMediaRequest $request): JsonResponse
    {
        $this->authorize('create', Medium::class);

        $medium = $this->mediaService->store(
            $request->file('file'),
            $request->user(),
            $request->validated(),
        );
        $this->adminAuditLogger->log('media.created', actor: $request->user(), request: $request, target: $medium);

        return $this->success(MediaResource::make($medium), 'Media uploaded successfully.', 201);
    }

    public function destroy(Medium $medium): JsonResponse
    {
        $this->authorize('delete', $medium);
        $this->adminAuditLogger->log('media.deleted', actor: request()->user(), request: request(), target: $medium);
        $this->mediaService->destroy($medium);

        return $this->success(null, 'Media deleted successfully.');
    }
}
