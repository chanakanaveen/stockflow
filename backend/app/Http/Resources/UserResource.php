<?php

namespace App\Http\Resources;

use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'is_active' => $this->is_active,
            'roles' => $this->getRoleNames()->sort()->values(),
            'permissions' => $this->getAllPermissions()->pluck('name')->sort()->values(),
            'store_ids' => Store::query()->accessibleTo($this->resource)->orderBy('id')->pluck('id'),
        ];
    }

    /** @return array<string, mixed> */
    public function with(Request $request): array
    {
        return ['meta' => ['request_id' => $request->attributes->get('request_id')]];
    }
}
