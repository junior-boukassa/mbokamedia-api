<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends ApiController
{
    public function __construct(
        protected AdminAuditLogger $adminAuditLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::query()
            ->withCount('articles')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->latest()
            ->paginate((int) $request->integer('per_page', config('api.pagination.per_page')));

        return $this->paginated(CategoryResource::collection($categories), 'Categories retrieved successfully.');
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $category = Category::query()->create($request->validated());
        $this->adminAuditLogger->log('category.created', actor: $request->user(), request: $request, target: $category);

        return $this->success(CategoryResource::make($category), 'Category created successfully.', 201);
    }

    public function show(Category $category): JsonResponse
    {
        $this->authorize('view', $category);

        $category->loadCount('articles');

        return $this->success(CategoryResource::make($category), 'Category retrieved successfully.');
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category->update($request->validated());
        $this->adminAuditLogger->log('category.updated', actor: $request->user(), request: $request, target: $category->fresh());

        return $this->success(CategoryResource::make($category->fresh()->loadCount('articles')), 'Category updated successfully.');
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $this->adminAuditLogger->log('category.deleted', actor: request()->user(), request: request(), target: $category);
        $category->delete();

        return $this->success(null, 'Category deleted successfully.');
    }
}
