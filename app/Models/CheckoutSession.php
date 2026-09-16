<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutSession extends Model
{
    use HasUuids;

    public const STATUS_OPEN = 'OPEN';
    public const STATUS_CONVERTED = 'CONVERTED';
    public const STATUS_EXPIRED = 'EXPIRED';

    protected $fillable = [
        'token',
        'user_id',
        'snapshot',
        'status',
        'expires_at',
        'order_id',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN && $this->expires_at?->isFuture();
    }
}
