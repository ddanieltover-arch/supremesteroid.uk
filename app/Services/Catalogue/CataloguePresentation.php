<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Pricing\PricingService;

class CataloguePresentation
{
    public function __construct(
        protected PricingService $pricing
    ) {}

    /**
     * @return list<array{code: string, label: string}>
     */
    public function badges(Product $product, ?ProductVariant $variant = null): array
    {
        $badges = [];
        $stock = $this->stock($product, $variant);

        try {
            $pricing = $this->pricing->calculateProductPrice($product, $variant);
        } catch (\Throwable) {
            $pricing = null;
        }

        $publishedAt = $product->published_at ?? $product->created_at;
        $newDays = (int) config('store.catalogue.new_product_days', 14);
        if ($publishedAt && $publishedAt->gte(now()->subDays($newDays))) {
            $badges[] = ['code' => 'NEW', 'label' => 'New'];
        }

        if ($product->is_featured) {
            $badges[] = ['code' => 'FEATURED', 'label' => 'Featured'];
        }

        if ($pricing['is_on_sale'] ?? false) {
            $percent = $pricing['discount_percent'] ?? null;
            $badges[] = [
                'code' => 'SALE',
                'label' => $percent ? 'Sale -'.$percent.'%' : 'Sale',
            ];
        }

        if ($stock['code'] === 'LOW_STOCK') {
            $badges[] = ['code' => 'LOW_STOCK', 'label' => 'Low stock'];
        }

        if ($stock['code'] === 'OUT_OF_STOCK') {
            $badges[] = ['code' => 'OUT_OF_STOCK', 'label' => 'Out of stock'];
        }

        if ($stock['code'] === 'COMING_SOON') {
            $badges[] = ['code' => 'COMING_SOON', 'label' => 'Coming soon'];
        }

        if ($product->compliance_verified_at && filled($product->lab_verification_reference)) {
            $badges[] = ['code' => 'VERIFIED_DOCUMENTATION', 'label' => 'Verified documentation'];
        }

        return $badges;
    }

    /**
     * @return array{code: string, label: string, quantity: int|null}
     */
    public function stock(Product $product, ?ProductVariant $variant = null): array
    {
        $inventory = $variant?->inventory ?? $product->inventory;
        $expose = (bool) config('store.catalogue.expose_exact_stock', false);
        $threshold = (int) config('store.catalogue.low_stock_threshold', 5);

        if (! $this->hasCatalogueImage($product)) {
            return ['code' => 'OUT_OF_STOCK', 'label' => 'Out of stock', 'quantity' => $expose ? 0 : null];
        }

        if ($product->status === ProductStatus::OUT_OF_STOCK) {
            return ['code' => 'OUT_OF_STOCK', 'label' => 'Out of stock', 'quantity' => $expose ? 0 : null];
        }

        if ($inventory === null) {
            return ['code' => 'COMING_SOON', 'label' => 'Coming soon', 'quantity' => null];
        }

        $available = $inventory->available();

        if ($available <= 0) {
            return ['code' => 'OUT_OF_STOCK', 'label' => 'Out of stock', 'quantity' => $expose ? 0 : null];
        }

        if ($available <= $threshold) {
            return ['code' => 'LOW_STOCK', 'label' => 'Low stock', 'quantity' => $expose ? $available : null];
        }

        return ['code' => 'IN_STOCK', 'label' => 'In stock', 'quantity' => $expose ? $available : null];
    }

    public function hasCatalogueImage(Product $product): bool
    {
        if ($product->relationLoaded('images')) {
            return $product->images->contains(
                fn ($image): bool => $this->isUsableCatalogueImageUrl($image->publicUrl() ?? $image->url ?? null)
            );
        }

        foreach ($product->images()->orderByDesc('is_primary')->orderBy('display_order')->get() as $image) {
            if ($this->isUsableCatalogueImageUrl($image->publicUrl() ?? $image->url ?? null)) {
                return true;
            }
        }

        return false;
    }

    public function usableImageUrl(?string $url): ?string
    {
        return $this->isUsableCatalogueImageUrl($url) ? $url : null;
    }

    /**
     * Broken reference CDN hosts render as empty placeholders on the storefront.
     * Treat those as missing so products stay out of stock until a local/repaired image exists.
     */
    public function isUsableCatalogueImageUrl(?string $url): bool
    {
        if (! filled($url)) {
            return false;
        }

        $normalized = strtolower(trim($url));

        foreach ($this->unusableImageHosts() as $host) {
            if (str_contains($normalized, $host)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Product>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Product>
     */
    public function constrainToUsableImages(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereHas('images', function ($images): void {
            $images->whereNotNull('url')->where('url', '!=', '');

            foreach ($this->unusableImageHosts() as $host) {
                $images->where('url', 'not like', '%'.$host.'%');
            }
        });
    }

    /**
     * @return list<string>
     */
    public function unusableImageHosts(): array
    {
        return [];
    }
}
