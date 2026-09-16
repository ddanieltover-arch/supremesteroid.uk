<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Enums\ProductClassification;
use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Reporting\AdminMetricsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_metrics_service_calculates_all_reporting_metrics_accurately(): void
    {
        $brand = Brand::create(['name' => 'Brand A', 'slug' => 'brand-a', 'is_active' => true]);
        $category = Category::create(['name' => 'Category A', 'slug' => 'cat-a', 'is_visible' => true]);

        $customer = User::create([
            'name' => 'Customer Test',
            'email' => 'customer@test.com',
            'password' => bcrypt('Secret123!'),
            'role' => 'CUSTOMER',
        ]);

        // Product 1: Low stock (on_hand 5 - reserved 1 = 4 <= safety threshold 5)
        $p1 = Product::create([
            'name' => 'Product Low Stock',
            'slug' => 'p-low-stock',
            'sku' => 'P-LOW',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'price_amount' => 50.00,
            'currency' => 'GBP',
            'status' => ProductStatus::ACTIVE,
            'classification' => ProductClassification::RESEARCH_USE,
            'compliance_verified_at' => Carbon::now(),
        ]);
        Inventory::create([
            'product_id' => $p1->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 1,
            'safety_stock_threshold' => 5,
        ]);

        // Product 2: Out of stock (on_hand 0, reserved 0)
        $p2 = Product::create([
            'name' => 'Product Out of Stock',
            'slug' => 'p-out-of-stock',
            'sku' => 'P-OUT',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'price_amount' => 60.00,
            'currency' => 'GBP',
            'status' => ProductStatus::ACTIVE,
            'classification' => ProductClassification::RESEARCH_USE,
            'compliance_verified_at' => Carbon::now(),
        ]);
        Inventory::create([
            'product_id' => $p2->id,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
            'safety_stock_threshold' => 2,
        ]);

        // Product 3: Unverified compliance (compliance_verified_at is null)
        $p3 = Product::create([
            'name' => 'Product Unverified Compliance',
            'slug' => 'p-unverified',
            'sku' => 'P-UNVER',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'price_amount' => 70.00,
            'currency' => 'GBP',
            'status' => ProductStatus::UNDER_REVIEW,
            'classification' => ProductClassification::RESEARCH_USE,
            'compliance_verified_at' => null,
        ]);

        // Order 1: Paid awaiting fulfillment
        $order1 = Order::create([
            'order_number' => 'SS-TEST-001',
            'user_id' => $customer->id,
            'status' => OrderStatus::PAID,
            'subtotal_amount' => 100.00,
            'shipping_amount' => 10.00,
            'total_amount' => 110.00,
            'currency' => 'GBP',
            'placed_at' => Carbon::now(),
        ]);

        Payment::create([
            'order_id' => $order1->id,
            'method' => PaymentMethodType::BANK_TRANSFER,
            'status' => PaymentStatus::PAID,
            'amount' => 110.00,
            'currency' => 'GBP',
            'paid_at' => Carbon::now(),
        ]);

        // Order 2: Pending payment submission
        $order2 = Order::create([
            'order_number' => 'SS-TEST-002',
            'user_id' => $customer->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'subtotal_amount' => 50.00,
            'shipping_amount' => 10.00,
            'total_amount' => 60.00,
            'currency' => 'GBP',
            'placed_at' => Carbon::now(),
        ]);

        Payment::create([
            'order_id' => $order2->id,
            'method' => PaymentMethodType::CRYPTOCURRENCY,
            'status' => PaymentStatus::PENDING,
            'amount' => 60.00,
            'currency' => 'GBP',
        ]);

        // Payment 3: Under review
        Payment::create([
            'order_id' => $order2->id,
            'method' => PaymentMethodType::BANK_TRANSFER,
            'status' => PaymentStatus::UNDER_REVIEW,
            'amount' => 60.00,
            'currency' => 'GBP',
        ]);

        $service = app(AdminMetricsService::class);
        $metrics = $service->getMetrics();

        $this->assertEquals(2, $metrics['total_orders']);
        $this->assertEquals(1, $metrics['pending_payments']);
        $this->assertEquals(1, $metrics['payments_under_review']);
        $this->assertEquals(1, $metrics['paid_awaiting_fulfillment']);
        $this->assertEquals(1, $metrics['low_stock_count']);
        $this->assertEquals(1, $metrics['out_of_stock_count']);
        $this->assertEquals(1, $metrics['compliance_review_backlog']);
    }
}
