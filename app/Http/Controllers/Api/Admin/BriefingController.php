<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreBriefingRequest;
use App\Http\Requests\Admin\UpdateBriefingRequest;
use App\Http\Resources\BriefingResource;
use App\Models\Briefing;
use App\Models\BriefingAudioTranscode;
use App\Services\AdminAuditLogger;
use App\Services\BriefingAudioTranscoder;
use App\Services\BriefingPublicationNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class BriefingController extends ApiController
{
    public function __construct(
        protected AdminAuditLogger $adminAuditLogger,
        protected BriefingPublicationNotifier $publicationNotifier,
        protected BriefingAudioTranscoder $audioTranscoder,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Briefing::class);
        $perPage = max(1, min(50, (int) $request->integer('per_page', 10)));

        $briefings = Briefing::query()
            ->with(['articles', 'transcode'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), fn ($query) => $query->where(function ($query) use ($request): void {
                $search = '%'.$request->string('search').'%';
                $query->where('title', 'like', $search)->orWhere('presenter', 'like', $search);
            }))
            ->orderByDesc('edition_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->paginated(BriefingResource::collection($briefings), 'Briefings retrieved successfully.');
    }

    public function store(StoreBriefingRequest $request): JsonResponse
    {
        $this->authorize('create', Briefing::class);
        $data = $request->validated();
        $this->validatePublishable($data['status'], $data['text'] ?? '', null);

        $briefing = DB::transaction(function () use ($data): Briefing {
            $briefing = Briefing::query()->create([
                'title' => $data['title'],
                'edition_date' => $data['edition_date'],
                'presenter' => $data['presenter'],
                'text' => $data['text'] ?? '',
                'status' => $data['status'],
                'notify_readers' => (bool) ($data['notify_readers'] ?? false),
                'published_at' => $data['status'] === 'published' ? now() : null,
            ]);
            $briefing->articles()->sync($this->articleSync($data['article_ids'] ?? []));

            return $briefing;
        });

        $briefing->load(['articles', 'transcode']);
        $this->adminAuditLogger->log(
            'briefing.created',
            actor: $request->user(),
            request: $request,
            target: $briefing,
            metadata: ['status' => $briefing->status],
        );
        $this->publicationNotifier->notifyOnFirstPublication($briefing);

        return $this->success(BriefingResource::make($briefing), 'Briefing created successfully.', 201);
    }

    public function show(Briefing $briefing): JsonResponse
    {
        $this->authorize('view', $briefing);

        return $this->success(
            BriefingResource::make($briefing->load(['articles', 'transcode'])),
            'Briefing retrieved successfully.',
        );
    }

    public function update(UpdateBriefingRequest $request, Briefing $briefing): JsonResponse
    {
        $this->authorize('update', $briefing);
        $data = $request->validated();
        $wasPublished = $briefing->status === 'published';

        $briefing = DB::transaction(function () use ($data, $briefing): Briefing {
            $briefing = Briefing::query()->lockForUpdate()->findOrFail($briefing->id);
            $status = $data['status'] ?? $briefing->status;
            $text = $data['text'] ?? $briefing->text;
            $this->validatePublishable($status, $text, $briefing);

            $briefing->fill(array_intersect_key($data, array_flip([
                'title',
                'edition_date',
                'presenter',
                'text',
                'status',
                'notify_readers',
            ])));
            if ($status === 'published' && ! $briefing->published_at) {
                $briefing->published_at = now();
            } elseif ($status !== 'published') {
                $briefing->published_at = null;
            }
            $briefing->save();

            if (array_key_exists('article_ids', $data)) {
                $briefing->articles()->sync($this->articleSync($data['article_ids']));
            }

            return $briefing->fresh(['articles', 'transcode']);
        });

        $this->adminAuditLogger->log(
            'briefing.updated',
            actor: $request->user(),
            request: $request,
            target: $briefing,
            metadata: ['status' => $briefing->status],
        );
        if (! $wasPublished && $briefing->status === 'published') {
            $this->publicationNotifier->notifyOnFirstPublication($briefing);
        }

        return $this->success(BriefingResource::make($briefing), 'Briefing updated successfully.');
    }

    public function uploadAudio(Request $request, Briefing $briefing): JsonResponse
    {
        $this->authorize('update', $briefing);
        $maxBytes = (int) config('briefing-audio.max_upload_bytes');
        $request->validate([
            'file' => ['required', 'file', 'max:'.(int) ceil($maxBytes / 1024)],
            'target_status' => ['sometimes', 'in:draft,published'],
            'notify_readers' => ['sometimes', 'boolean'],
        ]);

        $file = $request->file('file');
        if ($file === null || ! $file->isValid()) {
            throw ValidationException::withMessages(['file' => 'Le transfert du fichier audio a échoué.']);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        try {
            $this->audioTranscoder->inspectUpload($file->getRealPath(), $extension);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $sourcePath = $file->storeAs(
            'briefings/sources',
            Str::uuid().'.'.$extension,
            'public',
        );
        if (! is_string($sourcePath)) {
            throw new RuntimeException('Impossible d’enregistrer le fichier audio importé.');
        }

        try {
            $briefing = DB::transaction(function () use ($request, $briefing, $sourcePath): Briefing {
                $briefing = Briefing::query()->lockForUpdate()->findOrFail($briefing->id);
                $targetStatus = (string) $request->input('target_status', $briefing->status);
                $notifyReaders = $request->has('notify_readers')
                    ? $request->boolean('notify_readers')
                    : $briefing->notify_readers;
                $this->validatePublishable($targetStatus, $briefing->text, $briefing, false);

                $transcode = $briefing->transcode()->lockForUpdate()->first();
                $previousSource = $transcode?->source_path;
                $previousAudio = $briefing->audio_path;

                $briefing->update([
                    'status' => 'draft',
                    'published_at' => null,
                    'notify_readers' => $notifyReaders,
                    'audio_status' => BriefingAudioTranscode::STATUS_QUEUED,
                    'audio_duration_seconds' => null,
                    'audio_size_bytes' => null,
                ]);
                BriefingAudioTranscode::query()->updateOrCreate(
                    ['briefing_id' => $briefing->id],
                    [
                        'status' => BriefingAudioTranscode::STATUS_QUEUED,
                        'source_path' => $sourcePath,
                        'previous_audio_path' => $previousAudio,
                        'target_status' => $targetStatus,
                        'target_notify_readers' => $notifyReaders,
                        'lock_token' => null,
                        'started_at' => null,
                        'attempts' => 0,
                        'last_error' => null,
                    ],
                );

                if ($previousSource !== null && $previousSource !== $sourcePath) {
                    $this->deleteManagedFile($previousSource, 'briefings/sources/');
                }

                return $briefing->fresh(['articles', 'transcode']);
            });
        } catch (Throwable $exception) {
            $this->deleteManagedFile($sourcePath, 'briefings/sources/');
            throw $exception;
        }

        $this->adminAuditLogger->log(
            'briefing.audio_queued',
            actor: $request->user(),
            request: $request,
            target: $briefing,
            metadata: ['audio_status' => $briefing->audio_status],
        );

        return $this->success(BriefingResource::make($briefing), 'Audio queued for verification.', 202);
    }

    public function audio(Briefing $briefing): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('view', $briefing);
        $path = $briefing->audio_path;
        if ($briefing->audio_status !== BriefingAudioTranscode::STATUS_READY
            || ! is_string($path)
            || ! str_starts_with($path, 'briefings/audio/')
            || str_contains($path, '..')
            || str_contains($path, "\0")) {
            abort(404);
        }

        $disk = Storage::disk('public');
        $root = realpath($disk->path(''));
        $file = realpath($disk->path($path));
        if ($root === false || $file === false
            || ! str_starts_with($file, $root.DIRECTORY_SEPARATOR)
            || ! is_file($file)) {
            abort(404);
        }

        return response()->file($file, [
            'Content-Type' => 'audio/mp4',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function retryAudio(Request $request, Briefing $briefing): JsonResponse
    {
        $this->authorize('update', $briefing);
        $request->validate([
            'target_status' => ['sometimes', 'in:draft,published'],
            'notify_readers' => ['sometimes', 'boolean'],
        ]);
        $transcode = $briefing->transcode;

        if ($briefing->audio_status !== BriefingAudioTranscode::STATUS_FAILED
            || $transcode === null
            || $transcode->attempts >= (int) config('briefing-audio.max_attempts')
            || ! Storage::disk('public')->exists($transcode->source_path)) {
            throw ValidationException::withMessages([
                'audio' => 'Ce traitement ne peut pas être repris. Importez à nouveau le fichier audio.',
            ]);
        }

        $targetStatus = (string) $request->input('target_status', $transcode->target_status);
        $notifyReaders = $request->has('notify_readers')
            ? $request->boolean('notify_readers')
            : $transcode->target_notify_readers;
        $this->validatePublishable($targetStatus, $briefing->text, $briefing, false);

        DB::transaction(function () use ($briefing, $transcode, $targetStatus, $notifyReaders): void {
            $lockedBriefing = Briefing::query()->lockForUpdate()->findOrFail($briefing->id);
            $lockedTranscode = BriefingAudioTranscode::query()->whereKey($transcode->id)->lockForUpdate()->firstOrFail();
            $lockedBriefing->update([
                'status' => 'draft',
                'published_at' => null,
                'notify_readers' => $notifyReaders,
                'audio_status' => BriefingAudioTranscode::STATUS_QUEUED,
            ]);
            $lockedTranscode->update([
                'status' => BriefingAudioTranscode::STATUS_QUEUED,
                'target_status' => $targetStatus,
                'target_notify_readers' => $notifyReaders,
                'lock_token' => null,
                'started_at' => null,
                'last_error' => null,
            ]);
        });

        return $this->success(
            BriefingResource::make($briefing->fresh(['articles', 'transcode'])),
            'Audio processing queued again.',
            202,
        );
    }

    public function destroy(Request $request, Briefing $briefing): JsonResponse
    {
        $this->authorize('delete', $briefing);
        $this->adminAuditLogger->log(
            'briefing.deleted',
            actor: $request->user(),
            request: $request,
            target: $briefing,
            metadata: ['status' => $briefing->status],
        );

        $paths = [
            $briefing->audio_path,
            $briefing->transcode?->source_path,
            $briefing->transcode?->previous_audio_path,
        ];
        $briefing->transcode()?->delete();
        $briefing->delete();
        foreach (array_unique(array_filter($paths)) as $path) {
            $this->deleteManagedFile($path, str_starts_with($path, 'briefings/sources/')
                ? 'briefings/sources/'
                : 'briefings/audio/');
        }

        return $this->success(null, 'Briefing deleted successfully.');
    }

    /**
     * @param  array<int, int>  $articleIds
     * @return array<int, array{position: int}>
     */
    private function articleSync(array $articleIds): array
    {
        $result = [];
        foreach (array_values(array_unique($articleIds)) as $position => $articleId) {
            $result[$articleId] = ['position' => $position];
        }

        return $result;
    }

    private function validatePublishable(
        string $status,
        string $text,
        ?Briefing $briefing,
        bool $checkAudio = true,
    ): void {
        if ($status !== 'published') {
            return;
        }
        if (trim($text) === '') {
            throw ValidationException::withMessages(['text' => 'Un briefing publié doit contenir son texte.']);
        }
        if ($checkAudio && ($briefing === null
            || $briefing->audio_status !== 'ready'
            || $briefing->audio_path === null)) {
            throw ValidationException::withMessages(['audio' => 'Un briefing publié doit disposer d’un audio prêt.']);
        }
    }

    private function deleteManagedFile(string $path, string $prefix): void
    {
        if (str_starts_with($path, $prefix)
            && ! str_contains($path, '..')
            && ! str_contains($path, "\0")) {
            Storage::disk('public')->delete($path);
        }
    }
}
