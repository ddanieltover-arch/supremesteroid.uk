<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductRelationType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRelation extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id',
        'related_product_id',
        'relation_type',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'relation_type' => ProductRelationType::class,
            'display_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function relatedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'related_product_id');
    }
}
