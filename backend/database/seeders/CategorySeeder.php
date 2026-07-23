<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Beverages', 'description' => 'Hot and cold drinks'],
            ['name' => 'Snacks', 'description' => 'Chips, cookies, and quick bites'],
            ['name' => 'Dairy', 'description' => 'Milk, cheese, and yogurt'],
            ['name' => 'Bakery', 'description' => 'Bread, pastries, and baked goods'],
            ['name' => 'Produce', 'description' => 'Fresh fruits and vegetables'],
            ['name' => 'Frozen', 'description' => 'Frozen foods and ice cream'],
            ['name' => 'Meat & Seafood', 'description' => 'Fresh and processed meats'],
            ['name' => 'Household', 'description' => 'Cleaning and household essentials'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
