<?php

namespace App\Services\Inventory;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Exceptions\InventoryException;
use App\Http\Resources\StockDocumentResource;
use App\Models\StockDocument;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PostingService
{
    public function __construct(private DocumentWriter $documents, private AuditLogger $audit) {}

    /** @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function post(User $actor, StockDocument $document, string $key, array $payload = []): array
    {
        Gate::forUser($actor)->authorize('post', $document);
        if (! preg_match('/\\A[\\x21-\\x7e][\\x20-\\x7e]{0,127}\\z/D', $key)) {
            throw new InventoryException('INVALID_IDEMPOTENCY_KEY', 'A printable Idempotency-Key of at most 128 characters is required.', 422);
        }
        $route = 'POST:inventory:'.$document->type->value.':'.$document->id.':post';
        $hash = hash('sha256', $route.'|'.json_encode($this->canonical($payload), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

        return DB::transaction(function () use ($actor, $document, $key, $route, $hash) {
            DB::statement('INSERT INTO idempotency_records (actor_id, route_key, idempotency_key, request_hash, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE id = id',
                [$actor->id, $route, $key, $hash, 'completed', now(), now()]);
            $record = DB::table('idempotency_records')->where('actor_id', $actor->id)->where('route_key', $route)->where('idempotency_key', $key)->lockForUpdate()->first();
            if (! $record || ! hash_equals($record->request_hash, $hash)) {
                throw new InventoryException('IDEMPOTENCY_CONFLICT', 'This idempotency key was already used with a different request.');
            }
            $locked = StockDocument::query()->lockForUpdate()->findOrFail($document->id);
            Gate::forUser($actor)->authorize('post', $locked);
            if ($record->response_code !== null) {
                return json_decode($record->response_body, true, 512, JSON_THROW_ON_ERROR);
            }
            if ($locked->status === DocumentStatus::Posted) {
                throw new InventoryException('ALREADY_PROCESSED', 'This document has already been posted.');
            }
            if ($locked->status !== DocumentStatus::Draft) {
                throw new InventoryException('INVALID_STATE', 'Only draft documents can be posted.');
            }
            $locked->load('lines');
            $lines = $locked->lines->map(fn ($line) => ['product_id' => $line->product_id, 'quantity' => $line->quantity])->all();
            $this->documents->validateStock($actor, $locked->store_id, $lines);
            $balances = [];
            foreach ($locked->lines as $line) {
                DB::statement('INSERT INTO stock_balances (store_id, product_id, quantity, created_at, updated_at) VALUES (?, ?, 0, ?, ?) ON DUPLICATE KEY UPDATE id = id', [$locked->store_id, $line->product_id, now(), now()]);
                $balance = DB::table('stock_balances')->where('store_id', $locked->store_id)->where('product_id', $line->product_id)->lockForUpdate()->first();
                if (! $balance) {
                    throw new InventoryException('INVENTORY_BUSY', 'The inventory balance could not be locked.', 503);
                }
                $next = $locked->type === DocumentType::Receipt ? bcadd($balance->quantity, $line->quantity, 4) : bcsub($balance->quantity, $line->quantity, 4);
                if (bccomp($next, '0', 4) < 0) {
                    throw new InventoryException('INSUFFICIENT_STOCK', 'Insufficient stock for one or more items.', 422, [['product_id' => $line->product_id, 'store_id' => $locked->store_id, 'available' => $balance->quantity, 'required' => $line->quantity]]);
                }
                if (bccomp($next, Quantity::MAX, 4) > 0) {
                    throw new InventoryException('QUANTITY_OVERFLOW', 'The resulting balance exceeds the supported quantity range.', 422);
                }
                $balances[$line->product_id] = ['id' => $balance->id, 'next' => $next];
            }
            $postedAt = now('UTC');
            foreach ($locked->lines as $line) {
                $balance = $balances[$line->product_id];
                DB::table('stock_balances')->where('id', $balance['id'])->update(['quantity' => $balance['next'], 'updated_at' => $postedAt]);
                DB::table('stock_ledger_entries')->insert([
                    'store_id' => $locked->store_id, 'product_id' => $line->product_id, 'movement_type' => $locked->type->value,
                    'source_type' => 'document', 'source_id' => $locked->id, 'source_line_id' => $line->id,
                    'quantity_delta' => $locked->type === DocumentType::Receipt ? $line->quantity : bcsub('0', $line->quantity, 4),
                    'balance_after' => $balance['next'], 'posted_at' => $postedAt, 'actor_id' => $actor->id, 'created_at' => $postedAt,
                ]);
            }
            $locked->update(['status' => DocumentStatus::Posted, 'posted_by' => $actor->id, 'posted_at' => $postedAt]);
            $this->audit->record($locked->type->value.'.posted', $locked, $actor, ['store_id' => $locked->store_id, 'line_count' => $locked->lines->count()]);
            $result = ['data' => (new StockDocumentResource($locked->refresh()->load('lines')))->resolve()];
            DB::table('idempotency_records')->where('id', $record->id)->update(['response_code' => 200, 'response_body' => json_encode($result, JSON_THROW_ON_ERROR), 'resource_type' => 'stock_document', 'resource_id' => $locked->id, 'updated_at' => $postedAt]);

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
