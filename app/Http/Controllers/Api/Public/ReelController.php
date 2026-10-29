<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ReelVideoResource;
use App\Models\Video;
use App\Support\ReelPlayback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReelController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', config('api.pagination.public_per_page', 12));
        $perPage = max(1, min(20, $perPage));

        $videos = Video::query()
            ->published()
            ->where('is_reel', true)
            // Avec `playable=1` (l'application), seulement ce qu'elle peut lire
            // elle-même. Sans le paramètre (le site), rien ne change.
            ->when($request->boolean('playable'), fn ($query) => ReelPlayback::constrain($query))
            ->latest('published_at')
            ->paginate($perPage);

        return $this->paginated(ReelVideoResource::collection($videos), 'Reels retrieved successfully.');
    }

    /**
     * Un reel publié précis : sert à l'ouvrir depuis une notification ou un lien.
     */
    public function show(string $slug): JsonResponse
    {
        $video = $this->publishedReel($slug);

        return $this->success(ReelVideoResource::make($video), 'Reel retrieved successfully.');
    }

    /**
     * Compte une vue. Appelée par l'application après quelques secondes de
     * lecture réelle ; la limite de débit (voir routes/api.php) borne les abus.
     */
    public function view(string $slug): JsonResponse
    {
        $video = $this->publishedReel($slug);

        Video::query()->whereKey($video->getKey())->increment('views_count');

        return $this->success([
            'views_count' => (int) Video::query()->whereKey($video->getKey())->value('views_count'),
        ], 'View counted.');
    }

    protected function publishedReel(string $slug): Video
    {
        return Video::query()
            ->published()
            ->where('is_reel', true)
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
