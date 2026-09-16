<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'shipping_method_id',
        'rule_type',
        'min_subtotal',
        'max_subtotal',
        'override_rate_amount',
        'is_free_shipping',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_subtotal' => 'decimal:2',
            'max_subtotal' => 'decimal:2',
            'override_rate_amount' => 'decimal:2',
            'is_free_shipping' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }
}
