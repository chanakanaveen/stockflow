<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockDocument extends Model
{
    protected $fillable = ['number', 'type', 'store_id', 'status', 'reference', 'remarks', 'created_by', 'posted_by', 'posted_at'];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['type' => DocumentType::class, 'status' => DocumentStatus::class, 'posted_at' => 'immutable_datetime'];
    }

    /** @return HasMany<StockDocumentLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockDocumentLine::class, 'document_id')->orderBy('product_id');
    }

    /** @param Builder<StockDocument> $query
     * @return Builder<StockDocument>
     */
    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $query->whereIn('store_id', Store::query()->accessibleTo($user)->select('id'));
    }
}
