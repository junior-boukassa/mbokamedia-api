<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Enums\ContentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TagController extends ApiController
{
    /** Un tag n'est « en tendance » qu'à partir de ce nombre d'articles. */
    public const MIN_ARTICLES = 2;

    /** En dessous de ce nombre de tendances sur 7 jours, on élargit à 30 jours. */
    public const MIN_TRENDING = 4;

    /**
     * Tags portés par des articles publiés dans les 7 derniers jours (30 si
     * moins de 4 tags se détachent), du plus fréquent au moins fréquent.
     * Liste vide si rien ne ressort : l'application masque alors le bloc.
     */
    public function trending(Request $request): JsonResponse
    {
        $limit = max(1, min(12, (int) $request->integer('limit', 8)));

        $tags = Cache::remember(
            "public.tags.trending.{$limit}",
            now()->addMinutes(15),
            function () use ($limit): array {
                $recent = $this->within(7, $limit);

                return count($recent) >= self::MIN_TRENDING ? $recent : $this->within(30, $limit);
            },
        );

        return $this->success($tags, 'Trending tags retrieved successfully.');
    }

    /**
     * @return array<int, array{name: string, slug: string, articles_count: int}>
     */
    protected function within(int $days, int $limit): array
    {
        return DB::table('tags')
            ->join('article_tag', 'article_tag.tag_id', '=', 'tags.id')
            ->join('articles', 'articles.id', '=', 'article_tag.article_id')
            ->where('articles.status', ContentStatus::Published->value)
            ->whereNotNull('articles.published_at')
            ->where('articles.published_at', '<=', now())
            ->where('articles.published_at', '>=', now()->subDays($days))
            ->groupBy('tags.id', 'tags.name', 'tags.slug')
            ->havingRaw('count(articles.id) >= ?', [self::MIN_ARTICLES])
            ->orderByRaw('count(articles.id) desc')
            ->orderByRaw('max(articles.published_at) desc')
            ->orderBy('tags.name')
            ->limit($limit)
            ->selectRaw('tags.name as name, tags.slug as slug, count(articles.id) as articles_count')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'slug' => $row->slug,
                'articles_count' => (int) $row->articles_count,
            ])
            ->all();
    }
}
