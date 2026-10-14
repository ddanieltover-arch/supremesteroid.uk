<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportMappingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogueBrandMapping extends Model
{
    use HasUuids;

    protected $fillable = [
        'source_brand',
        'normalized_source_brand',
        'target_brand_id',
        'status',
        'match_hint',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportMappingStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function targetBrand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'target_brand_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
