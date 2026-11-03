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

        $productUrl = route('catalogue.show', $product->slug);

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
                'url' => $productUrl,
                'priceCurrency' => $pricing['currency'],
                'price' => $pricing['effective_price_amount'],
                'priceValidUntil' => now()->addYear()->toDateString(),
                'availability' => $availability,
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => [
                    '@type' => 'Organization',
                    'name' => config('store.legal_name', config('store.name')),
                    'url' => config('store.domain'),
                ],
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

    /**
     * @return array<string, mixed>
     */
    public function forBlogPost(
        string $title,
        string $description,
        string $url,
        ?string $image,
        string $authorName,
        ?\DateTimeInterface $datePublished,
        ?\DateTimeInterface $dateModified = null,
    ): array {
        $publisherLogo = (string) config('store.logo_url', '');
        if ($publisherLogo !== '' && ! preg_match('#^https?://#i', $publisherLogo)) {
            $publisherLogo = rtrim((string) config('store.domain', config('app.url')), '/').'/'.ltrim($publisherLogo, '/');
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $title,
            'description' => $description,
            'image' => $image ? [$image] : null,
            'author' => [
                '@type' => 'Person',
                'name' => $authorName !== '' ? $authorName : 'Supreme Editorial Team',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => config('store.legal_name', config('store.name')),
                'logo' => $publisherLogo !== '' ? [
                    '@type' => 'ImageObject',
                    'url' => $publisherLogo,
                ] : null,
            ],
            'datePublished' => $datePublished?->format(DATE_ATOM),
            'dateModified' => ($dateModified ?? $datePublished)?->format(DATE_ATOM),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $url,
            ],
        ], fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @param  list<array{question: string, answer: string}|object>  $faqs
     * @return array<string, mixed>|null
     */
    public function faqPage(array $faqs): ?array
    {
        $entities = [];
        foreach ($faqs as $faq) {
            $question = is_array($faq) ? (string) ($faq['question'] ?? '') : (string) ($faq->question ?? '');
            $answer = is_array($faq) ? (string) ($faq['answer'] ?? '') : (string) ($faq->answer ?? '');
            if (trim($question) === '' || trim($answer) === '') {
                continue;
            }
            $entities[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        }

        if ($entities === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }
}
