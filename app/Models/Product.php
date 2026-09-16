<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductClassification;
use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'sku',
        'name',
        'slug',
        'status',
        'classification',
        'price_amount',
        'compare_at_price_amount',
        'currency',
        'short_description',
        'description',
        'ingredients',
        'safety_guidelines',
        'batch_number',
        'lab_verification_reference',
        'compliance_verified_at',
        'compliance_officer_id',
        'brand_id',
        'category_id',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'classification' => ProductClassification::class,
            'price_amount' => 'decimal:2',
            'compare_at_price_amount' => 'decimal:2',
            'compliance_verified_at' => 'datetime',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * Scope for products that are published and compliance-verified.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::ACTIVE)
            ->whereIn('classification', ProductClassification::approvedForRetail())
            ->whereNotNull('compliance_verified_at');
    }

    /**
     * Scope for active products.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::ACTIVE);
    }

    /**
     * Scope for products approved for retail purchase.
     * Excludes DRAFT, IMPORTED, UNDER_REVIEW, SUSPENDED, and ARCHIVED items.
     * Strictly requires ACTIVE status, compliance verification timestamp, valid pricing, and valid title/sku.
     */
    public function scopePurchasable(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::ACTIVE)
            ->whereIn('classification', ProductClassification::approvedForRetail())
            ->whereNotNull('compliance_verified_at')
            ->where('price_amount', '>', 0)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereNotNull('name')
            ->where('name', '!=', '');
    }

    /**
     * Scope for products that currently have available inventory.
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereHas('inventory', function (Builder $q) {
            $q->whereRaw('quantity_on_hand - quantity_reserved > 0');
        });
    }

    /**
     * Scope by category ID or slug.
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->whereHas('category', function (Builder $q) use ($category) {
            $q->where('id', $category)->orWhere('slug', $category);
        });
    }

    /**
     * Scope by brand ID or slug.
     */
    public function scopeByBrand(Builder $query, string $brand): Builder
    {
        return $query->whereHas('brand', function (Builder $q) use ($brand) {
            $q->where('id', $brand)->orWhere('slug', $brand);
        });
    }

    /**
     * Search products by keyword across name, sku, and description.
     */
    public function scopeSearchable(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('display_order');
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->where('status', 'APPROVED');
    }

    public function seoMetadata(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
    }
}
