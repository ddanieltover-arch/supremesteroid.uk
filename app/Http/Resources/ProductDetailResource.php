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
class ProductDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        $pricing = app(PricingService::class)->calculateProductPrice($product);
        $eligibility = app(ProductPurchaseEligibilityService::class)
            ->checkEligibility($product, null, 1, true);

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'status' => $product->status?->value,
            'classification' => $product->classification?->value,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'ingredients' => $product->ingredients,
            'safety_guidelines' => $product->safety_guidelines,
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $product->brand->name,
                'slug' => $product->brand->slug,
            ] : null,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'images' => $product->images->map(fn ($image) => [
                'id' => $image->id,
                'url' => $image->publicUrl(),
                'alt_text' => $image->alt_text,
                'is_primary' => (bool) $image->is_primary,
            ])->values(),
            'variants' => $product->variants->map(function ($variant) use ($product) {
                $variantPricing = app(PricingService::class)->calculateProductPrice($product, $variant);
                $variantEligibility = app(ProductPurchaseEligibilityService::class)
                    ->checkEligibility($product, $variant, 1, true);

                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'name' => $variant->name,
                    'effective_price' => $variantPricing['effective_price_amount'],
                    'is_active' => (bool) $variant->is_active,
                    'is_purchasable' => $variantEligibility['is_eligible'],
                    'available_quantity' => $variant->inventory?->available(),
                ];
            })->values(),
            'pricing' => [
                'currency' => $pricing['currency'],
                'base_price' => $pricing['base_price_amount'],
                'sale_price' => $pricing['sale_price_amount'],
                'effective_price' => $pricing['effective_price_amount'],
                'is_on_sale' => $pricing['is_on_sale'],
            ],
            'available_quantity' => $product->inventory?->available(),
            'is_purchasable' => $eligibility['is_eligible'],
            'ineligibility_reasons' => $eligibility['reasons'] ?? [],
            'specifications' => $product->attributes
                ->filter(fn ($attribute) => $attribute->is_public)
                ->map(fn ($attribute) => [
                    'name' => $attribute->attribute_name,
                    'value' => $attribute->attribute_value,
                ])->values(),
            'batch_number' => $product->batch_number,
            'lab_verification_reference' => $product->lab_verification_reference,
        ];
    }
}
