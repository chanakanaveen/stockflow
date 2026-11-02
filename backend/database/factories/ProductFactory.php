<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['sku' => fake()->unique()->bothify('SKU-????####'), 'name' => fake()->words(3, true), 'category_id' => Category::factory(), 'base_uom' => 'unit', 'reorder_level' => '5.0000', 'is_active' => true];
    }
}
