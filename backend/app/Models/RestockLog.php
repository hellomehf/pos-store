<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestockLog extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'quantity_added',
        'previous_stock',
        'new_stock',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity_added'  => 'integer',
            'previous_stock'  => 'integer',
            'new_stock'       => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
