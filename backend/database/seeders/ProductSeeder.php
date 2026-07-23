<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            'Beverages' => [
                ['name' => 'Espresso', 'sku' => 'BEV-001', 'price' => 3.50, 'cost_price' => 1.75, 'stock_qty' => 100],
                ['name' => 'Cappuccino', 'sku' => 'BEV-002', 'price' => 4.50, 'cost_price' => 2.25, 'stock_qty' => 100],
                ['name' => 'Iced Latte', 'sku' => 'BEV-003', 'price' => 5.00, 'cost_price' => 2.50, 'stock_qty' => 80],
                ['name' => 'Fresh Orange Juice', 'sku' => 'BEV-004', 'price' => 4.00, 'cost_price' => 2.00, 'stock_qty' => 50],
                ['name' => 'Bottled Water 500ml', 'sku' => 'BEV-005', 'price' => 1.50, 'cost_price' => 0.75, 'stock_qty' => 200],
                ['name' => 'Green Tea', 'sku' => 'BEV-006', 'price' => 3.00, 'cost_price' => 1.50, 'stock_qty' => 60],
            ],
            'Snacks' => [
                ['name' => 'Potato Chips (Regular)', 'sku' => 'SNK-001', 'price' => 2.50, 'cost_price' => 1.25, 'stock_qty' => 150],
                ['name' => 'Chocolate Bar', 'sku' => 'SNK-002', 'price' => 2.00, 'cost_price' => 1.00, 'stock_qty' => 120],
                ['name' => 'Trail Mix', 'sku' => 'SNK-003', 'price' => 3.50, 'cost_price' => 1.75, 'stock_qty' => 40],
                ['name' => 'Granola Bar', 'sku' => 'SNK-004', 'price' => 1.75, 'cost_price' => 0.85, 'stock_qty' => 80],
                ['name' => 'Popcorn (Butter)', 'sku' => 'SNK-005', 'price' => 3.00, 'cost_price' => 1.50, 'stock_qty' => 5],
            ],
            'Dairy' => [
                ['name' => 'Whole Milk 1L', 'sku' => 'DRY-001', 'price' => 3.00, 'cost_price' => 1.50, 'stock_qty' => 60],
                ['name' => 'Greek Yogurt', 'sku' => 'DRY-002', 'price' => 2.50, 'cost_price' => 1.25, 'stock_qty' => 45],
                ['name' => 'Cheddar Cheese Block', 'sku' => 'DRY-003', 'price' => 5.50, 'cost_price' => 2.75, 'stock_qty' => 30],
                ['name' => 'Butter 250g', 'sku' => 'DRY-004', 'price' => 4.00, 'cost_price' => 2.00, 'stock_qty' => 25],
            ],
            'Bakery' => [
                ['name' => 'Sourdough Loaf', 'sku' => 'BKY-001', 'price' => 4.50, 'cost_price' => 2.25, 'stock_qty' => 15],
                ['name' => 'Croissant', 'sku' => 'BKY-002', 'price' => 2.50, 'cost_price' => 1.25, 'stock_qty' => 20],
                ['name' => 'Whole Wheat Bread', 'sku' => 'BKY-003', 'price' => 3.50, 'cost_price' => 1.75, 'stock_qty' => 18],
                ['name' => 'Blueberry Muffin', 'sku' => 'BKY-004', 'price' => 3.00, 'cost_price' => 1.50, 'stock_qty' => 12],
            ],
            'Produce' => [
                ['name' => 'Bananas (1 bunch)', 'sku' => 'PRD-001', 'price' => 1.50, 'cost_price' => 0.75, 'stock_qty' => 50],
                ['name' => 'Avocado', 'sku' => 'PRD-002', 'price' => 2.00, 'cost_price' => 1.00, 'stock_qty' => 30],
                ['name' => 'Tomatoes 1kg', 'sku' => 'PRD-003', 'price' => 3.50, 'cost_price' => 1.75, 'stock_qty' => 40],
                ['name' => 'Mixed Salad Greens', 'sku' => 'PRD-004', 'price' => 4.00, 'cost_price' => 2.00, 'stock_qty' => 25],
            ],
            'Frozen' => [
                ['name' => 'Frozen Pizza', 'sku' => 'FRZ-001', 'price' => 7.50, 'cost_price' => 3.75, 'stock_qty' => 20],
                ['name' => 'Ice Cream (Vanilla)', 'sku' => 'FRZ-002', 'price' => 5.00, 'cost_price' => 2.50, 'stock_qty' => 15],
                ['name' => 'Frozen Veggies Mix', 'sku' => 'FRZ-003', 'price' => 3.50, 'cost_price' => 1.75, 'stock_qty' => 35],
            ],
            'Meat & Seafood' => [
                ['name' => 'Chicken Breast 500g', 'sku' => 'MST-001', 'price' => 8.00, 'cost_price' => 4.00, 'stock_qty' => 20],
                ['name' => 'Ground Beef 500g', 'sku' => 'MST-002', 'price' => 9.50, 'cost_price' => 4.75, 'stock_qty' => 15],
                ['name' => 'Salmon Fillet', 'sku' => 'MST-003', 'price' => 12.00, 'cost_price' => 6.00, 'stock_qty' => 8],
            ],
            'Household' => [
                ['name' => 'Paper Towels (6-roll)', 'sku' => 'HH-001', 'price' => 8.50, 'cost_price' => 4.25, 'stock_qty' => 40],
                ['name' => 'Dish Soap', 'sku' => 'HH-002', 'price' => 3.50, 'cost_price' => 1.75, 'stock_qty' => 35],
                ['name' => 'Trash Bags (30ct)', 'sku' => 'HH-003', 'price' => 6.00, 'cost_price' => 3.00, 'stock_qty' => 3],
            ],
        ];

        foreach ($products as $categoryName => $items) {
            $category = Category::where('name', $categoryName)->first();
            if (! $category) continue;

            foreach ($items as $product) {
                Product::updateOrCreate(
                    ['sku' => $product['sku']],
                    array_merge($product, ['category_id' => $category->id])
                );
            }
        }
    }
}
