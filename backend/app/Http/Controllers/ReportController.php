<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\User;
use App\Services\ReportQueries;
use App\Services\SafeCsv;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function summary(Request $request, ReportQueries $queries): JsonResponse
    {
        $this->validateFilters($request);
        /** @var User $actor */ $actor = $request->user();

        return response()->json(['data' => [
            'active_products' => Product::query()->where('is_active', true)->count(),
            'active_stores' => Store::query()->accessibleTo($actor)->where('is_active', true)->count(),
            'pending_transfers' => StockTransfer::query()->accessibleTo($actor)->where('status', 'submitted')->count(),
            'low_stock_positions' => $queries->stock($actor, $request, true)->count(),
        ], 'meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    public function stock(Request $request, ReportQueries $queries): JsonResponse
    {
        $this->validateFilters($request);
        /** @var User $actor */ $actor = $request->user();

        return $this->page($request, $queries->stock($actor, $request, $request->routeIs('reports.low-stock'))->orderBy('s.id')->orderBy('p.id')->paginate($request->integer('per_page', 25)));
    }

    public function movements(Request $request, ReportQueries $queries): JsonResponse
    {
        $this->validateFilters($request);
        /** @var User $actor */ $actor = $request->user();

        return $this->page($request, $queries->movements($actor, $request)->orderBy('l.store_id')->orderBy('l.product_id')->paginate($request->integer('per_page', 25)));
    }

    public function audit(Request $request, ReportQueries $queries): JsonResponse
    {
        Gate::authorize('audit.view');
        $this->validateFilters($request);
        $request->validate(['action' => ['sometimes', 'string', 'max:64']]);
        /** @var User $actor */ $actor = $request->user();
        $query = $queries->audit($actor);
        if ($request->filled('action')) {
            $query->where('a.action', $request->string('action')->toString());
        }
        if ($request->filled('date_from')) {
            $query->where('a.created_at', '>=', $request->date('date_from', 'Y-m-d', 'UTC')->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->where('a.created_at', '<', $request->date('date_to', 'Y-m-d', 'UTC')->addDay()->startOfDay());
        }
        $page = $query->orderByDesc('a.id')->paginate($request->integer('per_page', 25));
        $page->through(function ($row) {
            $row->metadata = (object) json_decode($row->metadata, true, 512, JSON_THROW_ON_ERROR);
            $row->created_at = CarbonImmutable::parse($row->created_at, 'UTC')->toISOString();

            return $row;
        });

        return $this->page($request, $page);
    }

    public function export(Request $request, ReportQueries $queries): StreamedResponse
    {
        Gate::authorize('reports.export');
        $this->validateFilters($request, true);
        /** @var User $actor */ $actor = $request->user();
        $query = $queries->ledger($actor, $request);
        $ids = (clone $query)->orderBy('l.id')->limit(10001)->pluck('l.id');
        if ($ids->count() > 10000) {
            throw ValidationException::withMessages(['date_from' => 'Export is limited to 10,000 movements. Narrow the dates or filters.']);
        }
        $query->whereIn('l.id', $ids);

        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Cannot open CSV output.');
            }
            fputcsv($stream, ['Movement ID', 'Posted at (UTC)', 'Store', 'SKU', 'Product', 'Unit', 'Movement', 'Quantity delta', 'Balance after', 'Source type', 'Source ID'], ',', '"', '', "\r\n");
            $query->select('l.*', 's.name as store_name', 'p.sku', 'p.name as product_name', 'p.base_uom')->chunkById(500, function ($rows) use ($stream): void {
                foreach ($rows as $row) {
                    fputcsv($stream, [$row->id, $row->posted_at, SafeCsv::text($row->store_name), SafeCsv::text($row->sku), SafeCsv::text($row->product_name), SafeCsv::text($row->base_uom), $row->movement_type, $row->quantity_delta, $row->balance_after, $row->source_type, $row->source_id], ',', '"', '', "\r\n");
                }
            }, 'l.id', 'id');
            fclose($stream);
        }, 'stockflow-movements.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function validateFilters(Request $request, bool $export = false): void
    {
        Gate::authorize('reports.view');
        $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'store_id' => ['sometimes', 'integer', 'min:1'], 'product_id' => ['sometimes', 'integer', 'min:1'],
            'date_from' => [$export ? 'required' : 'sometimes', 'date_format:Y-m-d'],
            'date_to' => [$export ? 'required' : 'sometimes', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])]]);
        if ($export && $request->date('date_from')->diffInDays($request->date('date_to')) + 1 > 366) {
            throw ValidationException::withMessages(['date_to' => 'Choose a range of at most 366 days.']);
        }
    }

    /** @param LengthAwarePaginator<int, mixed> $page */
    private function page(Request $request, LengthAwarePaginator $page): JsonResponse
    {
        return response()->json(['data' => $page->items(), 'meta' => ['request_id' => $request->attributes->get('request_id'), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }
}
