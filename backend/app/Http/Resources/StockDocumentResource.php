<?php

namespace App\Http\Resources;

use App\Models\StockDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockDocument */
class StockDocumentResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'number' => $this->number, 'type' => $this->type->value, 'store_id' => $this->store_id,
            'status' => $this->status->value, 'reference' => $this->reference, 'remarks' => $this->remarks,
            'created_by' => $this->created_by, 'posted_by' => $this->posted_by, 'posted_at' => $this->posted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'lines' => $this->lines->map(fn ($line) => ['id' => $line->id, 'product_id' => $line->product_id, 'quantity' => $line->quantity])->all()];
    }

    /** @return array<string,mixed> */
    public function with(Request $request): array
    {
        return ['meta' => ['request_id' => $request->attributes->get('request_id')]];
    }
}
