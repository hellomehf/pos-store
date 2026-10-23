<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'type', 'description', 'properties', 'ip_address'];

    protected $casts = [
        'properties' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function log(string $type, string $description, array $properties = [], ?int $userId = null, ?string $ip = null): static
    {
        return static::create([
            'user_id' => $userId ?? auth()->id(),
            'type' => $type,
            'description' => $description,
            'properties' => $properties,
            'ip_address' => $ip ?? request()->ip(),
        ]);
    }
}
