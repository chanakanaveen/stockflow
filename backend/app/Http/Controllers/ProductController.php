<?php

namespace App\Http\Controllers;

use App\Exceptions\InventoryException;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Quantity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Product::class);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'search' => ['sometimes', 'string', 'max:100'], 'category_id' => ['sometimes', 'integer'], 'is_active' => ['sometimes', 'boolean']]);
        $query = Product::query()->orderBy('id');
        if ($request->filled('search')) {
            $search = '%'.$request->string('search').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('sku', 'like', $search));
        }
        if ($request->has('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return ProductResource::collection($query->paginate($request->integer('per_page', 25)))->additional(['meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    public function show(Product $product): ProductResource
    {
        Gate::authorize('view', $product);

        return new ProductResource($product);
    }

    public function store(ProductRequest $request, AuditLogger $audit): JsonResponse
    {
        /** @var User $actor */ $actor = $request->user();
        $product = DB::transaction(function () use ($request, $audit, $actor) {
            $data = $request->validated();
            $this->categoryActive((int) $data['category_id']);
            $data['reorder_level'] = Quantity::normalize($data['reorder_level'] ?? '0');
            $product = Product::create($data);
            $audit->record('product.created', $product, $actor);

            return $product->refresh();
        }, 3);

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function update(ProductRequest $request, Product $product, AuditLogger $audit): ProductResource
    {
        /** @var User $actor */ $actor = $request->user();

        return new ProductResource(DB::transaction(function () use ($request, $product, $audit, $actor) {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $data = $request->validated();
            if (isset($data['base_uom']) && $data['base_uom'] !== $locked->base_uom && DB::table('stock_ledger_entries')->where('product_id', $locked->id)->exists()) {
                throw new InventoryException('UOM_IMMUTABLE', 'The base unit cannot change after inventory has been posted.');
            }
            if (isset($data['category_id'])) {
                $this->categoryActive((int) $data['category_id']);
            }
            if (isset($data['reorder_level'])) {
                $data['reorder_level'] = Quantity::normalize($data['reorder_level']);
            }
            $locked->update($data);
            $audit->record('product.updated', $locked, $actor, ['is_active' => $locked->is_active]);

            return $locked;
        }, 3));
    }

    private function categoryActive(int $id): void
    {
        $category = Category::query()->sharedLock()->findOrFail($id);
        if (! $category->is_active) {
            throw new InventoryException('INACTIVE_CATEGORY', 'The category is inactive.', 422);
        }
    }
}
