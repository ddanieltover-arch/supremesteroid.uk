<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use App\Models\Product;
use App\Models\Review;
use App\Services\Pricing\PricingService;

class StructuredDataService
{
    public function __construct(
        protected PricingService $pricing,
        protected CataloguePresentation $presentation
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function organization(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('store.legal_name', config('store.name')),
            'url' => config('store.domain'),
            'logo' => config('store.logo_url'),
            'email' => filled(config('store.support_email')) ? config('store.support_email') : 'info@supremesteroid.uk',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('store.name'),
            'url' => config('store.domain'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => rtrim((string) config('store.domain'), '/').'/search?search={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * @param  list<array{name: string, url: string}>  $crumbs
     * @return array<string, mixed>
     */
    public function breadcrumbs(array $crumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)->values()->map(fn (array $crumb, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forProduct(Product $product): array
    {
        $pricing = $this->pricing->calculateProductPrice($product);
        $stock = $this->presentation->stock($product);
        $availability = match ($stock['code']) {
            'IN_STOCK', 'LOW_STOCK' => 'https://schema.org/InStock',
            'OUT_OF_STOCK' => 'https://schema.org/OutOfStock',
            default => 'https://schema.org/PreOrder',
        };

        $approved = Review::query()
            ->where('product_id', $product->id)
            ->where('status', 'APPROVED')
            ->get(['rating']);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'sku' => $product->sku,
            'description' => $product->short_description ?: $product->description,
            'image' => $product->images->map(fn ($image) => $image->publicUrl())->filter()->values()->all(),
            'brand' => $product->brand ? [
                '@type' => 'Brand',
                'name' => $product->brand->name,
            ] : null,
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => $pricing['currency'],
                'price' => $pricing['effective_price_amount'],
                'availability' => $availability,
                'url' => route('catalogue.show', $product->slug),
            ],
        ];

        if ($approved->isNotEmpty()) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float) $approved->avg('rating'), 1),
                'reviewCount' => $approved->count(),
            ];
        }

        return array_filter($schema, fn ($value) => $value !== null && $value !== []);
    }
}
