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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'published_at',
        'sort_order',
        'is_demo',
        'image_not_required',
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
            'published_at' => 'datetime',
            'sort_order' => 'integer',
            'is_demo' => 'boolean',
            'image_not_required' => 'boolean',
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
            ->where('name', '!=', '')
            ->where('is_demo', false);
    }

    public function isPurchasable(): bool
    {
        return $this->status === ProductStatus::ACTIVE
            && in_array($this->classification, ProductClassification::approvedForRetail(), true)
            && $this->compliance_verified_at !== null
            && (float) $this->price_amount > 0
            && filled($this->sku)
            && filled($this->name)
            && ! $this->is_demo;
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
     * Push unavailable / out-of-stock catalogue cards after in-stock items,
     * while preserving any subsequent sort clauses.
     */
    public function scopeOrderAvailableFirst(Builder $query): Builder
    {
        $hosts = app(\App\Services\Catalogue\CataloguePresentation::class)->unusableImageHosts();

        $imageExists = \App\Models\ProductImage::query()
            ->selectRaw('1')
            ->whereColumn('product_images.product_id', 'products.id')
            ->whereNotNull('url')
            ->where('url', '!=', '');

        foreach ($hosts as $host) {
            $imageExists->where('url', 'not like', '%'.$host.'%');
        }

        $imageExists->limit(1);

        $stockExists = \App\Models\Inventory::query()
            ->selectRaw('1')
            ->whereColumn('inventory.product_id', 'products.id')
            ->whereRaw('quantity_on_hand - quantity_reserved > 0')
            ->limit(1);

        return $query->orderByRaw(
            'CASE
                WHEN products.status = ? THEN 1
                WHEN NOT EXISTS ('.$imageExists->toSql().') THEN 1
                WHEN NOT EXISTS ('.$stockExists->toSql().') THEN 1
                ELSE 0
            END ASC',
            array_merge(
                [ProductStatus::OUT_OF_STOCK->value],
                $imageExists->getBindings(),
                $stockExists->getBindings()
            )
        );
    }

    /**
     * Scope by category ID or slug.
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where(function (Builder $q) use ($category) {
            $q->whereHas('category', function (Builder $categoryQuery) use ($category) {
                $categoryQuery->where('id', $category)->orWhere('slug', $category);
            })->orWhereHas('categories', function (Builder $categoryQuery) use ($category) {
                $categoryQuery->where('id', $category)->orWhere('slug', $category);
            });
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
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhereHas('brand', fn (Builder $brand) => $brand->where('name', 'like', "%{$term}%"))
                ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', "%{$term}%"))
                ->orWhereHas('attributes', fn (Builder $attribute) => $attribute->where('attribute_value', 'like', "%{$term}%"))
                ->orWhereHas('variants', fn (Builder $variant) => $variant
                    ->where('sku', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhereHas('attributes', fn (Builder $attribute) => $attribute->where('attribute_value', 'like', "%{$term}%")));
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
        return $this->hasMany(Review::class)->where('status', \App\Enums\ReviewStatus::APPROVED);
    }

    public function seoMetadata(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_category')->withPivot('is_primary')->withTimestamps();
    }

    public function content(): HasOne
    {
        return $this->hasOne(ProductContent::class);
    }

    public function relations(): HasMany
    {
        return $this->hasMany(ProductRelation::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ProductRevision::class)->orderByDesc('revision_number');
    }

    public function allReviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function importRecord(): HasOne
    {
        return $this->hasOne(CatalogueImportRecord::class, 'product_id');
    }

    public function isInternalSku(): bool
    {
        return str_starts_with((string) $this->sku, 'IMP-');
    }

    public function hasGenuineSku(): bool
    {
        return ! $this->isInternalSku() && filled($this->sku);
    }

    public function genuineSku(): ?string
    {
        return $this->isInternalSku() ? null : $this->sku;
    }

    public function displaySku(): string
    {
        return $this->hasGenuineSku() ? (string) $this->sku : 'None (Internal: '.$this->sku.')';
    }
}
