<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['category_id', 'name', 'sku', 'barcode', 'price', 'cost_price', 'stock_qty', 'image', 'is_active'])]
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock_qty' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function generateSku(string $categoryName): string
    {
        $clean = preg_replace('/[^A-Za-z]/', '', $categoryName);
        $prefix = strtoupper(substr($clean, 0, 3));
        if (strlen($prefix) < 3) {
            $prefix = str_pad($prefix, 3, 'X');
        }

        $lastSku = self::where('sku', 'like', "$prefix-%")
            ->orderByRaw("CAST(SUBSTRING(sku, LOCATE('-', sku) + 1) AS UNSIGNED) DESC")
            ->value('sku');

        if ($lastSku && preg_match('/-(\d+)$/', $lastSku, $m)) {
            $next = (int) $m[1] + 1;
        } else {
            $next = 1;
        }

        return $prefix . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function isLowStock(int $threshold = 10): bool
    {
        return $this->stock_qty <= $threshold;
    }
}
