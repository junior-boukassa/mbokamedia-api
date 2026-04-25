<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ContentStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreVideoRequest;
use App\Http\Requests\Admin\UpdateVideoRequest;
use App\Http\Resources\VideoResource;
use App\Models\Video;
use App\Services\AdminAuditLogger;
use App\Support\MediaPath;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoController extends ApiController
{
    public function __construct(
        protected AdminAuditLogger $adminAuditLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Video::class);

        $videos = Video::query()
            ->with('author')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), fn ($query) => $query->where('title', 'like', '%'.$request->string('search').'%'))
            ->latest()
            ->paginate((int) $request->integer('per_page', config('api.pagination.per_page')));

        return $this->paginated(VideoResource::collection($videos), 'Videos retrieved successfully.');
    }

    public function store(StoreVideoRequest $request): JsonResponse
    {
        $this->authorize('create', Video::class);

        $video = Video::query()->create($this->preparePayload($request->validated(), $request->user()->id));
        $this->adminAuditLogger->log(
            'video.created',
            actor: $request->user(),
            request: $request,
            target: $video,
            metadata: [
                'status' => $video->status?->value,
                'author_id' => $video->author_id,
            ],
        );

        return $this->success(VideoResource::make($video->load('author')), 'Video created successfully.', 201);
    }

    public function show(Video $video): JsonResponse
    {
        $this->authorize('view', $video);

        return $this->success(VideoResource::make($video->load('author')), 'Video retrieved successfully.');
    }

    public function update(UpdateVideoRequest $request, Video $video): JsonResponse
    {
        $this->authorize('update', $video);

        $previousStatus = $video->status?->value;
        $video->update($this->preparePayload($request->validated(), $video->author_id ?? $request->user()->id));
        $this->adminAuditLogger->log(
            'video.updated',
            actor: $request->user(),
            request: $request,
            target: $video->fresh(),
            metadata: [
                'previous_status' => $previousStatus,
                'current_status' => $video->fresh()->status?->value,
            ],
        );

        return $this->success(VideoResource::make($video->fresh()->load('author')), 'Video updated successfully.');
    }

    public function destroy(Video $video): JsonResponse
    {
        $this->authorize('delete', $video);

        $this->adminAuditLogger->log('video.deleted', actor: request()->user(), request: request(), target: $video);
        $video->delete();

        return $this->success(null, 'Video deleted successfully.');
    }

    protected function preparePayload(array $data, int $defaultAuthorId): array
    {
        $publishedAt = isset($data['published_at']) ? Carbon::parse($data['published_at']) : null;
        $status = $data['status'] ?? ContentStatus::Draft->value;

        if ($status === ContentStatus::Published->value && $publishedAt?->isFuture()) {
            $status = ContentStatus::Scheduled->value;
        }

        $data['status'] = $status;
        $data['author_id'] = $data['author_id'] ?? $defaultAuthorId;
        $data['thumbnail'] = MediaPath::normalize($data['thumbnail'] ?? null);

        return $data;
    }
}
