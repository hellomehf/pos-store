<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ABA PayWay payment record.
 *
 * @property int    $id
 * @property string $tran_id
 * @property int    $order_id
 * @property string $amount
 * @property string $currency
 * @property string $status
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class AbaPayment extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PAID    = 'PAID';
    public const STATUS_FAILED  = 'FAILED';

    protected $table = 'aba_payments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'order_id');
    }
}
