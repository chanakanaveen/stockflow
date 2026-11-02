<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockDocumentLine extends Model
{
    protected $fillable = ['document_id', 'product_id', 'quantity'];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }
}
