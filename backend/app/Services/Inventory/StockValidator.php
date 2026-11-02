<?php

namespace App\Services\Inventory;

use App\Exceptions\InventoryException;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\Quantity;
use Illuminate\Validation\ValidationException;

class StockValidator
{
    /** Caller holds a transaction. All posting paths lock stores, then products, in ID order.
     * @param  list<int>  $storeIds
     * @param  list<array{product_id:int,quantity:string}>  $lines
     */
    public function validate(User $actor, array $storeIds, array $lines): void
    {
        sort($storeIds, SORT_NUMERIC);
        foreach (array_unique($storeIds) as $id) {
            $store = Store::query()->sharedLock()->findOrFail($id);
            abort_unless($actor->canAccessStore($store), 404);
            if (! $store->is_active) {
                throw new InventoryException('INACTIVE_STORE', 'The store is inactive.', 422);
            }
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
}
