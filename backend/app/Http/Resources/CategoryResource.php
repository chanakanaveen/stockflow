<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'is_active' => $this->is_active];
    }

    /** @return array<string,mixed> */
    public function with(Request $request): array
    {
        return ['meta' => ['request_id' => $request->attributes->get('request_id')]];
    }
}
