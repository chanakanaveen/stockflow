<?php

namespace App\Http\Resources;

use App\Models\StockBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StockBalance */
class StockBalanceResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'store_id' => $this->store_id, 'product_id' => $this->product_id, 'quantity' => $this->quantity];
    }

    /** @return array<string,mixed> */
    public function with(Request $request): array
    {
        return ['meta' => ['request_id' => $request->attributes->get('request_id')]];
    }
}
