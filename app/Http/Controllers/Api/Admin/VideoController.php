<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ContentStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreVideoRequest;
use App\Http\Requests\Admin\UpdateVideoRequest;
use App\Http\Resources\VideoResource;
use App\Models\ReelTranscode;
use App\Models\Video;
use App\Services\AdminAuditLogger;
use App\Services\ReelPublicationNotifier;
use App\Support\MediaPath;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VideoController extends ApiController
{
    public function __construct(
        protected AdminAuditLogger $adminAuditLogger,
        protected ReelPublicationNotifier $reelPublicationNotifier,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Video::class);

        $videos = Video::query()
            ->with(['author', 'transcode'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), fn ($query) => $query->where('title', 'like', '%'.$request->string('search').'%'))
            // La page Reels du dashboard envoie `is_reel` : sans ce filtre elle
            // affichait toutes les vidéos. Sans le paramètre, rien ne change.
            ->when($request->has('is_reel'), fn ($query) => $query->where('is_reel', $request->boolean('is_reel')))
            ->latest()
            ->paginate((int) $request->integer('per_page', config('api.pagination.per_page')));

        return $this->paginated(VideoResource::collection($videos), 'Videos retrieved successfully.');
    }

    public function store(StoreVideoRequest $request): JsonResponse
    {
        $this->authorize('create', Video::class);

        $payload = $this->preparePayload($request->validated(), $request->user()->id);
        $shouldQueueTranscode = $this->isProcessableReelUpload($payload);
        $targetStatus = $payload['status'];
        $targetPublishedAt = $payload['published_at'] ?? null;

        if ($shouldQueueTranscode) {
            $payload['status'] = ContentStatus::Draft->value;
            $payload['published_at'] = null;
        }

        $video = DB::transaction(function () use ($payload, $request, $shouldQueueTranscode, $targetStatus, $targetPublishedAt): Video {
            $video = Video::query()->create([
                ...$payload,
                'author_id' => $payload['author_id'] ?? $request->user()->id,
            ]);

            if ($shouldQueueTranscode) {
                $video = $video->fresh();
                ReelTranscode::query()->create([
                    'video_id' => $video->id,
                    'status' => ReelTranscode::STATUS_QUEUED,
                    'source_path' => $video->video_url,
                    'target_status' => $targetStatus,
                    'target_published_at' => $targetPublishedAt,
                    'target_notify_on_publish' => $video->notify_on_publish,
                ]);
            }

            return $video->fresh(['author', 'transcode']);
        });
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

        $this->reelPublicationNotifier->notifyIfDue($video);

        return $this->success(VideoResource::make($video), 'Video created successfully.', 201);
    }

    public function show(Video $video): JsonResponse
    {
        $this->authorize('view', $video);

        return $this->success(VideoResource::make($video->load(['author', 'transcode'])), 'Video retrieved successfully.');
    }

    public function update(UpdateVideoRequest $request, Video $video): JsonResponse
    {
        $this->authorize('update', $video);

        $previousStatus = $video->status?->value;
        $video = DB::transaction(function () use ($request, $video): Video {
            $video = Video::query()->lockForUpdate()->findOrFail($video->id);
            $payload = $this->preparePayload(
                $request->validated(),
                $video->author_id ?? $request->user()->id,
                $video,
            );
            $transcode = $video->transcode()->lockForUpdate()->first();
            $isPending = in_array($transcode?->status, [
                ReelTranscode::STATUS_QUEUED,
                ReelTranscode::STATUS_PROCESSING,
            ], true);
            $effectivePayload = [
                'source_type' => $video->source_type?->value,
                'video_url' => $video->video_url,
                'is_reel' => $video->is_reel,
                'status' => $video->status?->value,
                'published_at' => $video->published_at,
                'notify_on_publish' => $video->notify_on_publish,
                ...$payload,
            ];

            if ($isPending && ! array_key_exists('status', $payload)) {
                $effectivePayload['status'] = $transcode->target_status;
            }
            if ($isPending && ! array_key_exists('published_at', $payload)) {
                $effectivePayload['published_at'] = $transcode->target_published_at;
            }
            if ($isPending && ! array_key_exists('notify_on_publish', $payload)) {
                $effectivePayload['notify_on_publish'] = $transcode->target_notify_on_publish;
            }

            $processable = $this->isProcessableReelUpload($effectivePayload);
            $sourceChanged = $effectivePayload['video_url'] !== $video->video_url
                || $effectivePayload['source_type'] !== $video->source_type?->value
                || (bool) $effectivePayload['is_reel'] !== (bool) $video->is_reel;
            $shouldQueueTranscode = $processable && (
                $sourceChanged
                || in_array($transcode?->status, [ReelTranscode::STATUS_FAILED, ReelTranscode::STATUS_CANCELLED], true)
            );

            if ($isPending && $processable) {
                $payload['status'] = ContentStatus::Draft->value;
                $payload['published_at'] = null;
                $video->update($payload);
                $transcode->update([
                    'status' => $sourceChanged ? ReelTranscode::STATUS_QUEUED : $transcode->status,
                    'source_path' => $effectivePayload['video_url'],
                    'target_status' => $effectivePayload['status'],
                    'target_published_at' => $effectivePayload['published_at'],
                    'target_notify_on_publish' => $effectivePayload['notify_on_publish'],
                    'lock_token' => $sourceChanged ? null : $transcode->lock_token,
                    'started_at' => $sourceChanged ? null : $transcode->started_at,
                    'attempts' => $sourceChanged ? 0 : $transcode->attempts,
                    'last_error' => null,
                ]);
            } elseif ($shouldQueueTranscode) {
                $payload['status'] = ContentStatus::Draft->value;
                $payload['published_at'] = null;
                $video->update($payload);
                ReelTranscode::query()->updateOrCreate(
                    ['video_id' => $video->id],
                    [
                        'status' => ReelTranscode::STATUS_QUEUED,
                        'source_path' => $effectivePayload['video_url'],
                        'target_status' => $effectivePayload['status'],
                        'target_published_at' => $effectivePayload['published_at'],
                        'target_notify_on_publish' => $effectivePayload['notify_on_publish'],
                        'lock_token' => null,
                        'started_at' => null,
                        'attempts' => 0,
                        'last_error' => null,
                    ],
                );
            } else {
                $video->update($payload);

                if ($isPending) {
                    $transcode->update([
                        'status' => ReelTranscode::STATUS_CANCELLED,
                        'lock_token' => null,
                        'started_at' => null,
                    ]);
                }
            }

            return $video->fresh(['author', 'transcode']);
        });
        $this->adminAuditLogger->log(
            'video.updated',
            actor: $request->user(),
            request: $request,
            target: $video,
            metadata: [
                'previous_status' => $previousStatus,
                'current_status' => $video->status?->value,
            ],
        );

        $this->reelPublicationNotifier->notifyIfDue($video);

        return $this->success(VideoResource::make($video), 'Video updated successfully.');
    }

    public function destroy(Video $video): JsonResponse
    {
        $this->authorize('delete', $video);

        $this->adminAuditLogger->log('video.deleted', actor: request()->user(), request: request(), target: $video);
        $video->delete();

        return $this->success(null, 'Video deleted successfully.');
    }

    protected function preparePayload(array $data, int $defaultAuthorId, ?Video $existing = null): array
    {
        $publishedAtValue = array_key_exists('published_at', $data)
            ? $data['published_at']
            : $existing?->published_at;
        $publishedAt = $publishedAtValue !== null
            ? Carbon::parse($publishedAtValue)
            : null;
        $status = $data['status'] ?? $existing?->status?->value ?? ContentStatus::Draft->value;

        if ($status === ContentStatus::Published->value && $publishedAt?->isFuture()) {
            $status = ContentStatus::Scheduled->value;
        }

        $data['status'] = $status;
        $data['author_id'] = $data['author_id'] ?? $defaultAuthorId;
        $data['thumbnail'] = MediaPath::normalize($data['thumbnail'] ?? null);
        if (array_key_exists('published_at', $data) || $existing === null) {
            $data['published_at'] = $publishedAt;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isProcessableReelUpload(array $payload): bool
    {
        if (! (bool) ($payload['is_reel'] ?? false)
            || ($payload['source_type'] ?? null) !== 'upload'
            || ! is_string($payload['video_url'] ?? null)) {
            return false;
        }

        $url = trim($payload['video_url']);
        if ($url === '' || parse_url($url, PHP_URL_SCHEME) !== null || str_starts_with($url, '/')) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo(is_string($path) ? $path : '', PATHINFO_EXTENSION));

        return in_array($extension, ['mp4', 'mov'], true);
    }
}
