<?php

namespace App\Services\Inventory;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Exceptions\InventoryException;
use App\Models\Product;
use App\Models\StockDocument;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DocumentWriter
{
    public function __construct(private NumberAllocator $numbers, private AuditLogger $audit) {}

    /** @param array<string,mixed> $data */
    public function create(User $actor, DocumentType $type, array $data): StockDocument
    {
        Gate::forUser($actor)->authorize('create', [StockDocument::class, $type]);

        return DB::transaction(function () use ($actor, $type, $data) {
            $this->validateStock($actor, (int) $data['store_id'], $data['lines']);
            $document = StockDocument::create(['number' => $this->numbers->next($type), 'type' => $type, 'status' => DocumentStatus::Draft,
                'store_id' => $data['store_id'], 'reference' => $data['reference'] ?? null, 'remarks' => $data['remarks'] ?? null, 'created_by' => $actor->id]);
            $this->replaceLines($document, $data['lines']);
            $this->audit->record($type->value.'.draft_created', $document, $actor, ['store_id' => $document->store_id]);

            return $document->refresh()->load('lines');
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function update(User $actor, StockDocument $document, array $data): StockDocument
    {
        return DB::transaction(function () use ($actor, $document, $data) {
            $locked = StockDocument::query()->lockForUpdate()->findOrFail($document->id);
            Gate::forUser($actor)->authorize('update', $locked);
            $this->requireDraft($locked);
            $lines = $data['lines'] ?? $locked->lines->map(fn ($line) => ['product_id' => $line->product_id, 'quantity' => $line->quantity])->all();
            $this->validateStock($actor, (int) ($data['store_id'] ?? $locked->store_id), $lines);
            $locked->fill(array_intersect_key($data, array_flip(['store_id', 'reference', 'remarks'])))->save();
            if (array_key_exists('lines', $data)) {
                $this->replaceLines($locked, $lines);
            }
            $this->audit->record($locked->type->value.'.draft_updated', $locked, $actor, ['store_id' => $locked->store_id]);

            return $locked->refresh()->load('lines');
        }, 3);
    }

    public function cancel(User $actor, StockDocument $document): StockDocument
    {
        return DB::transaction(function () use ($actor, $document) {
            $locked = StockDocument::query()->lockForUpdate()->findOrFail($document->id);
            Gate::forUser($actor)->authorize('update', $locked);
            $this->requireDraft($locked);
            $locked->update(['status' => DocumentStatus::Cancelled]);
            $this->audit->record($locked->type->value.'.cancelled', $locked, $actor, ['store_id' => $locked->store_id]);

            return $locked->load('lines');
        }, 3);
    }

    private function requireDraft(StockDocument $document): void
    {
        if ($document->status !== DocumentStatus::Draft) {
            throw new InventoryException('INVALID_STATE', 'Only draft documents may be changed.');
        }
    }

    /** Caller must be inside a transaction. Lock store, then products by ID.
     * @param  list<array{product_id:int,quantity:string}>  $lines
     */
    public function validateStock(User $actor, int $storeId, array $lines): void
    {
        $store = Store::query()->sharedLock()->findOrFail($storeId);
        abort_unless($actor->canAccessStore($store), 404);
        if (! $store->is_active) {
            throw new InventoryException('INACTIVE_STORE', 'The store is inactive.', 422);
        }
        if (count($lines) < 1 || count($lines) > 100) {
            throw ValidationException::withMessages(['lines' => ['Provide between 1 and 100 lines.']]);
        }
        $ids = array_map(fn ($line) => (int) $line['product_id'], $lines);
        if (count(array_unique($ids)) !== count($ids)) {
            throw ValidationException::withMessages(['lines' => ['Products must be unique within a document.']]);
        }
        sort($ids, SORT_NUMERIC);
        foreach ($ids as $id) {
            $product = Product::query()->sharedLock()->findOrFail($id);
            if (! $product->is_active) {
                throw new InventoryException('INACTIVE_PRODUCT', 'One or more products are inactive.', 422);
            }
        }
        foreach ($lines as $line) {
            if (! Quantity::valid($line['quantity'])) {
                throw ValidationException::withMessages(['lines' => ['Line quantities must be positive decimal strings.']]);
            }
        }
    }

    /** @param list<array{product_id:int,quantity:string}> $lines */
    private function replaceLines(StockDocument $document, array $lines): void
    {
        $document->lines()->delete();
        usort($lines, fn ($a, $b) => $a['product_id'] <=> $b['product_id']);
        foreach ($lines as $line) {
            $document->lines()->create(['product_id' => $line['product_id'], 'quantity' => Quantity::normalize($line['quantity'])]);
        }
    }
}
