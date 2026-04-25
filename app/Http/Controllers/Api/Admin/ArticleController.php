<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ContentStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreArticleRequest;
use App\Http\Requests\Admin\UpdateArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Models\User;
use App\Services\AdminAuditLogger;
use App\Services\ArticlePublicationNotifier;
use App\Support\MediaPath;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArticleController extends ApiController
{
    public function __construct(
        protected ArticlePublicationNotifier $articlePublicationNotifier,
        protected AdminAuditLogger $adminAuditLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Article::class);

        $articles = Article::query()
            ->with(['author', 'category', 'tags'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->where(function ($subQuery) use ($request): void {
                    $subQuery->where('title', 'like', '%'.$request->string('search').'%')
                        ->orWhere('excerpt', 'like', '%'.$request->string('search').'%');
                });
            })
            ->latest()
            ->paginate((int) $request->integer('per_page', config('api.pagination.per_page')));

        return $this->paginated(ArticleResource::collection($articles), 'Articles retrieved successfully.');
    }

    public function store(StoreArticleRequest $request): JsonResponse
    {
        $this->authorize('create', Article::class);

        $article = DB::transaction(function () use ($request) {
            $data = $this->preparePayload($request->validated(), $request->user(), $request->user()->id);
            $article = Article::query()->create($data);
            $article->tags()->sync($request->input('tag_ids', []));

            return $article;
        });

        $article = $article->fresh()->load(['author', 'category', 'tags']);
        $this->articlePublicationNotifier->notifyIfFreshlyPublished($article);
        $this->adminAuditLogger->log(
            'article.created',
            actor: $request->user(),
            request: $request,
            target: $article,
            metadata: [
                'status' => $article->status?->value,
                'author_id' => $article->author_id,
            ],
        );

        if ($article->status === ContentStatus::Published) {
            $this->adminAuditLogger->log(
                'article.published',
                actor: $request->user(),
                request: $request,
                target: $article,
                metadata: [
                    'published_at' => optional($article->published_at)?->toISOString(),
                ],
            );
        }

        return $this->success(
            ArticleResource::make($article),
            'Article created successfully.',
            201,
        );
    }

    public function show(Article $article): JsonResponse
    {
        $this->authorize('view', $article);

        return $this->success(
            ArticleResource::make($article->load(['author', 'category', 'tags'])),
            'Article retrieved successfully.',
        );
    }

    public function update(UpdateArticleRequest $request, Article $article): JsonResponse
    {
        $this->authorize('update', $article);

        $previousStatus = $article->status?->value;

        DB::transaction(function () use ($request, $article): void {
            $article->update($this->preparePayload($request->validated(), $request->user(), $article->author_id));

            if ($request->has('tag_ids')) {
                $article->tags()->sync($request->input('tag_ids', []));
            }
        });

        $freshArticle = $article->fresh()->load(['author', 'category', 'tags']);
        $this->articlePublicationNotifier->notifyIfFreshlyPublished($freshArticle, $previousStatus);
        $this->adminAuditLogger->log(
            'article.updated',
            actor: $request->user(),
            request: $request,
            target: $freshArticle,
            metadata: [
                'previous_status' => $previousStatus,
                'current_status' => $freshArticle->status?->value,
                'author_id' => $freshArticle->author_id,
            ],
        );

        if ($previousStatus !== $freshArticle->status?->value) {
            if ($freshArticle->status === ContentStatus::Published) {
                $this->adminAuditLogger->log(
                    'article.published',
                    actor: $request->user(),
                    request: $request,
                    target: $freshArticle,
                    metadata: [
                        'previous_status' => $previousStatus,
                        'published_at' => optional($freshArticle->published_at)?->toISOString(),
                    ],
                );
            }

            if ($previousStatus === ContentStatus::Published->value && $freshArticle->status !== ContentStatus::Published) {
                $this->adminAuditLogger->log(
                    'article.unpublished',
                    actor: $request->user(),
                    request: $request,
                    target: $freshArticle,
                    metadata: [
                        'current_status' => $freshArticle->status?->value,
                    ],
                );
            }
        }

        return $this->success(
            ArticleResource::make($freshArticle),
            'Article updated successfully.',
        );
    }

    public function destroy(Article $article): JsonResponse
    {
        $this->authorize('delete', $article);

        $this->adminAuditLogger->log(
            'article.deleted',
            actor: request()->user(),
            request: request(),
            target: $article,
            metadata: [
                'status' => $article->status?->value,
            ],
        );
        $article->delete();

        return $this->success(null, 'Article deleted successfully.');
    }

    protected function preparePayload(array $data, User $actor, int $defaultAuthorId): array
    {
        $publishedAt = isset($data['published_at']) ? Carbon::parse($data['published_at']) : null;
        $status = $data['status'] ?? ContentStatus::Draft->value;

        if ($status === ContentStatus::Published->value && $publishedAt === null) {
            $publishedAt = now();
        }

        if ($status === ContentStatus::Published->value && $publishedAt?->isFuture()) {
            $status = ContentStatus::Scheduled->value;
        }

        $data['status'] = $status;
        $data['published_at'] = $publishedAt;
        $data['author_id'] = $actor->canAssignContentAuthor()
            ? ($data['author_id'] ?? $defaultAuthorId)
            : $actor->id;
        $data['featured_image'] = MediaPath::normalize($data['featured_image'] ?? null);

        return $data;
    }
}
