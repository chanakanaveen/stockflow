<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class StoreController extends Controller
{
    public function store(StoreRequest $request, AuditLogger $audit): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $store = DB::transaction(function () use ($request, $audit, $actor) {
            $store = Store::create($request->validated());
            $audit->record('store.created', $store, $actor);

            return $store->refresh();
        }, 3);

        return (new StoreResource($store))->response()->setStatusCode(201);
    }

    public function update(StoreRequest $request, Store $store, AuditLogger $audit): StoreResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new StoreResource(DB::transaction(function () use ($request, $store, $audit, $actor) {
            $locked = Store::query()->lockForUpdate()->findOrFail($store->id);
            $locked->update($request->validated());
            $audit->record('store.updated', $locked, $actor, ['is_active' => $locked->is_active]);

            return $locked;
        }, 3));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Store::class);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        /** @var User $user */
        $user = $request->user();

        return StoreResource::collection(Store::query()->accessibleTo($user)->orderBy('id')->paginate($request->integer('per_page', 25)))
            ->additional(['meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    public function show(Store $store): StoreResource
    {
        Gate::authorize('view', $store);

        return new StoreResource($store);
    }
}
