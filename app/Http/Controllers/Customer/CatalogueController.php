<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Enums\ProductClassification;
use App\Http\Resources\ProductCardResource;
use App\Http\Resources\ProductDetailResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalogue\CatalogueDiscoveryService;
use App\Services\Catalogue\RelatedProductService;
use App\Services\Catalogue\ReviewService;
use App\Services\Catalogue\SeoService;
use App\Services\Catalogue\StockNotificationService;
use App\Services\Catalogue\StructuredDataService;
use App\Services\Shipping\ShippingEngine;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class CatalogueController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected SeoService $seoService,
        protected ShippingEngine $shippingEngine,
        protected RelatedProductService $relatedProducts,
        protected StructuredDataService $structuredData,
        protected StockNotificationService $stockNotifications,
        protected ReviewService $reviews,
        protected CatalogueDiscoveryService $discovery
    ) {}

    public function index(Request $request): Response
    {
        return $this->renderListing($request, 'Shop', route('shop.index'), null, 'shop');
    }

    public function search(Request $request): Response
    {
        $term = trim((string) ($request->query('q') ?? $request->query('search') ?? ''));
        $heading = $term !== '' ? 'Search results for “'.$term.'”' : 'Search';

        return $this->renderListing($request, $heading, route('search'), null, 'search');
    }

    public function category(Request $request, string $slug): Response
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->where('is_visible', true)
            ->with('parent')
            ->firstOrFail();

        $request->query->set('category', $category->slug);

        return $this->renderListing(
            $request,
            $category->name,
            route('category.show', $category->slug),
            $category,
            'category'
        );
    }

    public function brand(Request $request, string $slug): Response
    {
        $brand = Brand::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $request->query->set('brand', $brand->slug);

        return $this->renderListing(
            $request,
            $brand->name,
            route('brand.show', $brand->slug),
            $brand,
            'brand'
        );
    }

    public function show(Request $request, string $slug): Response
    {
        $product = Product::query()
            ->purchasable()
            ->with([
                'brand',
                'category.parent',
                'images',
                'attributes',
                'variants.inventory',
                'variants.attributes',
                'inventory',
                'seoMetadata',
                'content',
                'reviews.user',
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        $this->authorize('view', $product);

        $payload = (new ProductDetailResource($product))->resolve();
        $payload['shipping_methods'] = $this->shippingEngine->calculateRates('0.00', 'GB')->values();
        $payload['breadcrumbs'] = $this->productBreadcrumbs($product);
        $payload['structured_data'] = [
            $this->structuredData->forProduct($product),
            $this->structuredData->breadcrumbs($payload['breadcrumbs']),
        ];

        return Inertia::render('Catalogue/Show', [
            'product' => $payload,
            // Related rails are deferred so Instant Visits get the core product
            // immediately; related cards resolve after first paint.
            'related_groups' => Inertia::defer(function () use ($product) {
                $grouped = $this->relatedProducts->grouped($product, 4);

                return [
                    'related' => ProductCardResource::collection($grouped['related'])->resolve(),
                    'alternative' => ProductCardResource::collection($grouped['alternative'])->resolve(),
                    'complementary' => ProductCardResource::collection($grouped['complementary'])->resolve(),
                ];
            }),
            'wishlistProductIds' => $request->user()
                ? app(\App\Services\Account\WishlistService::class)->productIds($request->user())
                : [],
            'preview' => false,
            'seo' => $this->seoService->forPage(
                $request,
                $product->name,
                (string) ($product->short_description ?: $product->description ?: $product->name),
                [
                    'og_image' => $product->images->firstWhere('is_primary', true)?->publicUrl()
                        ?? $product->images->first()?->publicUrl(),
                ],
                $product
            ),
        ]);
    }

    /**
     * Admin-only storefront preview for draft/unpublished products.
     * Does not mutate cart, orders, or inventory.
     */
    public function preview(Request $request, string $slug): Response
    {
        $product = Product::query()
            ->with([
                'brand',
                'category.parent',
                'images',
                'attributes',
                'variants.inventory',
                'variants.attributes',
                'inventory',
                'seoMetadata',
                'content',
                'reviews.user',
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        $this->authorize('update', $product);

        $grouped = $this->relatedProducts->grouped($product);
        $related = $grouped['related']->merge($grouped['alternative'])->merge($grouped['complementary'])->unique('id')->values();

        $payload = (new ProductDetailResource($product))->resolve();
        $payload['is_purchasable'] = false;
        $payload['is_preview'] = true;
        $payload['related_products'] = ProductCardResource::collection($related)->resolve();
        $payload['related_groups'] = [
            'related' => ProductCardResource::collection($grouped['related'])->resolve(),
            'alternative' => ProductCardResource::collection($grouped['alternative'])->resolve(),
            'complementary' => ProductCardResource::collection($grouped['complementary'])->resolve(),
        ];
        $payload['shipping_methods'] = $this->shippingEngine->calculateRates('0.00', 'GB')->values();
        $payload['breadcrumbs'] = $this->productBreadcrumbs($product);
        $payload['structured_data'] = [
            $this->structuredData->forProduct($product),
            $this->structuredData->breadcrumbs($payload['breadcrumbs']),
        ];
        if (isset($payload['variants']) && is_array($payload['variants'])) {
            $payload['variants'] = array_map(function (array $variant): array {
                $variant['is_purchasable'] = false;

                return $variant;
            }, $payload['variants']);
        }

        return Inertia::render('Catalogue/Show', [
            'product' => $payload,
            'wishlistProductIds' => $request->user()
                ? app(\App\Services\Account\WishlistService::class)->productIds($request->user())
                : [],
            'preview' => true,
            'seo' => $this->seoService->forPage(
                $request,
                $product->name,
                (string) ($product->short_description ?: $product->description ?: $product->name),
                [
                    'robots' => 'noindex,nofollow',
                    'og_image' => $product->images->firstWhere('is_primary', true)?->publicUrl()
                        ?? $product->images->first()?->publicUrl(),
                ],
                $product
            ),
        ]);
    }

    public function suggest(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if (strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $products = Product::query()
            ->purchasable()
            ->searchable($term)
            ->with(['brand', 'category', 'images', 'inventory'])
            ->withCount(['variants as variant_count' => fn ($query) => $query->where('is_active', true)])
            ->limit(6)
            ->get();

        $brands = Brand::query()
            ->where('is_active', true)
            ->where('name', 'like', '%'.$term.'%')
            ->orderBy('name')
            ->limit(4)
            ->get(['id', 'name', 'slug']);

        $categories = Category::query()
            ->where('is_visible', true)
            ->where('name', 'like', '%'.$term.'%')
            ->orderBy('name')
            ->limit(4)
            ->get(['id', 'name', 'slug']);

        $productPayload = $products->map(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'url' => route('catalogue.show', $product->slug),
            'brand' => $product->brand?->name,
            'image_url' => $product->images->firstWhere('is_primary', true)?->publicUrl()
                ?? $product->images->first()?->publicUrl(),
            'pricing' => (new ProductCardResource($product))->resolve()['pricing'] ?? null,
        ]);

        return response()->json([
            'results' => $productPayload,
            'products' => $productPayload,
            'brands' => $brands->map(fn (Brand $brand) => [
                'id' => $brand->id,
                'name' => $brand->name,
                'url' => route('brand.show', $brand->slug),
            ]),
            'categories' => $categories->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'url' => route('category.show', $category->slug),
            ]),
            'view_all' => route('search', ['q' => $term]),
        ]);
    }

    public function quickView(string $slug): JsonResponse
    {
        $product = Product::query()
            ->purchasable()
            ->with([
                'brand',
                'category',
                'images',
                'attributes',
                'variants.inventory',
                'variants.attributes',
                'inventory',
                'content',
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        $this->authorize('view', $product);

        return response()->json([
            'product' => (new ProductDetailResource($product))->resolve(),
        ]);
    }

    public function cards(Request $request): JsonResponse
    {
        $slugs = $request->query('slugs', []);
        if (is_string($slugs)) {
            $slugs = array_filter(explode(',', $slugs));
        }

        $products = $this->discovery->purchasableBySlugs(is_array($slugs) ? $slugs : []);

        return response()->json([
            'products' => ProductCardResource::collection($products)->resolve(),
        ]);
    }

    public function navigationHighlights(): JsonResponse
    {
        return response()->json($this->discovery->navigationHighlights());
    }

    public function compare(Request $request): Response
    {
        $ids = $request->query('ids', []);
        if (is_string($ids)) {
            $ids = array_filter(explode(',', $ids));
        }

        $products = $this->discovery->purchasableByIds(is_array($ids) ? $ids : []);

        return Inertia::render('Catalogue/Compare', [
            'products' => $products->map(fn (Product $product) => (new ProductDetailResource($product))->resolve())->values(),
            'seo' => $this->seoService->forPage(
                $request,
                'Compare products',
                'Compare approved catalogue items using current server pricing and availability.',
                ['robots' => 'noindex,nofollow']
            ),
        ]);
    }

    public function notify(Request $request, string $slug): RedirectResponse
    {
        $product = Product::query()->purchasable()->where('slug', $slug)->firstOrFail();
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'product_variant_id' => ['nullable', 'string', 'exists:product_variants,id'],
        ]);

        $this->stockNotifications->subscribe(
            $product,
            $validated['email'],
            $request->user(),
            $validated['product_variant_id'] ?? null
        );

        return back()->with('success', 'We will email that address if this item becomes available.');
    }

    public function storeReview(Request $request, string $slug): RedirectResponse
    {
        $product = Product::query()->purchasable()->where('slug', $slug)->firstOrFail();
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->reviews->submit(
            $product,
            $request->user(),
            (int) $validated['rating'],
            $validated['title'] ?? null,
            $validated['body'] ?? null
        );

        return back()->with('success', 'Your review was submitted and is awaiting moderation.');
    }

    protected function renderListing(Request $request, string $heading, string $canonical, ?object $context = null, string $listingKind = 'shop'): Response
    {
        $categorySlug = $request->query('category');
        $brandSlug = $request->query('brand');
        $search = $request->query('search') ?? $request->query('q');
        $sort = is_string($request->query('sort')) ? $request->query('sort') : 'featured';
        $availability = $request->query('availability');
        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');
        $collection = $request->query('collection');
        $classification = $request->query('classification');
        $attributes = $request->query('attributes');

        $query = Product::query()
            ->purchasable()
            ->with(['brand', 'category', 'images', 'inventory'])
            ->withCount(['variants as variant_count' => fn ($variantQuery) => $variantQuery->where('is_active', true)]);

        if (is_string($categorySlug) && $categorySlug !== '') {
            $query->byCategory($categorySlug);
        }

        if (is_string($brandSlug) && $brandSlug !== '') {
            $query->byBrand($brandSlug);
        }

        if (is_string($search) && $search !== '') {
            $query->searchable($search);
        }

        if ($availability === 'in_stock') {
            $query->inStock();
        }

        if (is_numeric($minPrice)) {
            $query->where('price_amount', '>=', $minPrice);
        }

        if (is_numeric($maxPrice)) {
            $query->where('price_amount', '<=', $maxPrice);
        }

        if ($collection === 'featured') {
            $query->where('is_featured', true);
        }

        if ($collection === 'new') {
            $query->where(function ($builder) {
                $since = now()->subDays((int) config('store.catalogue.new_product_days', 14));
                $builder->where('published_at', '>=', $since)
                    ->orWhere(function ($inner) use ($since) {
                        $inner->whereNull('published_at')->where('created_at', '>=', $since);
                    });
            });
        }

        if ($collection === 'sale') {
            $query->whereNotNull('compare_at_price_amount')
                ->whereColumn('compare_at_price_amount', '>', 'price_amount');
        }

        $allowedClassifications = array_map(fn (ProductClassification $item) => $item->value, ProductClassification::approvedForRetail());
        if (is_string($classification) && in_array($classification, $allowedClassifications, true)) {
            $query->where('classification', $classification);
        } else {
            $classification = null;
        }

        if (is_array($attributes)) {
            foreach ($attributes as $name => $value) {
                if (! is_string($name) || ! is_string($value) || $value === '') {
                    continue;
                }
                $query->whereHas('attributes', function ($attributeQuery) use ($name, $value) {
                    $attributeQuery->where('attribute_name', $name)
                        ->where('attribute_value', $value)
                        ->where('is_public', true);
                });
            }
        }

        // Always keep out-of-stock / unavailable cards after in-stock ones.
        $query->orderAvailableFirst();

        match ($sort) {
            'price_asc' => $query->orderBy('price_amount'),
            'price_desc' => $query->orderByDesc('price_amount'),
            'newest' => $query->orderByDesc('published_at')->orderByDesc('created_at'),
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            'availability' => $query->orderByDesc(
                \App\Models\Inventory::query()
                    ->selectRaw('quantity_on_hand - quantity_reserved')
                    ->whereColumn('inventory.product_id', 'products.id')
                    ->limit(1)
            ),
            default => $query->orderByDesc('is_featured')->orderBy('sort_order')->orderByDesc('created_at'),
        };

        $facetQuery = (clone $query)->reorder();
        $visibleCategoryIds = (clone $facetQuery)->distinct()->pluck('category_id')->filter();
        $visibleBrandIds = (clone $facetQuery)->distinct()->pluck('brand_id')->filter();

        $perPage = (int) config('store.catalogue.products_per_page', 30);
        $products = $query->paginate($perPage)->withQueryString()
            ->through(fn (Product $product) => (new ProductCardResource($product))->resolve());

        $allCategories = collect(Cache::remember('catalogue.categories.visible.v2', 300, fn () => Category::query()
            ->where('is_visible', true)
            ->orderBy('display_order')
            ->get(['id', 'name', 'slug', 'parent_id', 'description', 'image_url'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_id' => $category->parent_id,
                'description' => $category->description,
                'image_url' => $category->image_url,
            ])->values()->all()));

        $allBrands = collect(Cache::remember('catalogue.brands.active.v2', 300, fn () => Brand::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'logo_url'])
            ->map(fn (Brand $brand) => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'description' => $brand->description,
                'logo_url' => $brand->logo_url,
            ])->values()->all()));

        $categories = $allCategories->whereIn('id', $visibleCategoryIds)->values();
        $brands = $allBrands->whereIn('id', $visibleBrandIds)->values();

        $description = is_object($context) && isset($context->description) && $context->description
            ? (string) $context->description
            : ($listingKind === 'shop'
                ? 'Shop premium steroids, SARMs, peptides, and PCT products at Supreme Steroids. High-quality brands, discreet UK shipping, and trusted service you can rely on.'
                : 'Lawful sports nutrition, wellness, and certified research formulations.');

        $relatedSearches = [];
        if ($products->total() === 0 && is_string($search) && $search !== '') {
            $relatedSearches = $allCategories
                ->filter(fn ($item) => str_contains(strtolower((string) $item['name']), strtolower($search)))
                ->take(5)
                ->map(fn ($item) => [
                    'name' => $item['name'],
                    'url' => route('category.show', $item['slug']),
                ])
                ->values()
                ->all();
        }

        $subcategories = [];
        if ($context instanceof Category) {
            $subcategories = $context->children()
                ->where('is_visible', true)
                ->orderBy('display_order')
                ->get(['id', 'name', 'slug'])
                ->map(fn (Category $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                ])
                ->values()
                ->all();
        }

        $breadcrumbs = $this->listingBreadcrumbs($listingKind, $context, $heading);
        $attributeFacets = $this->discovery->attributeFacets((clone $facetQuery)->select('products.id'));

        return Inertia::render('Catalogue/Index', [
            'heading' => $heading,
            'listing_kind' => $listingKind,
            'listing_path' => $request->path() === '/' ? '/shop' : '/'.$request->path(),
            'result_count' => $products->total(),
            'breadcrumbs' => $breadcrumbs,
            'structured_data' => [$this->structuredData->breadcrumbs($breadcrumbs)],
            'context' => is_object($context) ? [
                'type' => $context instanceof Brand ? 'brand' : ($context instanceof Category ? 'category' : 'page'),
                'name' => $context->name ?? $heading,
                'description' => $context->description ?? null,
                'image_url' => $context->image_url ?? $context->logo_url ?? null,
                'product_count' => $products->total(),
            ] : null,
            'subcategories' => $subcategories,
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'attribute_facets' => $attributeFacets,
            'related_searches' => $relatedSearches,
            'filters' => [
                'category' => is_string($categorySlug) ? $categorySlug : null,
                'brand' => is_string($brandSlug) ? $brandSlug : null,
                'search' => is_string($search) ? $search : null,
                'q' => is_string($search) ? $search : null,
                'sort' => $sort,
                'availability' => is_string($availability) ? $availability : null,
                'min_price' => is_numeric($minPrice) ? (string) $minPrice : null,
                'max_price' => is_numeric($maxPrice) ? (string) $maxPrice : null,
                'collection' => is_string($collection) ? $collection : null,
                'classification' => is_string($classification) ? $classification : null,
                'attributes' => is_array($attributes) ? $attributes : [],
            ],
            'seo' => $this->seoService->forPage(
                $request,
                $listingKind === 'shop'
                    ? 'Shop Premium Steroids, SARMs & PCT – '.config('store.name', 'Supreme Steroids')
                    : $heading.' | '.config('store.name', 'Supreme Steroids'),
                (string) $description,
                [
                    'canonical' => $canonical,
                    'robots' => $listingKind === 'search' ? 'noindex,nofollow' : 'index,follow',
                ],
                $context instanceof \Illuminate\Database\Eloquent\Model ? $context : null
            ),
        ]);
    }

    /**
     * @return list<array{name: string, url: string}>
     */
    protected function productBreadcrumbs(Product $product): array
    {
        $crumbs = [
            ['name' => 'Home', 'url' => route('home')],
        ];

        if ($product->category?->parent && $product->category->parent->is_visible) {
            $crumbs[] = [
                'name' => $product->category->parent->name,
                'url' => route('category.show', $product->category->parent->slug),
            ];
        }

        if ($product->category) {
            $crumbs[] = [
                'name' => $product->category->name,
                'url' => route('category.show', $product->category->slug),
            ];
        } elseif ($product->brand) {
            $crumbs[] = [
                'name' => $product->brand->name,
                'url' => route('brand.show', $product->brand->slug),
            ];
        } else {
            $crumbs[] = ['name' => 'Shop', 'url' => route('shop.index')];
        }

        $crumbs[] = ['name' => $product->name, 'url' => route('catalogue.show', $product->slug)];

        return $crumbs;
    }

    /**
     * @return list<array{name: string, url: string}>
     */
    protected function listingBreadcrumbs(string $kind, ?object $context, string $heading): array
    {
        $crumbs = [
            ['name' => 'Home', 'url' => route('home')],
        ];

        if ($kind === 'category' && $context instanceof Category) {
            if ($context->parent && $context->parent->is_visible) {
                $crumbs[] = [
                    'name' => $context->parent->name,
                    'url' => route('category.show', $context->parent->slug),
                ];
            }
            $crumbs[] = ['name' => $context->name, 'url' => route('category.show', $context->slug)];

            return $crumbs;
        }

        if ($kind === 'brand' && $context instanceof Brand) {
            $crumbs[] = ['name' => $context->name, 'url' => route('brand.show', $context->slug)];

            return $crumbs;
        }

        $crumbs[] = ['name' => $heading, 'url' => $kind === 'search' ? route('search') : route('shop.index')];

        return $crumbs;
    }
}
