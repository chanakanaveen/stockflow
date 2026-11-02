<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'sku' => $this->sku, 'name' => $this->name, 'category_id' => $this->category_id, 'base_uom' => $this->base_uom, 'reorder_level' => $this->reorder_level, 'is_active' => $this->is_active];
    }

    /** @return array<string,mixed> */
    public function with(Request $request): array
    {
        return ['meta' => ['request_id' => $request->attributes->get('request_id')]];
    }
}
