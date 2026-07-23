<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingPayment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'expected_amount_khr' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }
}
