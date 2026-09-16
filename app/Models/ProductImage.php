<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'product_id',
        'url',
        'alt_text',
        'display_order',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function publicUrl(): ?string
    {
        $value = $this->attributes['url'] ?? null;
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $value) === 1 || str_starts_with($value, '/')) {
            return $value;
        }

        try {
            return \Illuminate\Support\Facades\Storage::disk((string) config('filesystems.cloud', 'public'))->url($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
