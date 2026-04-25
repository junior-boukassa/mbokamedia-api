<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $articles = Article::query()
            ->published()
            ->visible()
            ->when($request->filled('category'), fn ($query) => $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('slug', $request->string('category'))))
            ->when($request->filled('tag'), fn ($query) => $query->whereHas('tags', fn ($tagQuery) => $tagQuery->where('slug', $request->string('tag'))))
            ->when($request->boolean('featured'), fn ($query) => $query->where('is_featured', true))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->where(function ($subQuery) use ($request): void {
                    $subQuery->where('title', 'like', '%'.$request->string('search').'%')
                        ->orWhere('excerpt', 'like', '%'.$request->string('search').'%');
                });
            })
            ->latest('published_at')
            ->paginate((int) $request->integer('per_page', config('api.pagination.public_per_page')));

        return $this->paginated(ArticleResource::collection($articles), 'Public articles retrieved successfully.');
    }

    public function show(Article $article): JsonResponse
    {
        abort_unless($article->status?->value === 'published' && $article->published_at?->isPast(), 404);

        $article->load(['author', 'category', 'tags']);
        $article->increment('views_count');

        return $this->success(ArticleResource::make($article->fresh()->load(['author', 'category', 'tags'])), 'Article retrieved successfully.');
    }
}
