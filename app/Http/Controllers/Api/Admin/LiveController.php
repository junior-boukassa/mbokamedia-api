<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreLiveRequest;
use App\Http\Requests\Admin\UpdateLiveRequest;
use App\Http\Resources\LiveResource;
use App\Models\Live;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Live::class);

        $lives = Live::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('started_at')
            ->paginate((int) $request->integer('per_page', config('api.pagination.per_page', 15)));

        return $this->paginated(LiveResource::collection($lives), 'Lives retrieved successfully.');
    }

    public function store(StoreLiveRequest $request): JsonResponse
    {
        $this->authorize('create', Live::class);

        $live = Live::query()->create($this->preparePayload($request->validated(), $request->user()->id));

        return $this->success(LiveResource::make($live), 'Live created successfully.', 201);
    }

    public function show(Live $live): JsonResponse
    {
        $this->authorize('view', $live);

        return $this->success(LiveResource::make($live), 'Live retrieved successfully.');
    }

    public function update(UpdateLiveRequest $request, Live $live): JsonResponse
    {
        $this->authorize('update', $live);

        $live->update($this->preparePayload($request->validated(), $live->created_by ?? $request->user()->id));

        return $this->success(LiveResource::make($live->fresh()), 'Live updated successfully.');
    }

    public function destroy(Live $live): JsonResponse
    {
        $this->authorize('delete', $live);

        $live->delete();

        return $this->success(null, 'Live deleted successfully.');
    }

    protected function preparePayload(array $data, int $createdBy): array
    {
        $data['created_by'] = $data['created_by'] ?? $createdBy;

        if (isset($data['thumbnail'])) {
            $data['thumbnail'] = ltrim($data['thumbnail'], '/');
        }

        if (isset($data['stream_url'])) {
            $data['stream_url'] = rtrim((string) $data['stream_url'], '/');
        }

        if (isset($data['external_url'])) {
            $data['external_url'] = rtrim((string) $data['external_url'], '/');
        }

        return $data;
    }
}
