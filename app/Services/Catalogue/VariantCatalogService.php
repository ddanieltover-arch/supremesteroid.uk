<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantAttribute;
use Illuminate\Validation\ValidationException;

class VariantCatalogService
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, string>  $pairs
     */
    public function create(Product $product, array $attributes, array $pairs = []): ProductVariant
    {
        $sku = strtoupper(trim((string) ($attributes['sku'] ?? '')));
        if ($sku === '') {
            throw ValidationException::withMessages(['sku' => 'A variant SKU is required.']);
        }

        if (ProductVariant::query()->where('sku', $sku)->exists()) {
            throw ValidationException::withMessages(['sku' => 'A variant with this SKU already exists.']);
        }

        $normalized = $this->normalizePairs($pairs);
        if ($normalized !== [] && $this->attributeSetExists($product, $normalized)) {
            throw ValidationException::withMessages(['attributes' => 'A variant with this attribute combination already exists.']);
        }

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => $sku,
            'name' => $attributes['name'] ?? $sku,
            'price_amount' => $attributes['price_amount'] ?? $product->price_amount,
            'compare_at_price_amount' => $attributes['compare_at_price_amount'] ?? null,
            'barcode' => $attributes['barcode'] ?? null,
            'weight_grams' => $attributes['weight_grams'] ?? null,
            'is_active' => $attributes['is_active'] ?? true,
        ]);

        foreach ($normalized as $name => $value) {
            VariantAttribute::query()->create([
                'product_variant_id' => $variant->id,
                'attribute_name' => $name,
                'attribute_value' => $value,
            ]);
        }

        return $variant->fresh(['attributes']);
    }

    /**
     * @param  array<string, string>  $pairs
     */
    public function attributeSetExists(Product $product, array $pairs, ?string $ignoreVariantId = null): bool
    {
        $normalized = $this->normalizePairs($pairs);
        $signature = $this->signature($normalized);

        return $product->variants()
            ->with('attributes')
            ->when($ignoreVariantId, fn ($query) => $query->where('id', '!=', $ignoreVariantId))
            ->get()
            ->contains(function (ProductVariant $variant) use ($signature) {
                $existing = [];
                foreach ($variant->attributes as $attribute) {
                    $existing[$attribute->attribute_name] = $attribute->attribute_value;
                }

                return $this->signature($this->normalizePairs($existing)) === $signature;
            });
    }

    /**
     * @param  array<string, string>  $pairs
     * @return array<string, string>
     */
    public function normalizePairs(array $pairs): array
    {
        $normalized = [];

        foreach ($pairs as $name => $value) {
            $key = strtoupper(trim((string) $name));
            $val = trim((string) $value);
            if ($key === '' || $val === '') {
                continue;
            }
            $normalized[$key] = $val;
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param  array<string, string>  $pairs
     */
    public function signature(array $pairs): string
    {
        return collect($pairs)->map(fn ($value, $name) => $name.'='.$value)->implode('|');
    }
}
