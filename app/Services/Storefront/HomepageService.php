<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Enums\ProductClassification;
use App\Http\Resources\ProductCardResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HomepageContent;
use App\Models\HomepageFaq;
use App\Models\Page;
use App\Models\Product;
use App\Models\Review;
use App\Services\Catalogue\CataloguePresentation;
use App\Services\Catalogue\SeoService;
use App\Services\Catalogue\StructuredDataService;
use App\Services\Shipping\ShippingEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class HomepageService
{
    public const CACHE_PAGE = 'homepage.page.v4';

    public const CACHE_CHROME = 'homepage.chrome.v4';

    public function __construct(
        protected ShippingEngine $shipping,
        protected SeoService $seoService,
        protected StructuredDataService $structuredData
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function page(Request $request): array
    {
        if (app()->environment('testing')) {
            return $this->composePage($request);
        }

        return Cache::remember(self::CACHE_PAGE, 300, fn () => $this->composePage($request));
    }

    /**
     * @return array<string, mixed>
     */
    protected function composePage(Request $request): array
    {
            $content = $this->content();
            $defaults = config('homepage', []);
            $base = $this->productQuery();

            $featured = (clone $base)->where('is_featured', true)->orderBy('sort_order')->orderByDesc('created_at')->limit(8)->get();
            $newDays = (int) config('store.catalogue.new_product_days', 14);
            $since = now()->subDays($newDays);
            $newProducts = (clone $base)
                ->where(function ($builder) use ($since) {
                    $builder->where('published_at', '>=', $since)
                        ->orWhere(function ($inner) use ($since) {
                            $inner->whereNull('published_at')->where('created_at', '>=', $since);
                        });
                })
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get();
            $saleProducts = (clone $base)
                ->whereNotNull('compare_at_price_amount')
                ->whereColumn('compare_at_price_amount', '>', 'price_amount')
                ->orderBy('sort_order')
                ->limit(8)
                ->get();

            $heroProducts = $featured->take(3);
            if ($heroProducts->isEmpty()) {
                $heroProducts = $newProducts->take(3);
            }

            $categories = Category::query()
                ->where('is_visible', true)
                ->whereNull('parent_id')
                ->withCount(['products as product_count' => fn ($query) => $query->purchasable()])
                ->orderByDesc('is_featured')
                ->orderBy('display_order')
                ->orderBy('name')
                ->get()
                ->filter(fn (Category $category) => (int) $category->product_count > 0)
                ->take(8)
                ->values();

            $categoryThumbnails = $this->categoryProductThumbnails($categories);

            $categories = $categories
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'image_url' => $this->safeImageUrl($category->image_url)
                        ?? ($categoryThumbnails[$category->id] ?? null),
                    'product_count' => (int) $category->product_count,
                    'url' => route('category.show', $category->slug),
                ])
                ->values()
                ->all();

            $brands = Brand::query()
                ->where('is_active', true)
                ->withCount(['products as product_count' => fn ($query) => $query->purchasable()])
                ->orderBy('display_order')
                ->orderBy('name')
                ->get()
                ->filter(fn (Brand $brand) => (int) $brand->product_count > 0)
                ->take(8)
                ->map(fn (Brand $brand) => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                    'description' => $brand->description,
                    'logo_url' => $brand->logo_url,
                    'product_count' => (int) $brand->product_count,
                    'url' => route('brand.show', $brand->slug),
                ])
                ->values()
                ->all();

            $intent = $this->intentGroups($featured->isNotEmpty(), $newProducts->isNotEmpty(), $saleProducts->isNotEmpty());

            $reviews = Review::query()
                ->where('status', \App\Enums\ReviewStatus::APPROVED)
                ->with(['user', 'product' => fn ($query) => $query->purchasable()])
                ->latest()
                ->limit(3)
                ->get()
                ->filter(fn (Review $review) => $review->product !== null)
                ->map(fn (Review $review) => [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'title' => $review->title,
                    'body' => $review->body,
                    'display_name' => $review->user?->name,
                    'product_name' => $review->product?->name,
                    'product_url' => $review->product ? route('catalogue.show', $review->product->slug) : null,
                    'verified_purchase' => (bool) $review->is_verified_purchase,
                ])
                ->values()
                ->all();

            $heroDefaults = $defaults['hero'] ?? [];
            $editorialDefaults = $defaults['editorial'] ?? [];
            $seoTitle = $this->safeText($content->seo_title, (string) ($defaults['seo']['title'] ?? 'Supreme Steroids | Premium Product Collection'), 80);
            $seoDescription = $this->safeText($content->seo_description, (string) ($defaults['seo']['description'] ?? ''), 160);

            return [
                'homepage' => [
                    'announcement' => $this->announcement($content),
                    'hero' => [
                        'eyebrow' => $this->safeText($content->hero_eyebrow, (string) ($heroDefaults['eyebrow'] ?? '')),
                        'headline' => $this->safeText($content->hero_headline, (string) ($heroDefaults['headline'] ?? '')),
                        'description' => $this->safeText($content->hero_description, (string) ($heroDefaults['description'] ?? ''), 400),
                        'primary_cta' => [
                            'label' => $this->safeText($content->hero_primary_label, (string) ($heroDefaults['primary_cta']['label'] ?? 'Explore Collection')),
                            'url' => $this->safeUrl($content->hero_primary_url, (string) ($heroDefaults['primary_cta']['url'] ?? '/shop')),
                        ],
                        'secondary_cta' => [
                            'label' => $this->safeText($content->hero_secondary_label, (string) ($heroDefaults['secondary_cta']['label'] ?? 'Shop Categories')),
                            'url' => $this->safeUrl($content->hero_secondary_url, (string) ($heroDefaults['secondary_cta']['url'] ?? '/#categories')),
                        ],
                        'products' => ProductCardResource::collection($heroProducts)->resolve(),
                    ],
                    'categories' => $categories,
                    'featuredProducts' => ProductCardResource::collection($featured)->resolve(),
                    'newProducts' => ProductCardResource::collection($newProducts)->resolve(),
                    'saleProducts' => ProductCardResource::collection($saleProducts)->resolve(),
                    'brands' => $brands,
                    'intent' => $intent,
                    'editorial' => $content->editorial_enabled ? [
                        'label' => $this->safeText($content->editorial_label, (string) ($editorialDefaults['label'] ?? '')),
                        'heading' => $this->safeText($content->editorial_heading, (string) ($editorialDefaults['heading'] ?? '')),
                        'body' => $this->safeText($content->editorial_body, (string) ($editorialDefaults['body'] ?? ''), 600),
                        'cta' => [
                            'label' => $this->safeText($content->editorial_cta_label, (string) ($editorialDefaults['cta']['label'] ?? 'View the catalogue')),
                            'url' => $this->safeUrl($content->editorial_cta_url, (string) ($editorialDefaults['cta']['url'] ?? '/shop')),
                        ],
                        'image_url' => $this->safeImageUrl($content->editorial_image_url ?: ($editorialDefaults['image_url'] ?? null)),
                    ] : null,
                    'trustItems' => $defaults['trust_items'] ?? [],
                    'shipping' => $this->shipping->publicOverview(),
                    'faqs' => HomepageFaq::query()->published()->get(['id', 'question', 'answer'])->all(),
                    'reviews' => $reviews,
                    'newsletter' => $content->newsletter_enabled ? [
                        'heading' => $this->safeText($content->newsletter_heading, (string) ($defaults['newsletter']['heading'] ?? '')),
                        'body' => $this->safeText($content->newsletter_body, (string) ($defaults['newsletter']['body'] ?? ''), 300),
                    ] : null,
                    'sections' => $this->sections($content),
                ],
                'featuredProducts' => ProductCardResource::collection($featured)->resolve(),
                'structured_data' => [
                    $this->structuredData->organization(),
                    $this->structuredData->website(),
                ],
                'seo' => $this->seoService->forPage($request, $seoTitle, $seoDescription, [
                    'canonical' => route('home'),
                    'og_image' => $heroProducts->first()?->images->firstWhere('is_primary', true)?->publicUrl()
                        ?? $heroProducts->first()?->images->first()?->publicUrl(),
                ]),
            ];
    }

    /**
     * @return array<string, mixed>
     */
    public function chrome(): array
    {
        if (app()->environment('testing')) {
            return $this->composeChrome();
        }

        return Cache::remember(self::CACHE_CHROME, 120, fn () => $this->composeChrome());
    }

    /**
     * @return array<string, mixed>
     */
    protected function composeChrome(): array
    {
            $content = $this->content();
            $this->ensurePolicyPages();

            return [
                'announcement' => $this->announcement($content),
                'policies' => $this->policies(),
                'social' => $this->social($content),
                'address' => config('store.business_address', []),
                'support_phone' => config('store.support_phone'),
            ];
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_PAGE);
        Cache::forget(self::CACHE_CHROME);
    }

    public function content(): HomepageContent
    {
        return HomepageContent::query()->first() ?? HomepageContent::query()->create([]);
    }

    /**
     * @return list<array{key: string, enabled: bool, position: int}>
     */
    protected function sections(HomepageContent $content): array
    {
        $defaults = collect(config('homepage.sections', []))->keyBy('key');
        $stored = collect($content->section_order ?? []);

        $merged = $defaults->map(function (array $default, string $key) use ($stored) {
            $row = $stored->firstWhere('key', $key) ?? $default;

            return [
                'key' => $key,
                'enabled' => (bool) ($row['enabled'] ?? $default['enabled'] ?? true),
                'position' => (int) ($row['position'] ?? $default['position'] ?? 100),
            ];
        });

        return $merged->sortBy('position')->values()->all();
    }

    /**
     * @return array{enabled: bool, message: string}|null
     */
    protected function announcement(HomepageContent $content): ?array
    {
        if (! $content->announcement_enabled) {
            return null;
        }

        $message = $this->safeText($content->announcement_message, '');
        if ($message === '' && $content->announcement_use_shipping_threshold) {
            $overview = $this->shipping->publicOverview();
            $threshold = $overview['free_shipping_threshold'] ?? null;
            if (! $threshold || (float) $threshold <= 0) {
                return null;
            }
            $message = 'Free UK delivery on eligible orders over £'.number_format((float) $threshold, 0);
        }

        if ($message === '') {
            return null;
        }

        return [
            'enabled' => true,
            'dismissible' => (bool) config('homepage.announcement.dismissible', true),
            'signature' => hash('sha256', $message),
            'message' => $message,
        ];
    }

    /**
     * @return list<array{key: string, label: string, url: string}>
     */
    protected function intentGroups(bool $hasFeatured, bool $hasNew, bool $hasSale): array
    {
        $groups = [];
        if ($hasFeatured) {
            $groups[] = ['key' => 'featured', 'label' => 'Featured', 'url' => '/shop?collection=featured'];
        }
        if ($hasNew) {
            $groups[] = ['key' => 'new', 'label' => 'New Arrivals', 'url' => '/shop?collection=new'];
        }
        if ($hasSale) {
            $groups[] = ['key' => 'offers', 'label' => 'Selected Offers', 'url' => '/shop?collection=sale'];
        }

        $hasConsumer = Product::query()->purchasable()->where('classification', ProductClassification::OTC_CONSUMER)->exists();
        $hasResearch = Product::query()->purchasable()->where('classification', ProductClassification::RESEARCH_USE)->exists();

        if ($hasConsumer) {
            $groups[] = ['key' => 'consumer', 'label' => 'Consumer Collection', 'url' => '/shop?classification=OTC_CONSUMER'];
        }
        if ($hasResearch) {
            $groups[] = ['key' => 'research', 'label' => 'Research Collection', 'url' => '/shop?classification=RESEARCH_USE'];
        }

        return $groups;
    }

    /**
     * @return list<array{title: string, slug: string, url: string}>
     */
    protected function policies(): array
    {
        return collect(config('homepage.policy_pages', []))->map(fn (array $page) => [
            'title' => $page['title'],
            'slug' => $page['slug'],
            'url' => '/'.$page['slug'],
        ])->values()->all();
    }

    /**
     * @return array<string, string>
     */
    protected function social(HomepageContent $content): array
    {
        $configured = array_filter(config('homepage.social', []));
        $stored = array_filter($content->social_links ?? []);
        $merged = array_merge($configured, $stored);

        return collect($merged)
            ->filter(fn ($url) => is_string($url) && $this->safeImageUrl($url) !== null)
            ->map(fn ($url) => $this->safeUrl((string) $url, ''))
            ->filter()
            ->all();
    }

    protected function ensurePolicyPages(): void
    {
        foreach (config('homepage.policy_pages', []) as $page) {
            Page::query()->firstOrCreate(
                ['slug' => $page['slug']],
                [
                    'title' => $page['title'],
                    'content' => null,
                    'is_published' => false,
                ]
            );
        }
    }

    protected function productQuery()
    {
        return app(CataloguePresentation::class)
            ->constrainToUsableImages(Product::query()->purchasable())
            ->with(['brand', 'category', 'images', 'inventory'])
            ->withCount(['variants as variant_count' => fn ($query) => $query->where('is_active', true)]);
    }

    /**
     * Pick one catalogue product image per category for collection cards.
     * Prefer durable local images, then fall back to any product image URL in the collection.
     *
     * @param  Collection<int, Category>  $categories
     * @return array<string, string>
     */
    protected function categoryProductThumbnails(Collection $categories): array
    {
        $missing = $categories
            ->filter(fn (Category $category) => $this->safeImageUrl($category->image_url) === null)
            ->values();

        if ($missing->isEmpty()) {
            return [];
        }

        $categoryIds = $missing->pluck('id')->all();
        $childIdsByParent = Category::query()
            ->whereIn('parent_id', $categoryIds)
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id')
            ->map(fn (Collection $rows) => $rows->pluck('id')->all());

        $lookupIds = collect($categoryIds)
            ->merge($childIdsByParent->flatten())
            ->unique()
            ->values()
            ->all();

        $presentation = app(CataloguePresentation::class);
        $products = Product::query()
            ->purchasable()
            ->where(function ($query) use ($lookupIds, $categoryIds): void {
                $query->whereIn('category_id', $lookupIds)
                    ->orWhereHas('categories', fn ($categories) => $categories->whereIn('categories.id', $categoryIds));
            })
            ->whereHas('images', fn ($query) => $query->whereNotNull('url')->where('url', '!=', ''))
            ->with([
                'images' => fn ($query) => $query
                    ->whereNotNull('url')
                    ->where('url', '!=', '')
                    ->orderByDesc('is_primary')
                    ->orderBy('display_order'),
                'categories:id',
            ])
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->get();

        $parentByChild = [];
        foreach ($childIdsByParent as $parentId => $childIds) {
            foreach ($childIds as $childId) {
                $parentByChild[(string) $childId] = (string) $parentId;
            }
        }

        $thumbnails = [];
        $fallback = [];

        foreach ($products as $product) {
            $candidateCategoryIds = collect([(string) $product->category_id])
                ->merge($product->categories->pluck('id')->map(fn ($id) => (string) $id))
                ->filter()
                ->map(fn (string $id) => $parentByChild[$id] ?? $id)
                ->filter(fn (string $id) => in_array($id, array_map('strval', $categoryIds), true))
                ->unique()
                ->values();

            $image = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
            $url = $this->safeImageUrl($image?->publicUrl() ?? $image?->url);
            if ($url === null) {
                continue;
            }

            $usable = $presentation->usableImageUrl($url);

            foreach ($candidateCategoryIds as $categoryId) {
                if ($usable !== null && ! isset($thumbnails[$categoryId])) {
                    $thumbnails[$categoryId] = $usable;
                }
                if (! isset($fallback[$categoryId])) {
                    $fallback[$categoryId] = $url;
                }
            }
        }

        foreach ($fallback as $categoryId => $url) {
            $thumbnails[$categoryId] ??= $url;
        }

        return $thumbnails;
    }

    protected function safeText(?string $value, string $fallback, int $max = 180): string
    {
        $text = trim(strip_tags((string) ($value ?: $fallback)));

        return Str::limit($text, $max, '');
    }

    protected function safeUrl(?string $url, string $fallback): string
    {
        $candidate = trim((string) ($url ?: $fallback));
        if ($candidate === '') {
            return $fallback;
        }
        if (str_starts_with($candidate, '/')) {
            return $candidate;
        }
        if (filter_var($candidate, FILTER_VALIDATE_URL)) {
            $scheme = parse_url($candidate, PHP_URL_SCHEME);

            return in_array($scheme, ['http', 'https'], true) ? $candidate : $fallback;
        }

        return $fallback;
    }

    protected function safeImageUrl(mixed $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $candidate = $this->encodeImageUrl(trim($url));
        $safe = $this->safeUrl($candidate, '');

        return $safe !== '' ? $safe : null;
    }

    /**
     * Product CDN paths often contain spaces; encode them so validation and browsers accept the URL.
     */
    protected function encodeImageUrl(string $url): string
    {
        return \App\Services\Catalogue\CataloguePresentation::encodeImageUrl($url);
    }
}
