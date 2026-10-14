<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use App\Models\Product;
use App\Models\SeoMetadata;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SeoService
{
    public function __construct(
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{title: string, description: string, canonical: string, robots: string, og_title: string, og_description: string, og_image: ?string}
     */
    public function forPage(
        Request $request,
        string $title,
        string $description,
        array $overrides = [],
        ?Model $model = null
    ): array {
        $record = $model?->seoMetadata;
        $canonical = $overrides['canonical'] ?? $request->url();

        $resolvedTitle = $record?->meta_title ?: $title;
        $resolvedDescription = $record?->meta_description ?: $this->defaultDescription($model, $title, $description);

        return [
            'title' => $resolvedTitle,
            'description' => Str::limit(trim($resolvedDescription), 160),
            'canonical' => $record?->canonical_url ?: $canonical,
            'robots' => $record?->robots_directive ?: ($overrides['robots'] ?? 'index,follow'),
            'og_title' => $record?->og_title ?: $resolvedTitle,
            'og_description' => $record?->og_description ?: Str::limit(trim($resolvedDescription), 200),
            'og_image' => $record?->og_image_url ?: ($overrides['og_image'] ?? null),
        ];
    }

    /**
     * Ensure safe SEO defaults exist before publication. Does not overwrite admin overrides.
     */
    public function ensureDefaultsForProduct(Product $product, bool $force = false): SeoMetadata
    {
        $product->loadMissing(['brand', 'category', 'images']);
        $seo = $product->seoMetadata ?? new SeoMetadata([
            'seoable_type' => Product::class,
            'seoable_id' => $product->id,
        ]);

        $defaults = $this->defaultPayloadForProduct($product);
        $before = $seo->exists ? $seo->only([
            'meta_title', 'meta_description', 'canonical_url', 'robots_directive', 'og_title', 'og_description', 'og_image_url',
        ]) : null;

        foreach ($defaults as $key => $value) {
            if ($force || blank($seo->{$key})) {
                $seo->{$key} = $value;
            }
        }

        if ($product->is_demo || $product->status?->value === 'COMPLIANCE_REVIEW') {
            $seo->robots_directive = 'noindex,nofollow';
        }

        $seo->save();
        $product->setRelation('seoMetadata', $seo);

        if ($before !== null) {
            $this->auditLogger->log(
                eventType: 'product_seo_defaults_applied',
                auditable: $product,
                oldValues: $before,
                newValues: $seo->only(array_keys($defaults)),
                reason: 'Safe SEO defaults ensured for publication checklist.'
            );
        }

        return $seo;
    }

    /**
     * @return array<string, string|null>
     */
    public function defaultPayloadForProduct(Product $product): array
    {
        $brand = $product->brand?->name;
        $category = $product->category?->name;
        $title = trim($product->name.($brand ? ' | '.$brand : '').' | Supreme Steroids');
        $title = Str::limit($title, 70, '');

        $description = trim(implode(' ', array_filter([
            $product->name,
            $brand ? 'from '.$brand : null,
            $category ? 'in '.$category : null,
            $product->sku ? 'SKU '.$product->sku : null,
            'Commercial specification listed on Supreme Steroids.',
        ])));
        $description = Str::limit($description, 160, '');

        $primary = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
        $ogImage = $primary?->publicUrl();

        return [
            'meta_title' => $title,
            'meta_description' => $description,
            'canonical_url' => url('/product/'.$product->slug),
            'robots_directive' => 'index,follow',
            'og_title' => $title,
            'og_description' => $description,
            'og_image_url' => $ogImage,
        ];
    }

    protected function defaultDescription(?Model $model, string $title, string $description): string
    {
        $base = trim($description);

        if ($model instanceof Product) {
            $parts = array_filter([
                $model->name,
                $model->brand?->name ? 'from '.$model->brand->name : null,
                $model->category?->name ? 'in '.$model->category->name : null,
                $model->sku ? 'SKU '.$model->sku : null,
            ]);

            return trim(implode(' ', $parts).'. '.$base);
        }

        if ($model instanceof \App\Models\Category || $model instanceof \App\Models\Brand) {
            return trim($title.'. '.$base);
        }

        return $base !== '' ? $base : $title;
    }
}
