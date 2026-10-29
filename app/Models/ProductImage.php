<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Catalogue\CataloguePresentation;
use App\Services\Catalogue\CatalogueStoredImageCdnFallback;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ProductImage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'product_id',
        'url',
        'alt_text',
        'display_order',
        'is_primary',
        'caption',
        'thumbnail_url',
        'product_variant_id',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ProductImage $image): void {
            if ($image->product_variant_id === null) {
                return;
            }

            $belongs = ProductVariant::query()
                ->whereKey($image->product_variant_id)
                ->where('product_id', $image->product_id)
                ->exists();

            if (! $belongs) {
                throw new InvalidArgumentException('Cannot assign an image to a variant that belongs to another product.');
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function publicUrl(): ?string
    {
        $value = $this->attributes['url'] ?? null;
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (str_starts_with($value, '/storage/')) {
            // Prefer the recorded CDN original whenever available. Localized
            // /storage files are gitignored and missing from Vercel containers.
            $remote = app(CatalogueStoredImageCdnFallback::class)->remoteUrlForStoredPath($value);
            if (is_string($remote) && $remote !== '') {
                return CataloguePresentation::encodeImageUrl($remote);
            }

            $relative = ltrim(substr($value, strlen('/storage/')), '/');
            try {
                if (Storage::disk('public')->exists($relative)) {
                    return $value;
                }
            } catch (\Throwable) {
                // fall through
            }

            return $value;
        }

        if (preg_match('#^https?://#i', $value) === 1) {
            return CataloguePresentation::encodeImageUrl($value);
        }

        if (str_starts_with($value, '/')) {
            return $value;
        }

        try {
            return Storage::disk((string) config('filesystems.cloud', 'public'))->url($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
