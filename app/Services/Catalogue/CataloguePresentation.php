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
}
