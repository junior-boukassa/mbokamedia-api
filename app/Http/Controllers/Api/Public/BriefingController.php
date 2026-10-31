<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\BriefingResource;
use App\Models\Briefing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BriefingController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(20, (int) $request->integer('per_page', 10)));
        $briefings = Briefing::query()
            ->available()
            ->with(['articles' => fn ($query) => $query->published()])
            ->orderByDesc('edition_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->paginated(BriefingResource::collection($briefings), 'Briefings retrieved successfully.');
    }

    public function latest(): JsonResponse
    {
        $briefing = Briefing::query()
            ->available()
            ->with(['articles' => fn ($query) => $query->published()])
            ->orderByDesc('edition_date')
            ->orderByDesc('id')
            ->first();

        return $this->success(
            $briefing === null ? null : BriefingResource::make($briefing),
            'Latest briefing retrieved successfully.',
        );
    }

    public function show(int $briefing): JsonResponse
    {
        $briefing = Briefing::query()
            ->available()
            ->with(['articles' => fn ($query) => $query->published()])
            ->findOrFail($briefing);

        return $this->success(BriefingResource::make($briefing), 'Briefing retrieved successfully.');
    }

    public function audio(int $briefing): BinaryFileResponse
    {
        $briefing = Briefing::query()->available()->findOrFail($briefing);
        $disk = Storage::disk('public');
        $root = realpath($disk->path(''));
        $path = $briefing->audio_path;
        if (! is_string($path)
            || ! str_starts_with($path, 'briefings/audio/')
            || str_contains($path, '..')
            || str_contains($path, "\0")) {
            abort(404);
        }

        $file = realpath($disk->path($path));
        if ($root === false || $file === false
            || ! str_starts_with($file, $root.DIRECTORY_SEPARATOR)
            || ! is_file($file)) {
            abort(404);
        }

        return response()->file($file, [
            'Content-Type' => 'audio/mp4',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=60',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
