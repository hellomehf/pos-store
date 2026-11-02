<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitOfMeasureSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'Piece', 'abbreviation' => 'pc', 'unit_type' => 'count'],
            ['name' => 'Can', 'abbreviation' => 'can', 'unit_type' => 'count'],
            ['name' => 'Bottle', 'abbreviation' => 'btl', 'unit_type' => 'count'],
            ['name' => 'Pack', 'abbreviation' => 'pack', 'unit_type' => 'count'],
            ['name' => 'Box', 'abbreviation' => 'box', 'unit_type' => 'count'],
            ['name' => 'Carton', 'abbreviation' => 'ctn', 'unit_type' => 'count'],
            ['name' => 'Bag', 'abbreviation' => 'bag', 'unit_type' => 'count'],
            ['name' => 'Roll', 'abbreviation' => 'roll', 'unit_type' => 'count'],
            ['name' => 'Kilogram', 'abbreviation' => 'kg', 'unit_type' => 'weight'],
            ['name' => 'Gram', 'abbreviation' => 'g', 'unit_type' => 'weight'],
            ['name' => 'Liter', 'abbreviation' => 'L', 'unit_type' => 'volume'],
            ['name' => 'Milliliter', 'abbreviation' => 'ml', 'unit_type' => 'volume'],
        ];

        foreach ($units as $unit) {
            DB::table('units_of_measure')->updateOrInsert(
                ['abbreviation' => $unit['abbreviation']],
                $unit
            );
        }
    }
}
