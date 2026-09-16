<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Resources\ProductCardResource;
use App\Http\Resources\ProductDetailResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalogue\SeoService;
use App\Services\Inventory\InventoryReservationService;
use App\Services\Shipping\ShippingEngine;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CatalogueController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected SeoService $seoService,
        protected ShippingEngine $shippingEngine,
        protected InventoryReservationService $reservations
    ) {}

    public function index(Request $request): Response
    {
        return $this->renderListing($request, 'Shop', route('shop.index'));
    }

    public function search(Request $request): Response
    {
        return $this->renderListing($request, 'Search', route('search'));
    }

    public function category(Request $request, string $slug): Response
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->where('is_visible', true)
            ->firstOrFail();

        $request->query->set('category', $category->slug);

        return $this->renderListing(
            $request,
            $category->name,
            route('category.show', $category->slug),
            $category
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
            $brand
        );
    }

    public function show(Request $request, string $slug): Response
    {
        $this->reservations->releaseExpiredReservations();

        $product = Product::query()
            ->purchasable()
            ->with(['brand', 'category', 'images', 'attributes', 'variants.inventory', 'inventory', 'seoMetadata'])
            ->where('slug', $slug)
            ->firstOrFail();

        $this->authorize('view', $product);

        $related = Product::query()
            ->purchasable()
            ->with(['brand', 'category', 'images', 'inventory'])
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($query) => $query->where('category_id', $product->category_id))
            ->limit(4)
            ->get();

        $payload = (new ProductDetailResource($product))->resolve();
        $payload['related_products'] = ProductCardResource::collection($related)->resolve();
        $payload['shipping_methods'] = $this->shippingEngine->calculateRates('0.00', 'GB')->values();

        return Inertia::render('Catalogue/Show', [
            'product' => $payload,
            'seo' => $this->seoService->forPage(
                $request,
                $product->name,
                (string) ($product->short_description ?: $product->description ?: $product->name),
                [
                    'og_image' => $product->images->firstWhere('is_primary', true)?->url
                        ?? $product->images->first()?->url,
                ],
                $product
            ),
        ]);
    }

    protected function renderListing(Request $request, string $heading, string $canonical, ?object $context = null): Response
    {
        $categorySlug = $request->query('category');
        $brandSlug = $request->query('brand');
        $search = $request->query('search') ?? $request->query('q');
        $sort = is_string($request->query('sort')) ? $request->query('sort') : 'featured';
        $availability = $request->query('availability');
        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');

        $this->reservations->releaseExpiredReservations();

        $query = Product::query()
            ->purchasable()
            ->with(['brand', 'category', 'images', 'inventory']);

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

        match ($sort) {
            'price_asc' => $query->orderBy('price_amount'),
            'price_desc' => $query->orderByDesc('price_amount'),
            'newest' => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('is_featured')->orderByDesc('created_at'),
        };

        $products = $query->paginate(12)->withQueryString()
            ->through(fn (Product $product) => (new ProductCardResource($product))->resolve());

        $categories = Category::query()
            ->where('is_visible', true)
            ->orderBy('display_order')
            ->get(['id', 'name', 'slug']);

        $brands = Brand::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $description = is_object($context) && isset($context->description) && $context->description
            ? (string) $context->description
            : 'Lawful sports nutrition, wellness, and certified research formulations.';

        return Inertia::render('Catalogue/Index', [
            'heading' => $heading,
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'filters' => [
                'category' => is_string($categorySlug) ? $categorySlug : null,
                'brand' => is_string($brandSlug) ? $brandSlug : null,
                'search' => is_string($search) ? $search : null,
                'sort' => $sort,
                'availability' => is_string($availability) ? $availability : null,
                'min_price' => is_numeric($minPrice) ? (string) $minPrice : null,
                'max_price' => is_numeric($maxPrice) ? (string) $maxPrice : null,
            ],
            'seo' => $this->seoService->forPage(
                $request,
                $heading.' | '.config('store.name', 'Supreme Steroids'),
                (string) $description,
                ['canonical' => $canonical],
                $context
            ),
        ]);
    }
}
