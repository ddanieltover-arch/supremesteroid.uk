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
        'why_choose',
        'how_supplied',
        'where_it_fits',
        'who_its_for',
        'what_you_get',
        'key_differences',
        'answer_capsule',
        'related_reading',
    ];

    protected function casts(): array
    {
        return [
            'highlights' => 'array',
            'specifications' => 'array',
            'faqs' => 'array',
            'key_differences' => 'array',
            'related_reading' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
