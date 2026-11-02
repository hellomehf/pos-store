<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'name', 'sku', 'barcode', 'price', 'cost_price', 'stock_qty', 'image', 'is_active', 'created_by', 'base_unit_id'])]
class Product extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock_qty' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function generateSku(string $categoryName, ?int $categoryId = null): string
    {
        $prefix = null;

        if ($categoryId) {
            $existingSku = self::where('category_id', $categoryId)
                ->where('sku', 'like', '___-%')
                ->value('sku');
            if ($existingSku && preg_match('/^([A-Z]{2,4})-/', $existingSku, $m)) {
                $prefix = $m[1];
            }
        }

        if (!$prefix) {
            $clean = preg_replace('/[^A-Za-z]/', '', $categoryName);
            $prefix = strtoupper(substr($clean, 0, 3));
            if (strlen($prefix) < 3) {
                $prefix = str_pad($prefix, 3, 'X');
            }
        }

        $lastSku = self::where('sku', 'like', "$prefix-%")
            ->orderByRaw("CAST(SUBSTRING(sku, LOCATE('-', sku) + 1) AS UNSIGNED) DESC")
            ->value('sku');

        if ($lastSku && preg_match('/-(\d+)$/', $lastSku, $m)) {
            $next = (int) $m[1] + 1;
        } else {
            $next = 1;
        }

        $sku = $prefix . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);

        while (self::where('sku', $sku)->exists()) {
            $next++;
            $sku = $prefix . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);
        }

        return $sku;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_unit_id');
    }

    public function getBaseUnitRecord()
    {
        return $this->productUnits()->where('is_base_unit', true)->first();
    }

    public function isLowStock(int $threshold = 10): bool
    {
        return $this->stock_qty <= $threshold;
    }
}
