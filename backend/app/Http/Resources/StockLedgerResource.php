<?php

namespace App\Http\Resources;

use App\Models\StockLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockLedgerEntry */
class StockLedgerResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'store_id' => $this->store_id, 'product_id' => $this->product_id, 'movement_type' => $this->movement_type->value, 'source_type' => $this->source_type, 'source_id' => $this->source_id, 'source_line_id' => $this->source_line_id, 'quantity_delta' => $this->quantity_delta, 'balance_after' => $this->balance_after, 'posted_at' => $this->posted_at->toISOString(), 'actor_id' => $this->actor_id];
    }

    /** @return array<string,mixed> */
    public function with(Request $request): array
    {
        return ['meta' => ['request_id' => $request->attributes->get('request_id')]];
    }
}
