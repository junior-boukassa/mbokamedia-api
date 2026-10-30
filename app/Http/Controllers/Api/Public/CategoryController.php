<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\CategoryResource;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends ApiController
{
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->active()
            ->withCount('articles')
            // `position` d'abord : la rédaction choisit l'ordre. À position
            // égale (rubriques jamais classées), l'ordre alphabétique reste.
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $this->attachFallbackImages($categories);

        return $this->success(CategoryResource::collection($categories), 'Public categories retrieved successfully.');
    }

    /**
     * Pour une rubrique sans image : l'image à la une de son dernier article
     * publié. Une requête pour toutes les rubriques, puis association en
     * mémoire : jamais de requête dans une boucle.
     *
     * @param  \Illuminate\Support\Collection<int, Category>  $categories
     */
    protected function attachFallbackImages($categories): void
    {
        // La clé est toujours présente (nulle quand la rubrique a sa propre image).
        $categories->each(fn (Category $category) => $category->setAttribute('fallback_image_path', null));

        $needing = $categories->filter(fn (Category $category) => blank($category->image));

        if ($needing->isEmpty()) {
            return;
        }

        $images = Article::query()
            ->published()
            ->whereIn('category_id', $needing->pluck('id'))
            ->whereNotNull('featured_image')
            ->where('featured_image', '<>', '')
            ->whereRaw(
                'articles.id = (select a2.id from articles a2
                    where a2.category_id = articles.category_id
                      and a2.status = ?
                      and a2.published_at is not null
                      and a2.published_at <= ?
                      and a2.featured_image is not null
                      and a2.featured_image <> ?
                    order by a2.published_at desc, a2.id desc
                    limit 1)',
                ['published', now(), ''],
            )
            ->pluck('featured_image', 'category_id');

        foreach ($needing as $category) {
            $category->setAttribute('fallback_image_path', $images[$category->id] ?? null);
        }
    }
}
