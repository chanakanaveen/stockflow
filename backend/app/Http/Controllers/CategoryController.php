<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Category::class);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'search' => ['sometimes', 'string', 'max:100'], 'is_active' => ['sometimes', 'boolean']]);
        $query = Category::query()->orderBy('id');
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search').'%');
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return CategoryResource::collection($query->paginate($request->integer('per_page', 25)))->additional(['meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    public function show(Category $category): CategoryResource
    {
        Gate::authorize('view', $category);

        return new CategoryResource($category);
    }

    public function store(CategoryRequest $request, AuditLogger $audit): JsonResponse
    {
        /** @var User $actor */ $actor = $request->user();
        $category = DB::transaction(function () use ($request, $audit, $actor) {
            $category = Category::create($request->validated());
            $audit->record('category.created', $category, $actor);

            return $category->refresh();
        }, 3);

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function update(CategoryRequest $request, Category $category, AuditLogger $audit): CategoryResource
    {
        /** @var User $actor */ $actor = $request->user();

        return new CategoryResource(DB::transaction(function () use ($request, $category, $audit, $actor) {
            $locked = Category::query()->lockForUpdate()->findOrFail($category->id);
            $locked->update($request->validated());
            $audit->record('category.updated', $locked, $actor, ['is_active' => $locked->is_active]);

            return $locked;
        }, 3));
    }
}
