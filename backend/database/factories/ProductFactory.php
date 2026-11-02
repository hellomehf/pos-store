<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $counter = 0;
        $counter++;

        return [
            'category_id' => Category::factory(),
            'name'        => fake()->unique()->words(3, true),
            'sku'         => 'TST-' . str_pad($counter, 3, '0', STR_PAD_LEFT) . '-' . fake()->unique()->numberBetween(100, 999),
            'barcode'     => null,
            'price'       => fake()->randomFloat(2, 0.50, 50.00),
            'cost_price'  => fake()->randomFloat(2, 0.10, 30.00),
            'stock_qty'   => fake()->numberBetween(10, 200),
            'image'       => null,
            'is_active'   => true,
            'created_by'  => null,
            'base_unit_id' => null,
        ];
    }
}
