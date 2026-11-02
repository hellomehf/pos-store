<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'unit_id',
        'conversion_factor',
        'purchase_cost',
        'selling_price',
        'barcode',
        'is_base_unit',
        'is_purchase_unit',
        'is_selling_unit',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:6',
            'purchase_cost' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'is_base_unit' => 'boolean',
            'is_purchase_unit' => 'boolean',
            'is_selling_unit' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }
}
