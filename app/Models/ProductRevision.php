<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRevision extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id',
        'revision_number',
        'actor_id',
        'changed_fields',
        'old_values',
        'new_values',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'changed_fields' => 'array',
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
