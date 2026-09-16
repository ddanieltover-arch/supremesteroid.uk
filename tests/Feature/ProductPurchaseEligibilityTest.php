<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductClassification;
use App\Enums\ProductStatus;
use App\Exceptions\ProductNotPurchasableException;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Services\Catalogue\ProductPurchaseEligibilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPurchaseEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected ProductPurchaseEligibilityService $service;
    protected Brand $brand;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProductPurchaseEligibilityService::class);

        $this->brand = Brand::create([
            'name' => 'Apex Pharma',
            'slug' => 'apex-pharma',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Injectables',
            'slug' => 'injectables',
            'is_visible' => true,
        ]);
    }

    protected function createProduct(array $attributes = []): Product
    {
        $product = Product::create(array_merge([
            'name' => 'Testosterone Cypionate',
            'slug' => 'test-cyp-250-' . uniqid(),
            'sku' => 'APEX-TC250-' . uniqid(),
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'price_amount' => 55.00,
            'currency' => 'GBP',
            'status' => ProductStatus::ACTIVE,
            'classification' => ProductClassification::RESEARCH_USE,
            'compliance_verified_at' => Carbon::now()->subDay(),
            'batch_number' => 'BATCH-ELIG-001',
            'lab_verification_reference' => 'LAB-ELIG-001',
        ], $attributes));

        Inventory::create([
            'product_id' => $product->id,
            'quantity_on_hand' => 20,
            'quantity_reserved' => 0,
            'safety_stock_threshold' => 5,
        ]);

        return $product;
    }

    public function test_eligible_active_product_passes_verification(): void
    {
        $product = $this->createProduct();

        $check = $this->service->checkEligibility($product, null, 1, true);

        $this->assertTrue($check['is_eligible']);
        $this->assertEmpty($check['reasons']);
    }

    public function test_draft_product_is_blocked_from_purchase(): void
    {
        $product = $this->createProduct(['status' => ProductStatus::DRAFT]);

        $this->expectException(ProductNotPurchasableException::class);
        $this->service->assertPurchasable($product, null, 1, false);
    }

    public function test_imported_status_product_is_blocked(): void
    {
        $product = $this->createProduct(['status' => ProductStatus::IMPORTED]);

        $this->expectException(ProductNotPurchasableException::class);
        $this->service->assertPurchasable($product, null, 1, false);
    }

    public function test_under_review_product_is_blocked(): void
    {
        $product = $this->createProduct(['status' => ProductStatus::UNDER_REVIEW]);

        $this->expectException(ProductNotPurchasableException::class);
        $this->service->assertPurchasable($product, null, 1, false);
    }

    public function test_suspended_product_is_blocked(): void
    {
        $product = $this->createProduct(['status' => ProductStatus::SUSPENDED]);

        $this->expectException(ProductNotPurchasableException::class);
        $this->service->assertPurchasable($product, null, 1, false);
    }

    public function test_archived_product_is_blocked(): void
    {
        $product = $this->createProduct(['status' => ProductStatus::ARCHIVED]);

        $this->expectException(ProductNotPurchasableException::class);
        $this->service->assertPurchasable($product, null, 1, false);
    }

    public function test_unverified_compliance_blocks_purchase(): void
    {
        $product = $this->createProduct(['compliance_verified_at' => null]);

        $this->expectException(ProductNotPurchasableException::class);
        $this->service->assertPurchasable($product, null, 1, false);
    }

    public function test_zero_price_product_is_blocked(): void
    {
        $product = $this->createProduct(['price_amount' => 0.00]);

        $this->expectException(ProductNotPurchasableException::class);
        $this->service->assertPurchasable($product, null, 1, false);
    }

    public function test_out_of_stock_product_fails_inventory_check(): void
    {
        $product = $this->createProduct();
        $inventory = $product->inventory;
        $inventory->quantity_on_hand = 0;
        $inventory->save();

        $this->expectException(ProductNotPurchasableException::class);
        $this->service->assertPurchasable($product, null, 1, true);
    }
}
