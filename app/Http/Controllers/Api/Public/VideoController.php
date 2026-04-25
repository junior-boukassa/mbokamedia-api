<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\VideoResource;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $videos = Video::query()
            ->published()
            ->with('author')
            ->when($request->boolean('featured'), fn ($query) => $query->where('is_featured', true))
            ->latest('published_at')
            ->paginate((int) $request->integer('per_page', config('api.pagination.public_per_page')));

        return $this->paginated(VideoResource::collection($videos), 'Public videos retrieved successfully.');
    }

    public function show(Video $video): JsonResponse
    {
        abort_unless($video->status?->value === 'published' && $video->published_at?->isPast(), 404);

        return $this->success(VideoResource::make($video->load('author')), 'Video retrieved successfully.');
    }
}
