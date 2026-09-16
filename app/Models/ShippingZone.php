<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShippingZoneType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'code',
        'type',
        'countries',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ShippingZoneType::class,
            'countries' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function methods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class);
    }
}
