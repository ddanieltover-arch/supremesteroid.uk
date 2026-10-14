<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportMappingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogueCategoryMapping extends Model
{
    use HasUuids;

    protected $fillable = [
        'source_category',
        'normalized_source_category',
        'target_category_id',
        'status',
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

    public function targetCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'target_category_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
