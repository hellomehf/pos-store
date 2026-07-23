<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyReport extends Model
{
    protected $fillable = [
        'report_date',
        'gross_revenue',
        'total_expenses',
        'net_profit',
        'total_tax_collected',
        'total_transactions',
        'cash_count',
        'card_count',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'gross_revenue' => 'decimal:2',
            'total_expenses' => 'decimal:2',
            'net_profit' => 'decimal:2',
            'total_tax_collected' => 'decimal:2',
            'total_transactions' => 'integer',
            'cash_count' => 'integer',
            'card_count' => 'integer',
        ];
    }
}
