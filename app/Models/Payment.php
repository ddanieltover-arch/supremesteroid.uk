<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'order_id',
        'method',
        'status',
        'amount',
        'currency',
        'reference',
        'expires_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethodType::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(PaymentSubmission::class);
    }

    public function latestSubmission(): HasOne
    {
        return $this->hasOne(PaymentSubmission::class)->latestOfMany();
    }
}
