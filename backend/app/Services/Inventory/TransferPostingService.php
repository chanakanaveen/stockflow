<?php

namespace App\Services\Inventory;

use App\Enums\TransferStatus;
use App\Exceptions\InventoryException;
use App\Http\Resources\StockTransferResource;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TransferPostingService
{
    public function __construct(private TransferWriter $writer, private AuditLogger $audit) {}

    /** @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function approve(User $actor, StockTransfer $transfer, string $key, array $payload = []): array
    {
        Gate::forUser($actor)->authorize('approve', $transfer);
        if (! preg_match('/\\A[\\x21-\\x7e][\\x20-\\x7e]{0,127}\\z/D', $key)) {
            throw new InventoryException('INVALID_IDEMPOTENCY_KEY', 'A printable Idempotency-Key of at most 128 characters is required.', 422);
        }
        $route = 'POST:transfers:'.$transfer->id.':approve';
        $hash = hash('sha256', $route.'|'.json_encode($this->canonical($payload), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

        return DB::transaction(function () use ($actor, $transfer, $key, $route, $hash) {
            DB::statement('INSERT INTO idempotency_records (actor_id, route_key, idempotency_key, request_hash, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE id = id',
                [$actor->id, $route, $key, $hash, 'completed', now(), now()]);
            $record = DB::table('idempotency_records')->where('actor_id', $actor->id)->where('route_key', $route)->where('idempotency_key', $key)->lockForUpdate()->first();
            if (! $record || ! hash_equals($record->request_hash, $hash)) {
                throw new InventoryException('IDEMPOTENCY_CONFLICT', 'This idempotency key was already used with a different request.');
            }
            $locked = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            Gate::forUser($actor)->authorize('approve', $locked);
            if ($record->response_code !== null) {
                return json_decode($record->response_body, true, 512, JSON_THROW_ON_ERROR);
            }
            if ($locked->status === TransferStatus::ApprovedPosted) {
                throw new InventoryException('ALREADY_PROCESSED', 'This transfer has already been posted.');
            }
            $this->writer->requireStatus($locked, TransferStatus::Submitted);
            $locked->load('lines');
            $this->writer->validate($actor, $locked->from_store_id, $locked->to_store_id, $this->writer->lines($locked));
            $stores = [$locked->from_store_id, $locked->to_store_id];
            sort($stores, SORT_NUMERIC);
            $balances = [];
            // All pairs, including absent rows, are locked in (store_id, product_id) order.
            foreach ($stores as $storeId) {
                foreach ($locked->lines as $line) {
                    DB::statement('INSERT INTO stock_balances (store_id, product_id, quantity, created_at, updated_at) VALUES (?, ?, 0, ?, ?) ON DUPLICATE KEY UPDATE id = id', [$storeId, $line->product_id, now(), now()]);
                    $balance = DB::table('stock_balances')->where('store_id', $storeId)->where('product_id', $line->product_id)->lockForUpdate()->first();
                    if (! $balance) {
                        throw new InventoryException('INVENTORY_BUSY', 'The inventory balance could not be locked.', 503);
                    }
                    $out = $storeId === $locked->from_store_id;
                    $next = $out ? bcsub($balance->quantity, $line->quantity, 4) : bcadd($balance->quantity, $line->quantity, 4);
                    if (bccomp($next, '0', 4) < 0) {
                        throw new InventoryException('INSUFFICIENT_STOCK', 'Insufficient source stock.', 422, [['store_id' => $storeId, 'product_id' => $line->product_id, 'available' => $balance->quantity, 'required' => $line->quantity]]);
                    }
                    if (bccomp($next, Quantity::MAX, 4) > 0) {
                        throw new InventoryException('QUANTITY_OVERFLOW', 'The destination balance exceeds the supported quantity range.', 422);
                    }
                    $balances[] = ['id' => $balance->id, 'store_id' => $storeId, 'product_id' => $line->product_id, 'line_id' => $line->id, 'next' => $next, 'delta' => $out ? bcsub('0', $line->quantity, 4) : $line->quantity, 'movement' => $out ? 'transfer_out' : 'transfer_in'];
                }
            }
            $postedAt = now('UTC');
            foreach ($balances as $balance) {
                DB::table('stock_balances')->where('id', $balance['id'])->update(['quantity' => $balance['next'], 'updated_at' => $postedAt]);
                DB::table('stock_ledger_entries')->insert(['store_id' => $balance['store_id'], 'product_id' => $balance['product_id'], 'movement_type' => $balance['movement'],
                    'source_type' => 'transfer', 'source_id' => $locked->id, 'source_line_id' => $balance['line_id'], 'quantity_delta' => $balance['delta'], 'balance_after' => $balance['next'],
                    'posted_at' => $postedAt, 'actor_id' => $actor->id, 'created_at' => $postedAt]);
            }
            $locked->update(['status' => TransferStatus::ApprovedPosted, 'approved_by' => $actor->id, 'approved_at' => $postedAt, 'posted_at' => $postedAt]);
            $this->audit->record('transfer.approved_posted', $locked, $actor, ['from_store_id' => $locked->from_store_id, 'to_store_id' => $locked->to_store_id, 'line_count' => $locked->lines->count()]);
            $result = ['data' => (new StockTransferResource($locked->refresh()->load('lines')))->resolve()];
            DB::table('idempotency_records')->where('id', $record->id)->update(['response_code' => 200, 'response_body' => json_encode($result, JSON_THROW_ON_ERROR), 'resource_type' => 'stock_transfer', 'resource_id' => $locked->id, 'updated_at' => $postedAt]);

            return $result;
        }, 3);
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => $this->canonical($item), $value);
    }
}
