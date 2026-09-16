<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use App\Services\Catalogue\ProductPurchaseEligibilityService;
use App\Services\Pricing\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        $pricing = null;
        try {
            $pricing = app(PricingService::class)->calculateProductPrice($product);
        } catch (\Throwable) {
            $pricing = null;
        }

        $eligibility = app(ProductPurchaseEligibilityService::class)
            ->checkEligibility($product, null, 1, true);

        $primaryImage = $product->relationLoaded('images')
            ? $product->images->firstWhere('is_primary', true) ?? $product->images->first()
            : null;

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'status' => $product->status?->value,
            'classification' => $product->classification?->value,
            'short_description' => $product->short_description,
            'brand' => $product->brand?->name,
            'category' => $product->category?->name,
            'category_slug' => $product->category?->slug,
            'brand_slug' => $product->brand?->slug,
            'image_url' => $primaryImage?->publicUrl(),
            'image_alt' => $primaryImage?->alt_text ?: $product->name,
            'batch_number' => $product->batch_number,
            'lab_verification_reference' => $product->lab_verification_reference,
            'available_quantity' => $product->inventory?->available(),
            'pricing' => $pricing ? [
                'currency' => $pricing['currency'],
                'base_price' => $pricing['base_price_amount'],
                'sale_price' => $pricing['sale_price_amount'],
                'effective_price' => $pricing['effective_price_amount'],
                'is_on_sale' => $pricing['is_on_sale'],
            ] : null,
            'is_purchasable' => $eligibility['is_eligible'],
            'is_featured' => (bool) $product->is_featured,
        ];
    }
}
