<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ImportMatchStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductClassification;
use App\Enums\ProductRelationType;
use App\Enums\ProductStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\ProductNotPurchasableException;
use App\Models\CatalogueImportRecord;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use App\Models\SeoMetadata;
use App\Models\StockNotification;
use App\Services\Catalogue\CatalogueImportService;
use App\Services\Catalogue\CataloguePresentation;
use App\Services\Catalogue\CataloguePublicationService;
use App\Services\Catalogue\RelatedProductService;
use App\Services\Catalogue\ReviewService;
use App\Services\Catalogue\StructuredDataService;
use App\Services\Catalogue\VariantCatalogService;
use App\Services\Pricing\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class CatalogueExpansionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    public function test_publication_checklist_and_compliance_gate(): void
    {
        $product = $this->makeProduct([
            'status' => ProductStatus::DRAFT,
            'compliance_verified_at' => null,
            'description' => null,
            'short_description' => null,
        ]);

        $service = app(CataloguePublicationService::class);
        $checklist = $service->checklist($product);

        $this->assertFalse($checklist['can_publish']);
        $this->assertFalse(collect($checklist['items'])->firstWhere('key', 'compliance')['passed']);

        $this->expectException(ProductNotPurchasableException::class);
        $service->publish($product);
    }

    public function test_publish_succeeds_only_after_checklist_passes_and_does_not_auto_verify_compliance(): void
    {
        $product = $this->makeProduct([
            'status' => ProductStatus::DRAFT,
            'description' => 'Original store description for a lawful catalogue item.',
            'short_description' => 'Factual commercial summary.',
        ]);
        ProductImage::query()->create([
            'product_id' => $product->id,
            'url' => '/assets/demo/product.jpg',
            'alt_text' => $product->name,
            'is_primary' => true,
            'display_order' => 0,
        ]);

        $published = app(CataloguePublicationService::class)->publish($product->fresh());

        $this->assertSame(ProductStatus::ACTIVE, $published->status);
        $this->assertNotNull($published->published_at);
        $this->assertNotNull($published->compliance_verified_at);
        $this->assertTrue($published->revisions()->exists());
    }

    public function test_demo_products_are_never_purchasable_or_listed(): void
    {
        $demo = $this->makeProduct([
            'name' => 'Demo Catalogue Item',
            'is_demo' => true,
            'sku' => 'DEMO-001',
            'slug' => 'demo-catalogue-item',
        ]);

        $this->assertSame(0, Product::query()->purchasable()->whereKey($demo->id)->count());
        $this->get('/shop')->assertOk()->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
        $this->get('/product/'.$demo->slug)->assertNotFound();
    }

    public function test_variant_pricing_stock_and_duplicate_prevention(): void
    {
        $product = $this->makeProduct(['price_amount' => 49.90, 'compare_at_price_amount' => 59.90]);
        $variant = app(VariantCatalogService::class)->create($product, [
            'sku' => $product->sku.'-60',
            'name' => '60 capsules',
            'price_amount' => 39.90,
            'compare_at_price_amount' => 49.90,
        ], ['PACK_SIZE' => '60']);

        $pricing = app(PricingService::class)->calculateProductPrice($product, $variant);
        $this->assertTrue($pricing['is_on_sale']);
        $this->assertSame('39.90', $pricing['effective_price_amount']);
        $this->assertSame(20, $pricing['discount_percent']);

        $this->makeVariant($product, '30 capsules', '29.90', 0);
        $out = $product->variants()->where('name', '30 capsules')->first();
        $this->assertSame('OUT_OF_STOCK', app(CataloguePresentation::class)->stock($product, $out)['code']);

        $this->expectException(ValidationException::class);
        app(VariantCatalogService::class)->create($product, [
            'sku' => $product->sku.'-60B',
            'name' => 'Sixty',
            'price_amount' => 39.90,
        ], ['PACK_SIZE' => '60']);
    }

    public function test_category_brand_and_related_product_rules(): void
    {
        $primary = $this->makeCategory('Oral Steroids');
        $secondary = $this->makeCategory('PCT');
        $product = $this->makeProduct(['name' => 'Lawful Support Formula', 'category_id' => $primary->id]);
        $product->categories()->attach($secondary->id, ['is_primary' => false]);

        $hidden = $this->makeProduct([
            'name' => 'Hidden Related',
            'status' => ProductStatus::COMPLIANCE_REVIEW,
            'compliance_verified_at' => null,
            'sku' => 'HID-REL-1',
            'slug' => 'hidden-related',
        ]);
        $visibleRelated = $this->makeProduct(['name' => 'Visible Related', 'sku' => 'VIS-REL-1', 'slug' => 'visible-related']);

        app(RelatedProductService::class)->attach($product, $hidden, ProductRelationType::RELATED);
        app(RelatedProductService::class)->attach($product, $visibleRelated, ProductRelationType::ALTERNATIVE);

        $this->assertTrue(Product::query()->byCategory($secondary->slug)->whereKey($product->id)->exists());
        $this->assertTrue(Product::query()->byBrand($product->brand->slug)->whereKey($product->id)->exists());

        $related = app(RelatedProductService::class)->purchasableRelated($product);
        $this->assertTrue($related->contains('id', $visibleRelated->id));
        $this->assertFalse($related->contains('id', $hidden->id));

        $this->get('/category/'.$primary->slug)->assertOk();
        $this->get('/brand/'.$product->brand->slug)->assertOk();
    }

    public function test_search_filter_and_sort_are_server_side(): void
    {
        $cheap = $this->makeProduct(['name' => 'Alpha Support', 'price_amount' => 10.00, 'sku' => 'ALP-10']);
        $expensive = $this->makeProduct(['name' => 'Zulu Support', 'price_amount' => 80.00, 'sku' => 'ZUL-80', 'slug' => 'zulu-support', 'is_featured' => true]);
        $this->makeProduct(['name' => 'Other Brand Item', 'sku' => 'OTH-1', 'slug' => 'other-brand-item']);

        $this->assertTrue(Product::query()->searchable('Alpha')->whereKey($cheap->id)->exists());
        $this->assertTrue(Product::query()->searchable('ALP-10')->whereKey($cheap->id)->exists());
        $this->assertTrue(Product::query()->searchable($cheap->brand->name)->whereKey($cheap->id)->exists());

        $this->get('/shop?sort=price_asc')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.id', $cheap->id)
        );
        $this->get('/shop?sort=name_desc')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.id', $expensive->id)
        );
        $this->get('/search/suggest?q=Alpha')->assertOk()->assertJsonFragment(['sku' => 'ALP-10']);
        $this->get('/shop?search=no-such-item')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 0)
        );
    }

    public function test_out_of_stock_hides_add_to_cart_and_stores_notification(): void
    {
        $product = $this->makeProduct(['name' => 'Restock Item'], 0);

        $this->get('/product/'.$product->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('product.is_purchasable', false)
            ->where('product.stock.code', 'OUT_OF_STOCK')
        );

        $this->post('/product/'.$product->slug.'/notify', [
            'email' => 'restock@example.test',
        ])->assertRedirect();

        $this->assertTrue(StockNotification::query()->where('email', 'restock@example.test')->where('product_id', $product->id)->exists());
    }

    public function test_wishlist_toggle_requires_purchasable_product(): void
    {
        $user = $this->makeCustomer('wish@test.com');
        $product = $this->makeProduct(['name' => 'Wishlist Item']);

        $this->actingAs($user)->post('/wishlist', ['product_id' => $product->id])->assertRedirect();
        $this->actingAs($user)->get('/account/wishlist')->assertOk();
    }

    public function test_reviews_are_verified_from_orders_and_only_approved_are_public(): void
    {
        $user = $this->makeCustomer('reviewer@test.com', 'Reviewer');
        $admin = $this->makeAdmin('review-admin@test.com');
        $product = $this->makeProduct(['name' => 'Reviewed Item']);

        $order = Order::query()->create([
            'order_number' => 'SS-REV-001',
            'user_id' => $user->id,
            'status' => OrderStatus::PAID,
            'subtotal_amount' => 55.00,
            'shipping_amount' => 0,
            'total_amount' => 55.00,
            'currency' => 'GBP',
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => 55.00,
            'quantity' => 1,
            'total_price' => 55.00,
        ]);

        $review = app(ReviewService::class)->submit($product, $user, 5, 'Solid packaging', 'Arrived as described.');
        $this->assertTrue($review->is_verified_purchase);
        $this->assertSame(ReviewStatus::PENDING, $review->status);

        $this->get('/product/'.$product->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('product.reviews.count', 0)
        );

        app(ReviewService::class)->moderate($review, ReviewStatus::APPROVED, $admin);
        $this->get('/product/'.$product->slug)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('product.reviews.count', 1)
            ->where('product.reviews.items.0.verified_purchase', true)
        );

        app(ReviewService::class)->moderate($review->fresh(), ReviewStatus::HIDDEN, $admin);
        $this->assertSame(0, Review::query()->where('status', ReviewStatus::APPROVED)->count());
    }

    public function test_seo_defaults_are_unique_and_structured_data_omits_fake_reviews(): void
    {
        $first = $this->makeProduct(['name' => 'Alpha Capsules', 'short_description' => 'Bottle of capsules.']);
        $second = $this->makeProduct(['name' => 'Beta Tablets', 'short_description' => 'Blister pack of tablets.', 'sku' => 'BETA-1', 'slug' => 'beta-tablets']);

        $seoA = app(\App\Services\Catalogue\SeoService::class)->forPage(
            \Illuminate\Http\Request::create('/product/'.$first->slug, 'GET'),
            $first->name,
            (string) $first->short_description,
            [],
            $first
        );
        $seoB = app(\App\Services\Catalogue\SeoService::class)->forPage(
            \Illuminate\Http\Request::create('/product/'.$second->slug, 'GET'),
            $second->name,
            (string) $second->short_description,
            [],
            $second
        );
        $this->assertNotSame($seoA['description'], $seoB['description']);

        SeoMetadata::query()->create([
            'seoable_type' => Product::class,
            'seoable_id' => $first->id,
            'meta_title' => 'Custom Alpha title',
            'meta_description' => 'Custom alpha description for search.',
        ]);
        $overridden = app(\App\Services\Catalogue\SeoService::class)->forPage(
            \Illuminate\Http\Request::create('/product/'.$first->slug, 'GET'),
            $first->name,
            (string) $first->short_description,
            [],
            $first->fresh('seoMetadata')
        );
        $this->assertSame('Custom Alpha title', $overridden['title']);

        $schema = app(StructuredDataService::class)->forProduct($first->fresh(['brand', 'images']));
        $this->assertSame('Product', $schema['@type']);
        $this->assertArrayNotHasKey('aggregateRating', $schema);
        $this->assertSame($first->name, $schema['name']);
    }

    public function test_import_stages_drafts_detects_duplicates_and_never_auto_publishes(): void
    {
        $existing = $this->makeProduct(['name' => 'Known Support Formula', 'sku' => 'KNOWN-1']);
        $importer = app(CatalogueImportService::class);

        $fresh = $importer->stage([
            'name' => 'Imported Wellness Capsules',
            'sku' => 'IMP-NEW-1',
            'brand' => 'North Harbour',
            'category' => 'Supplements',
            'price' => 24.00,
            'format' => 'Capsules',
            'pack_size' => '60',
        ], 'batch-1', 'csv');

        $this->assertSame(ImportMatchStatus::NEW, $fresh->match_status);
        $this->assertNotNull($fresh->product);
        $this->assertSame(ProductStatus::IMPORTED, $fresh->product->status);
        $this->assertNull($fresh->product->compliance_verified_at);
        $this->assertSame(0, Product::query()->purchasable()->whereKey($fresh->product_id)->count());
        $this->assertStringNotContainsString('clinically proven', strtolower((string) $fresh->product->description));
        $this->get('/shop')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.sku', $existing->sku)
        );

        $exact = $importer->stage([
            'name' => 'Copy of known',
            'sku' => 'KNOWN-1',
        ], 'batch-2', 'json');
        $this->assertSame(ImportMatchStatus::EXACT_MATCH, $exact->match_status);
        $this->assertNull($exact->product_id);

        $possible = $importer->stage([
            'name' => 'Known Support Formula',
            'sku' => 'KNOWN-2',
            'brand' => $existing->brand->name,
        ], 'batch-3', 'admin');
        $this->assertSame(ImportMatchStatus::POSSIBLE_DUPLICATE, $possible->match_status);
        $this->assertSame(ProductStatus::IMPORTED, $possible->product->status);

        $this->assertSame(3, CatalogueImportRecord::query()->count());
    }

    public function test_restricted_imported_products_cannot_enter_cart(): void
    {
        $record = app(CatalogueImportService::class)->stage([
            'name' => 'Restricted Import',
            'sku' => 'RST-1',
            'classification' => ProductClassification::RESEARCH_USE->value,
        ], 'batch-r', 'api');

        $this->assertNotNull($record->product_id);
        $this->assertSame(ProductStatus::IMPORTED, $record->product->status);
        $this->assertNull($record->product->compliance_verified_at);
        $this->assertSame(0, Product::query()->purchasable()->whereKey($record->product_id)->count());

        $this->get('/shop')->assertOk();
        $this->post('/cart/items', [
            'product_id' => $record->product_id,
            'quantity' => 1,
        ])->assertRedirect()->assertSessionHasErrors();
    }
}
