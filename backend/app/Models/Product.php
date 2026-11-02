<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = ['sku', 'name', 'category_id', 'base_uom', 'reorder_level', 'is_active'];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'reorder_level' => 'decimal:4'];
    }
}
