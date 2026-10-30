<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ReviewStatus;
use App\Models\ProductAttribute;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class CatalogueDiscoveryExperienceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    public function test_out_of_stock_products_sort_after_in_stock_on_shop(): void
    {
        $inStock = $this->makeProduct([
            'name' => 'AAA In Stock Product',
            'sku' => 'SORT-IN-1',
            'is_featured' => true,
            'sort_order' => 99,
        ], 12);
        \App\Models\ProductImage::query()->create([
            'product_id' => $inStock->id,
            'url' => 'https://cdn.example.test/in-stock.webp',
            'alt_text' => 'In stock',
            'display_order' => 1,
            'is_primary' => true,
        ]);

        // Zero stock and no usable image — still ACTIVE so it remains on the catalogue.
        $outOfStock = $this->makeProduct([
            'name' => 'Should Appear Last Out Of Stock',
            'sku' => 'SORT-OUT-1',
            'is_featured' => true,
            'sort_order' => 1,
        ], 0);

        $this->get('/shop?sort=featured')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalogue/Index')
                ->where('products.data.0.id', $inStock->id)
                ->where('products.data.1.id', $outOfStock->id)
            );
    }

    public function test_shop_category_brand_and_search_pages_expose_discovery_state(): void
    {
        $product = $this->makeProduct(['name' => 'Discovery Capsules', 'is_featured' => true]);
        ProductAttribute::query()->create([
            'product_id' => $product->id,
            'attribute_name' => 'PACK_SIZE',
            'attribute_value' => '60',
            'is_public' => true,
        ]);

        $this->get('/shop?collection=featured&sort=newest')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalogue/Index')
                ->where('listing_kind', 'shop')
                ->where('filters.collection', 'featured')
                ->where('filters.sort', 'newest')
                ->has('breadcrumbs')
                ->missing('catalogueNav')
            );

        $this->get('/category/'.$product->category->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalogue/Index')
                ->where('listing_kind', 'category')
                ->where('context.name', $product->category->name)
            );

        $this->get('/brand/'.$product->brand->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalogue/Index')
                ->where('listing_kind', 'brand')
                ->where('result_count', 1)
            );

        $this->get('/search?q=Discovery')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalogue/Index')
                ->where('listing_kind', 'search')
                ->where('products.data.0.sku', $product->sku)
            );
    }

    public function test_search_suggest_groups_products_brands_and_categories(): void
    {
        $product = $this->makeProduct(['name' => 'Grouped Search Item']);

        $this->getJson('/search/suggest?q='.$product->brand->name)
            ->assertOk()
            ->assertJsonPath('brands.0.name', $product->brand->name)
            ->assertJsonStructure(['results', 'products', 'brands', 'categories', 'view_all']);
    }

    public function test_quick_view_and_recent_cards_only_return_purchasable_products(): void
    {
        $live = $this->makeProduct(['name' => 'Live Quick View']);
        $hidden = $this->makeProduct([
            'name' => 'Hidden Quick View',
            'status' => ProductStatus::DRAFT,
            'sku' => 'HID-QV-1',
            'slug' => 'hidden-quick-view',
        ]);

        $this->getJson('/product/'.$live->slug.'/quick-view')
            ->assertOk()
            ->assertJsonPath('product.sku', $live->sku)
            ->assertJsonPath('product.pricing.effective_price', '55.00');

        $this->getJson('/product/'.$hidden->slug.'/quick-view')->assertNotFound();

        $this->getJson('/catalogue/cards?slugs='.$live->slug.','.$hidden->slug)
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.slug', $live->slug);
    }

    public function test_compare_page_omits_non_purchasable_products(): void
    {
        $live = $this->makeProduct(['name' => 'Compare Live']);
        $hidden = $this->makeProduct([
            'name' => 'Compare Hidden',
            'status' => ProductStatus::COMPLIANCE_REVIEW,
            'compliance_verified_at' => null,
            'sku' => 'CMP-HID',
            'slug' => 'compare-hidden',
        ]);

        $this->get('/compare?ids='.$live->id.','.$hidden->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalogue/Compare')
                ->has('products', 1)
                ->where('products.0.id', $live->id)
                ->where('seo.robots', 'noindex,nofollow')
            );
    }

    public function test_product_page_includes_grouped_relations_and_home_breadcrumb(): void
    {
        $product = $this->makeProduct(['name' => 'Detail Experience']);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalogue/Show')
                ->where('product.breadcrumbs.0.name', 'Home')
                ->has('product.related_groups.related')
                ->has('product.related_groups.alternative')
                ->has('product.related_groups.complementary')
            );
    }

    public function test_cart_is_not_indexed(): void
    {
        $this->get('/cart')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cart/Index')
                ->where('seo.robots', 'noindex,nofollow')
            );
    }

    public function test_out_of_stock_product_is_not_quick_added_as_available(): void
    {
        $product = $this->makeProduct(['name' => 'Empty Discovery'], 0);

        $this->getJson('/product/'.$product->slug.'/quick-view')
            ->assertOk()
            ->assertJsonPath('product.is_purchasable', false)
            ->assertJsonPath('product.stock.code', 'OUT_OF_STOCK');
    }

    public function test_search_results_are_not_indexed(): void
    {
        $this->get('/search?q=nothing-matching')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalogue/Index')
                ->where('listing_kind', 'search')
                ->where('seo.robots', 'noindex,nofollow')
            );
    }

    public function test_attribute_filters_remain_in_query_state(): void
    {
        $product = $this->makeProduct(['name' => 'Attribute Filter Capsules']);
        ProductAttribute::query()->create([
            'product_id' => $product->id,
            'attribute_name' => 'PACK_SIZE',
            'attribute_value' => '60',
            'is_public' => true,
        ]);

        $this->get('/shop?attributes[PACK_SIZE]=60&sort=name_asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.attributes.PACK_SIZE', '60')
                ->where('filters.sort', 'name_asc')
                ->where('products.data.0.sku', $product->sku)
            );
    }

    public function test_only_approved_reviews_appear_on_the_product_page(): void
    {
        $product = $this->makeProduct(['name' => 'Review Visibility']);
        $user = $this->makeCustomer('reviewer@customer.test');

        Review::query()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'rating' => 5,
            'title' => 'Pending title',
            'body' => 'Should stay hidden',
            'status' => ReviewStatus::PENDING,
        ]);

        Review::query()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'rating' => 4,
            'title' => 'Approved title',
            'body' => 'Visible review',
            'status' => ReviewStatus::APPROVED,
            'is_verified_purchase' => true,
        ]);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.reviews.count', 1)
                ->where('product.reviews.items.0.title', 'Approved title')
                ->where('product.reviews.items.0.verified_purchase', true)
            );
    }

    public function test_hidden_categories_are_omitted_from_public_navigation(): void
    {
        $hidden = $this->makeCategory('Hidden Lab');
        $hidden->update(['is_visible' => false]);

        $this->getJson('/catalogue/nav')
            ->assertOk()
            ->assertJsonPath('categories', fn ($categories) => collect($categories)->every(
                fn ($category) => ($category['slug'] ?? null) !== $hidden->slug
            ));
    }
}
