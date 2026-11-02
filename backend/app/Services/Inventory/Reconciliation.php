<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;

class Reconciliation
{
    /** @return array<string,list<object>> */
    public function inspect(): array
    {
        $keys = DB::table('stock_balances')->select('store_id', 'product_id')->union(DB::table('stock_ledger_entries')->select('store_id', 'product_id'));
        $totals = DB::table('stock_ledger_entries')->select('store_id', 'product_id')->selectRaw('SUM(quantity_delta) AS total')->groupBy('store_id', 'product_id');
        $balances = DB::query()->fromSub($keys, 'k')
            ->leftJoin('stock_balances as b', fn ($join) => $join->on('b.store_id', '=', 'k.store_id')->on('b.product_id', '=', 'k.product_id'))
            ->leftJoinSub($totals, 'l', fn ($join) => $join->on('l.store_id', '=', 'k.store_id')->on('l.product_id', '=', 'k.product_id'))
            ->select('k.store_id', 'k.product_id', 'b.id as balance_id')->selectRaw('COALESCE(b.quantity,0) AS balance, COALESCE(l.total,0) AS ledger_total')->get()
            ->filter(fn ($row) => $row->balance_id === null || bccomp((string) $row->balance, (string) $row->ledger_total, 4) !== 0)->values()->all();
        $documents = DB::select("SELECT d.id AS document_id, n.id AS line_id FROM stock_document_lines n JOIN stock_documents d ON d.id=n.document_id
            LEFT JOIN stock_ledger_entries l ON l.source_type='document' AND l.source_line_id=n.id
            WHERE d.status='posted' GROUP BY d.id,n.id,n.quantity,d.type,d.store_id,n.product_id
            HAVING COUNT(l.id) <> 1 OR COALESCE(SUM(l.quantity_delta),0) <> CASE WHEN d.type='receipt' THEN n.quantity ELSE -n.quantity END
            OR MAX(l.source_id<>d.id OR l.store_id<>d.store_id OR l.product_id<>n.product_id OR l.movement_type<>d.type)=1");
        $invalid = DB::select("SELECT l.id FROM stock_ledger_entries l LEFT JOIN stock_document_lines n ON n.id=l.source_line_id
            LEFT JOIN stock_documents d ON d.id=n.document_id
            WHERE l.source_type='document' AND (d.id IS NULL OR d.status<>'posted' OR l.source_id<>d.id)");

        return ['balance_mismatches' => $balances, 'document_mismatches' => $documents, 'invalid_sources' => $invalid];
    }
}
