<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use App\Enums\ProductClassification;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Str;

class CataloguePublicationChecklistService
{
    public function __construct(
        protected SeoService $seoService,
    ) {}

    /**
     * Authoritative publication readiness checklist.
     *
     * @return array{
     *   status: 'READY'|'BLOCKED',
     *   can_publish: bool,
     *   items: list<array{key: string, label: string, passed: bool, severity: string, message: string}>,
     *   failures: list<string>
     * }
     */
    public function evaluate(Product $product): array
    {
        $product->loadMissing([
            'brand',
            'category',
            'images',
            'seoMetadata',
            'content',
            'variants.inventory',
            'inventory',
            'relations.relatedProduct',
        ]);

        $items = [
            $this->item('identity', 'Identity', filled($product->name) && filled($product->slug), 'critical', 'Missing product identity (name/slug).'),
            $this->item('sku', 'SKU', filled($product->sku), 'critical', 'Missing SKU.'),
            $this->item('supplier_sku', 'Supplier SKU', $product->hasGenuineSku(), 'warning', 'No genuine supplier SKU documented; internal identifier ('.$product->sku.') is assigned.'),
            $this->item('brand', 'Brand', $product->brand_id !== null, 'critical', 'Missing brand.'),
            $this->item('category', 'Category', $product->category_id !== null || $product->categories()->exists(), 'critical', 'Missing category.'),
            $this->item('variant', 'Variant structure', $this->variantsValid($product), 'critical', 'Variant duplicate or invalid structure.'),
            $this->item('price', 'Price', (float) $product->price_amount > 0, 'critical', 'No valid price.'),
            $this->item('inventory', 'Inventory', $this->inventoryValid($product), 'critical', 'Inventory configuration invalid.'),
            $this->item(
                'classification',
                'Classification',
                in_array($product->classification, ProductClassification::approvedForRetail(), true),
                'critical',
                'Unapproved classification.'
            ),
            $this->item(
                'compliance',
                'Compliance',
                $product->compliance_verified_at !== null
                    && $product->status !== ProductStatus::COMPLIANCE_REVIEW,
                'critical',
                $product->status === ProductStatus::COMPLIANCE_REVIEW
                    ? 'Compliance review incomplete — product is held.'
                    : 'Compliance review incomplete.'
            ),
            $this->item(
                'content',
                'Content',
                filled($product->description) || filled($product->short_description) || filled($product->content?->overview),
                'critical',
                'Missing product description.'
            ),
            $this->item('specifications', 'Specifications', true, 'warning', 'Specifications optional when not documented.'),
            $this->item('media', 'Primary media', $this->mediaValid($product), 'critical', $this->mediaFailureMessage($product)),
            $this->item('media_validity', 'Media validity', $this->mediaReferencesValid($product), 'critical', 'Invalid media.'),
            $this->item('seo', 'SEO', $this->seoValid($product), 'critical', $this->seoFailureMessage($product)),
            $this->item(
                'og_image',
                'OG image',
                $this->ogImageValid($product),
                $product->image_not_required ? 'warning' : 'warning',
                'OG image missing (non-blocking when defaults can be derived later).'
            ),
            $this->item('related', 'Related products', $this->relatedValid($product), 'warning', 'Related products include non-purchasable targets (hidden publicly).'),
            $this->item(
                'demo',
                'Production catalogue',
                ! $product->is_demo,
                'critical',
                'Demo products cannot be published.'
            ),
        ];

        $criticalFailures = collect($items)
            ->filter(fn (array $item) => ! $item['passed'] && $item['severity'] === 'critical')
            ->values();

        $canPublish = $criticalFailures->isEmpty();

        return [
            'status' => $canPublish ? 'READY' : 'BLOCKED',
            'can_publish' => $canPublish,
            'items' => $items,
            'failures' => $criticalFailures->pluck('message')->all(),
        ];
    }

    /**
     * CLI/admin oriented report lines.
     *
     * @return list<string>
     */
    public function reportLines(Product $product): array
    {
        $result = $this->evaluate($product);
        $lines = [
            'PRODUCT '.$product->sku,
            'Status: '.$result['status'],
            '',
        ];

        foreach ($result['items'] as $item) {
            $lines[] = $item['label'].': '.($item['passed'] ? 'PASS' : 'FAIL');
        }

        $lines[] = '';
        $lines[] = 'Publication: '.($result['can_publish'] ? 'READY' : 'BLOCKED');

        return $lines;
    }

