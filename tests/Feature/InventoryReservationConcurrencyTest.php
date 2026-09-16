<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ProductClassification;
use App\Enums\ProductStatus;
use App\Exceptions\InsufficientInventoryException;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReservationConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryReservationService $reservationService;
    protected Brand $brand;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reservationService = app(InventoryReservationService::class);

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

    public function test_concurrent_inventory_reservation_blocks_second_buyer_when_stock_is_one(): void
    {
        // 1. Available stock = 1
        $product = Product::create([
            'name' => 'Limited Edition Primobolan',
            'slug' => 'limited-primo-' . uniqid(),
            'sku' => 'PRIMO-LTD-01',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'price_amount' => 95.00,
            'currency' => 'GBP',
            'status' => ProductStatus::ACTIVE,
            'classification' => ProductClassification::RESEARCH_USE,
            'compliance_verified_at' => Carbon::now()->subDay(),
            'batch_number' => 'BATCH-INV-001',
            'lab_verification_reference' => 'LAB-INV-001',
        ]);

        $inventory = Inventory::create([
            'product_id' => $product->id,
            'quantity_on_hand' => 1,
            'quantity_reserved' => 0,
            'safety_stock_threshold' => 0,
        ]);

        $sessionA = 'session_customer_a_' . uniqid();
        $sessionB = 'session_customer_b_' . uniqid();

        // 2. Customer A attempts to purchase / reserve = 1 -> SUCCESS
        $reservationA = $this->reservationService->reserveItem(
            product: $product,
            variant: null,
            quantity: 1,
            order: null,
            sessionId: $sessionA
        );

        $this->assertNotNull($reservationA);
        $this->assertEquals('ACTIVE', $reservationA->status);
        $this->assertEquals(1, $reservationA->quantity);

        // Verify inventory table state reflects 1 reserved, 0 available
        $inventory->refresh();
        $this->assertEquals(1, $inventory->quantity_on_hand);
        $this->assertEquals(1, $inventory->quantity_reserved);

        // 3. Customer B attempts to purchase / reserve = 1 -> FAILS SAFELY
        $failedSafely = false;
        try {
            $this->reservationService->reserveItem(
                product: $product,
                variant: null,
                quantity: 1,
                order: null,
                sessionId: $sessionB
            );
        } catch (InsufficientInventoryException $e) {
            $failedSafely = true;
            $this->assertEquals(0, $e->getAvailable());
            $this->assertEquals(1, $e->getRequested());
        }

        $this->assertTrue($failedSafely, 'Customer B attempt must fail with InsufficientInventoryException.');

        // 4. If Customer A releases their reservation, Customer B can now succeed
        $this->reservationService->releaseReservation($reservationA, 'customer_abandoned_checkout');

        $inventory->refresh();
        $this->assertEquals(0, $inventory->quantity_reserved);

        $reservationB = $this->reservationService->reserveItem(
            product: $product,
            variant: null,
            quantity: 1,
            order: null,
            sessionId: $sessionB
        );

        $this->assertNotNull($reservationB);
        $this->assertEquals('ACTIVE', $reservationB->status);
    }
}
