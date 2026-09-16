<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingMethod extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'shipping_zone_id',
        'name',
        'code',
        'rate_amount',
        'currency',
        'estimated_days',
        'is_discreet',
        'is_active',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'rate_amount' => 'decimal:2',
            'is_discreet' => 'boolean',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ShippingRule::class);
    }
}
