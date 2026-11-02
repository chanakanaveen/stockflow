<?php

namespace App\Models;

use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    protected $fillable = ['number', 'from_store_id', 'to_store_id', 'status', 'created_by', 'submitted_by', 'approved_by', 'rejected_by', 'submitted_at', 'approved_at', 'posted_at', 'rejected_at', 'rejection_reason'];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['status' => TransferStatus::class, 'submitted_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime', 'posted_at' => 'immutable_datetime', 'rejected_at' => 'immutable_datetime'];
    }

    /** @return HasMany<StockTransferLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class, 'transfer_id')->orderBy('product_id');
    }

    /** @param Builder<StockTransfer> $query
     * @return Builder<StockTransfer>
     */
    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $query->whereIn('from_store_id', Store::query()->accessibleTo($user)->select('id'))
            ->whereIn('to_store_id', Store::query()->accessibleTo($user)->select('id'));
    }

    /** @param Builder<StockTransfer> $query
     * @return Builder<StockTransfer>
     */
    public function scopeAwaitingApprovalBy(Builder $query, User $user): Builder
    {
        return $query->accessibleTo($user)->where('status', TransferStatus::Submitted->value)
            ->where('created_by', '<>', $user->id)->where('submitted_by', '<>', $user->id);
    }
}
