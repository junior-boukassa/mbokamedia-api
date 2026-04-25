<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContentStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Article\StoreArticleRequest;
use App\Http\Requests\Api\V1\Article\UpdateArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleController extends ApiController
{
    public function index(): JsonResponse
    {
        $articles = Article::query()
            ->with(['author', 'category', 'tags'])
            ->latest()
            ->paginate((int) config('api.pagination.public_per_page', 12));

        return $this->paginated(ArticleResource::collection($articles), 'Articles retrieved successfully.');
    }

    public function show(Article $article): JsonResponse
    {
        return $this->success(
            ArticleResource::make($article->loadMissing(['author', 'category', 'tags'])),
            'Article retrieved successfully.',
        );
    }

    public function store(StoreArticleRequest $request): JsonResponse
    {
        $article = new Article();
        $this->fillArticle($article, $request, $request->user()->id);
        $article->save();

        return $this->success(
            ArticleResource::make($article->load(['author', 'category', 'tags'])),
            'Article created successfully.',
            201,
        );
    }

    public function update(UpdateArticleRequest $request, Article $article): JsonResponse
    {
        if ($article->author_id !== $request->user()->id) {
            return $this->error('You can only update your own articles.', 403);
        }

        $this->fillArticle($article, $request, $article->author_id, partial: true);
        $article->save();

        return $this->success(
            ArticleResource::make($article->load(['author', 'category', 'tags'])),
            'Article updated successfully.',
        );
    }

    public function destroy(Request $request, Article $article): JsonResponse
    {
        if ($article->author_id !== $request->user()->id) {
            return $this->error('You can only delete your own articles.', 403);
        }

        $article->delete();

        return $this->success(null, 'Article deleted successfully.');
    }

    protected function fillArticle(Article $article, Request $request, int $authorId, bool $partial = false): void
    {
        $validated = $request->validated();
        $status = $validated['status'] ?? ($partial ? $article->status?->value : ContentStatus::Draft->value);

        $article->fill([
            'title' => $validated['title'] ?? $article->title,
            'excerpt' => $validated['excerpt'] ?? ($partial ? $article->excerpt : null),
            'content' => $validated['content'] ?? $article->content,
            'featured_image' => $validated['featured_image'] ?? ($partial ? $article->featured_image : null),
            'status' => $status,
            'category_id' => $this->resolveCategoryId($validated['category_id'] ?? $article->category_id),
            'author_id' => $authorId,
            'published_at' => $this->resolvePublishedAt(
                $status,
                $validated['published_at'] ?? ($partial ? $article->published_at?->toISOString() : null),
            ),
            'is_featured' => $validated['is_featured'] ?? ($partial ? $article->is_featured : false),
            'seo_title' => $validated['seo_title'] ?? ($partial ? $article->seo_title : null),
            'seo_description' => $validated['seo_description'] ?? ($partial ? $article->seo_description : null),
        ]);
    }

    protected function resolveCategoryId(?int $categoryId): int
    {
        if ($categoryId !== null) {
            return $categoryId;
        }

        return Category::query()->firstOrCreate(
            ['slug' => 'general'],
            [
                'name' => 'General',
                'description' => 'Default category for API articles.',
                'is_active' => true,
            ],
        )->id;
    }

    protected function resolvePublishedAt(string $status, mixed $publishedAt): mixed
    {
        if ($status === ContentStatus::Published->value) {
            return $publishedAt ?: now();
        }

        return $publishedAt;
    }
}
