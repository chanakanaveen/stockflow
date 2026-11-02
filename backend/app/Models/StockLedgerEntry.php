<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class StockLedgerEntry extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['quantity_delta' => 'decimal:4', 'balance_after' => 'decimal:4', 'posted_at' => 'immutable_datetime', 'movement_type' => MovementType::class];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Ledger entries are immutable.');
        });
        static::deleting(function (): void {
            throw new LogicException('Ledger entries are immutable.');
        });
    }
}
