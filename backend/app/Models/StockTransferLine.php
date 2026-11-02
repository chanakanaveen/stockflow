<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransferLine extends Model
{
    protected $fillable = ['transfer_id', 'product_id', 'quantity'];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }
}
