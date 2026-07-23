<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'total_khr' => 'decimal:2',
            'tendered_amount' => 'decimal:2',
            'tendered_khr' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'change_khr' => 'decimal:2',
            'exchange_rate' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public static function generateInvoiceNumber(): string
    {
        $date = now()->format('Ymd');
        $lastSale = self::where('invoice_number', 'like', "INV-{$date}-%")
            ->orderByDesc('invoice_number')
            ->first();

        if ($lastSale) {
            $lastNumber = (int) substr($lastSale->invoice_number, -5);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('INV-%s-%05d', $date, $nextNumber);
    }
}
