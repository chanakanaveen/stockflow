<?php

namespace App\Http\Controllers;

use App\Http\Resources\StoreResource;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class StoreController extends Controller
{
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
