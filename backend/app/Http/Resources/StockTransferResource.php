<?php

namespace App\Http\Resources;

use App\Models\StockTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockTransfer */
class StockTransferResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'number' => $this->number, 'from_store_id' => $this->from_store_id, 'to_store_id' => $this->to_store_id,
            'status' => $this->status->value, 'created_by' => $this->created_by, 'submitted_by' => $this->submitted_by,
            'approved_by' => $this->approved_by, 'rejected_by' => $this->rejected_by, 'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toISOString(), 'submitted_at' => $this->submitted_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(), 'posted_at' => $this->posted_at?->toISOString(), 'rejected_at' => $this->rejected_at?->toISOString(),
            'lines' => $this->lines->map(fn ($line) => ['id' => $line->id, 'product_id' => $line->product_id, 'quantity' => $line->quantity])->all()];
    }

    /** @return array<string,mixed> */
    public function with(Request $request): array
    {
        return ['meta' => ['request_id' => $request->attributes->get('request_id')]];
    }
}
