<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductContent extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id',
        'overview',
        'highlights',
        'specifications',
        'composition',
        'manufacturer_information',
        'packaging_information',
        'documentation',
        'storage_information',
        'shipping_information',
        'faqs',
        'important_information',
    ];

    protected function casts(): array
    {
        return [
            'highlights' => 'array',
            'specifications' => 'array',
            'faqs' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