    protected function variantsValid(Product $product): bool
    {
        $skus = $product->variants->pluck('sku')->filter()->map(fn ($sku) => strtoupper((string) $sku));

        return $skus->count() === $skus->unique()->count();
    }

    protected function inventoryValid(Product $product): bool
    {
        if ($product->variants->isNotEmpty()) {
            return $product->variants->every(function ($variant) {
                $inventory = $variant->inventory;

                return $inventory !== null && (int) $inventory->quantity_on_hand >= 0;
            });
        }

        $inventory = $product->inventory;

        return $inventory !== null && (int) $inventory->quantity_on_hand >= 0;
    }

    protected function mediaValid(Product $product): bool
    {
        if ($product->image_not_required) {
            return true;
        }

        $images = $product->images;
        if ($images->isEmpty()) {
            return false;
        }

        return $images->contains(fn (ProductImage $image) => $image->is_primary)
            || $images->count() === 1;
    }

    protected function mediaReferencesValid(Product $product): bool
    {
        if ($product->image_not_required && $product->images->isEmpty()) {
            return true;
        }

        return $product->images->every(function (ProductImage $image) {
            return filled($image->url)
                && filled($image->alt_text)
                && ! str_contains((string) $image->url, ' ')
                && ! str_contains(strtolower((string) $image->url), 'broken');
        });
    }

    protected function mediaFailureMessage(Product $product): string
    {
        if ($product->image_not_required) {
            return 'Image not required override is set.';
        }

        if ($product->images->isEmpty()) {
            return 'Missing primary image.';
        }

        if (! $product->images->contains(fn (ProductImage $image) => $image->is_primary) && $product->images->count() > 1) {
            return 'Missing primary image.';
        }

        return 'Missing primary image.';
    }

    protected function seoValid(Product $product): bool
    {
        $seo = $product->seoMetadata;
        $title = trim((string) ($seo?->meta_title ?: ''));
        $description = trim((string) ($seo?->meta_description ?: ''));
        $canonical = trim((string) ($seo?->canonical_url ?: ''));

        if ($title === '' || $description === '') {
            return false;
        }

        if (mb_strlen($title) > 70 || mb_strlen($description) > 170) {
            return false;
        }

        if ($this->containsUnsafeHtml($title) || $this->containsUnsafeHtml($description)) {
            return false;
        }

        if ($canonical !== '' && ! filter_var($canonical, FILTER_VALIDATE_URL) && ! str_starts_with($canonical, '/')) {
            return false;
        }

        return true;
    }

    protected function seoFailureMessage(Product $product): string
    {
        $seo = $product->seoMetadata;
        if (! filled($seo?->meta_title)) {
            return 'SEO title missing.';
        }
        if (! filled($seo?->meta_description)) {
            return 'SEO description missing.';
        }
        if (mb_strlen((string) $seo->meta_title) > 70) {
            return 'SEO title too long.';
        }
        if (mb_strlen((string) $seo->meta_description) > 170) {
            return 'SEO description too long.';
        }

        return 'SEO validation failed.';
    }

    protected function ogImageValid(Product $product): bool
    {
        if ($product->image_not_required && $product->images->isEmpty()) {
            return true;
        }

        $seo = $product->seoMetadata;
        if (filled($seo?->og_image_url)) {
            return true;
        }

        $primary = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

        return $primary !== null && filled($primary->publicUrl());
    }

    protected function relatedValid(Product $product): bool
    {
        if ($product->relations->isEmpty()) {
            return true;
        }

        // Historical relations may remain; public display already filters purchasable.
        return $product->relations->contains(function ($relation) {
            $related = $relation->relatedProduct;

            return $related !== null
                && Product::query()->purchasable()->whereKey($related->id)->exists();
        });
    }

    protected function containsUnsafeHtml(string $value): bool
    {
        return $value !== strip_tags($value) || Str::contains(strtolower($value), ['<script', 'javascript:']);
    }

    /**
     * @return array{key: string, label: string, passed: bool, severity: string, message: string}
     */
    protected function item(string $key, string $label, bool $passed, string $severity, string $failureMessage): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'passed' => $passed,
            'severity' => $severity,
            'message' => $passed ? $label : $failureMessage,
        ];
    }
}
