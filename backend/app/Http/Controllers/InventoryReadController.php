<?php

namespace App\Http\Controllers;

use App\Http\Resources\StockBalanceResource;
use App\Http\Resources\StockLedgerResource;
use App\Models\StockBalance;
use App\Models\StockLedgerEntry;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class InventoryReadController extends Controller
{
    public function balances(Request $request): AnonymousResourceCollection
    {
        $this->validateFilters($request);
        /** @var User $actor */ $actor = $request->user();
        $query = StockBalance::query()->whereIn('store_id', Store::query()->accessibleTo($actor)->select('id'))->orderBy('store_id')->orderBy('product_id');
        foreach (['store_id', 'product_id'] as $filter) {
            if ($request->has($filter)) {
                $query->where($filter, $request->integer($filter));
            }
        }

        return StockBalanceResource::collection($query->paginate($request->integer('per_page', 25)))->additional(['meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    public function ledger(Request $request): AnonymousResourceCollection
    {
        $this->validateFilters($request);
        /** @var User $actor */ $actor = $request->user();
        $query = StockLedgerEntry::query()->whereIn('store_id', Store::query()->accessibleTo($actor)->select('id'))->orderByDesc('posted_at')->orderByDesc('id');
        foreach (['store_id', 'product_id'] as $filter) {
            if ($request->has($filter)) {
                $query->where($filter, $request->integer($filter));
            }
        }
        if ($request->filled('date_from')) {
            $query->where('posted_at', '>=', $request->date('date_from', 'Y-m-d', 'UTC')->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->where('posted_at', '<', $request->date('date_to', 'Y-m-d', 'UTC')->addDay()->startOfDay());
        }

        return StockLedgerResource::collection($query->paginate($request->integer('per_page', 25)))->additional(['meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    private function validateFilters(Request $request): void
    {
        Gate::authorize('inventory.view');
        $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'store_id' => ['sometimes', 'integer'], 'product_id' => ['sometimes', 'integer'], 'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])]]);
    }
}
