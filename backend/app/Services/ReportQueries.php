<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportQueries
{
    public function stock(User $actor, Request $request, bool $low = false): Builder
    {
        $query = DB::table('stores as s')->crossJoin('products as p')
            ->leftJoin('stock_balances as b', fn ($join) => $join->on('b.store_id', '=', 's.id')->on('b.product_id', '=', 'p.id'))
            ->whereIn('s.id', Store::query()->accessibleTo($actor)->select('id'))
            ->where('s.is_active', true)->where('p.is_active', true)
            ->selectRaw('s.id as store_id, s.name as store_name, p.id as product_id, p.sku, p.name as product_name, p.base_uom, p.reorder_level, CAST(COALESCE(b.quantity, 0) AS DECIMAL(18,4)) as quantity');
        foreach (['store_id' => 's.id', 'product_id' => 'p.id'] as $filter => $column) {
            if ($request->filled($filter)) {
                $query->where($column, $request->integer($filter));
            }
        }
        if ($low) {
            $query->whereRaw('COALESCE(b.quantity, 0) < p.reorder_level');
        }

        return $query;
    }

    public function ledger(User $actor, Request $request): Builder
    {
        $query = DB::table('stock_ledger_entries as l')->join('stores as s', 's.id', '=', 'l.store_id')->join('products as p', 'p.id', '=', 'l.product_id')
            ->whereIn('l.store_id', Store::query()->accessibleTo($actor)->select('id'));
        foreach (['store_id', 'product_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where('l.'.$filter, $request->integer($filter));
            }
        }
        if ($request->filled('date_from')) {
            $query->where('l.posted_at', '>=', $request->date('date_from', 'Y-m-d', 'UTC')->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->where('l.posted_at', '<', $request->date('date_to', 'Y-m-d', 'UTC')->addDay()->startOfDay());
        }

        return $query;
    }

    public function movements(User $actor, Request $request): Builder
    {
        return $this->ledger($actor, $request)
            ->selectRaw('l.store_id, s.name as store_name, l.product_id, p.sku, p.name as product_name, p.base_uom, SUM(CASE WHEN l.quantity_delta > 0 THEN l.quantity_delta ELSE 0 END) as incoming, SUM(CASE WHEN l.quantity_delta < 0 THEN -l.quantity_delta ELSE 0 END) as outgoing, SUM(l.quantity_delta) as net, COUNT(*) as movement_count')
            ->groupBy('l.store_id', 's.name', 'l.product_id', 'p.sku', 'p.name', 'p.base_uom');
    }

    public function audit(User $actor): Builder
    {
        $query = DB::table('audit_logs as a');
        if (! $actor->hasRole('System Admin')) {
            $stores = Store::query()->accessibleTo($actor)->select('id');
            $query->where(function (Builder $visible) use ($stores) {
                $visible->where(fn (Builder $q) => $q->where('a.subject_type', 'stores')->whereIn('a.subject_id', clone $stores))
                    ->orWhere(fn (Builder $q) => $q->where('a.subject_type', 'stock_documents')->whereIn('a.subject_id', DB::table('stock_documents')->whereIn('store_id', clone $stores)->select('id')))
                    ->orWhere(fn (Builder $q) => $q->where('a.subject_type', 'stock_transfers')->whereIn('a.subject_id', DB::table('stock_transfers')->whereIn('from_store_id', clone $stores)->whereIn('to_store_id', clone $stores)->select('id')));
            });
        }

        return $query;
    }
}
