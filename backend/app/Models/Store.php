<?php

namespace App\Models;

use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'location_label', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @param Builder<Store> $query
     * @return Builder<Store>
     */
    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $user->hasRole('System Admin') ? $query : $query->whereIn('id', $user->stores()->select('stores.id'));
    }
}
