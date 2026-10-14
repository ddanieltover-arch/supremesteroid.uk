<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use App\Http\Resources\ProductCardResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CatalogueDiscoveryService
{
    /**
     * @return array{categories: list<array<string, mixed>>, brands: list<array<string, mixed>>, featured: list<array<string, mixed>>, new_arrivals: list<array<string, mixed>>}
     */
    public function navigation(): array
    {
        return Cache::remember('catalogue.navigation.v2', 300, function () {
            $categories = Category::query()
                ->where('is_visible', true)
                ->whereNull('parent_id')
                ->with(['children' => fn ($query) => $query->where('is_visible', true)->orderBy('display_order')])
                ->orderBy('display_order')
                ->get(['id', 'name', 'slug', 'parent_id', 'display_order']);

            $brands = Brand::query()
                ->where('is_active', true)
                ->orderBy('display_order')
                ->orderBy('name')
                ->limit(16)
                ->get(['id', 'name', 'slug', 'logo_url']);

            $featured = Product::query()
                ->purchasable()
                ->where('is_featured', true)
                ->with(['brand', 'category', 'images', 'inventory'])
                ->orderBy('sort_order')
                ->limit(4)
                ->get();

            $newDays = (int) config('store.catalogue.new_product_days', 14);
            $newArrivals = Product::query()
                ->purchasable()
                ->with(['brand', 'category', 'images', 'inventory'])
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->limit(4)
                ->get()
                ->filter(function (Product $product) use ($newDays) {
                    $stamp = $product->published_at ?? $product->created_at;

                    return $stamp && $stamp->gte(now()->subDays($newDays));
                })
                ->values();

            return [
                'categories' => $categories->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'children' => $category->children->map(fn (Category $child) => [
                        'id' => $child->id,
                        'name' => $child->name,
                        'slug' => $child->slug,
                    ])->values()->all(),
                ])->values()->all(),
                'brands' => $brands->map(fn (Brand $brand) => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                    'logo_url' => $brand->logo_url,
                ])->values()->all(),
                'featured' => ProductCardResource::collection($featured)->resolve(),
                'new_arrivals' => ProductCardResource::collection($newArrivals)->resolve(),
            ];
        });
    }

    /**
     * @param  list<string>  $slugs
     * @return Collection<int, Product>
     */
    public function purchasableBySlugs(array $slugs, int $limit = 12): Collection
    {
        $slugs = collect($slugs)
            ->filter(fn ($slug) => is_string($slug) && $slug !== '')
            ->unique()
            ->take($limit)
            ->values();

        if ($slugs->isEmpty()) {
            return collect();
        }

        $products = Product::query()
            ->purchasable()
            ->whereIn('slug', $slugs->all())
            ->with(['brand', 'category', 'images', 'inventory'])
            ->withCount(['variants as variant_count' => fn ($query) => $query->where('is_active', true)])
            ->get()
            ->keyBy('slug');

        return $slugs->map(fn (string $slug) => $products->get($slug))->filter()->values();
    }

    /**
     * @param  list<string>  $ids
     * @return Collection<int, Product>
     */
    public function purchasableByIds(array $ids, int $limit = 4): Collection
    {
        $ids = collect($ids)
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->unique()
            ->take($limit)
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->purchasable()
            ->whereIn('id', $ids->all())
            ->with(['brand', 'category', 'images', 'inventory', 'attributes', 'content'])
            ->withCount(['variants as variant_count' => fn ($query) => $query->where('is_active', true)])
            ->get()
            ->sortBy(fn (Product $product) => array_search($product->id, $ids->all(), true))
            ->values();
    }

    /**
     * @param  Builder<Product>|Collection<int, string>  $productIds
     * @return list<array{name: string, values: list<string>}>
     */
    public function attributeFacets(Builder|Collection $productIds): array
    {
        if ($productIds instanceof Collection && $productIds->isEmpty()) {
            return [];
        }

        return ProductAttribute::query()
            ->whereIn('product_id', $productIds)
            ->where('is_public', true)
            ->get(['attribute_name', 'attribute_value'])
            ->groupBy('attribute_name')
            ->map(fn (Collection $rows, string $name) => [
                'name' => $name,
                'values' => $rows->pluck('attribute_value')->filter()->unique()->sort()->values()->all(),
            ])
            ->filter(fn (array $facet) => $facet['values'] !== [])
            ->values()
            ->all();
    }
}
