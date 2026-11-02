<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockBalance extends Model
{
    protected $guarded = ['id', 'quantity', 'store_id', 'product_id'];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }
}
