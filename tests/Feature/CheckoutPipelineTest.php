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
use App\Services\Checkout\CheckoutSessionService;
use App\Services\Order\OrderCreationService;
use App\Services\Payment\PaymentStateMachine;
use App\Services\Shipping\ShippingEngine;
use Carbon\Carbon;
use Database\Seeders\InitialFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected Brand $brand;
    protected Category $category;
    protected User $customerA;
    protected User $customerB;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialFoundationSeeder::class);

        $this->brand = Brand::firstOrCreate(['name' => 'Apex Pharma'], [
            'slug' => 'apex-pharma',
            'is_active' => true,
        ]);

        $this->category = Category::firstOrCreate(['name' => 'Injectables'], [
            'slug' => 'injectables',
            'is_visible' => true,
        ]);

        $this->customerA = User::create([
            'name' => 'Alice Customer',
            'email' => 'alice@customer.test',
            'password' => bcrypt('Secret123!'),
            'role' => 'CUSTOMER',
        ]);

        $this->customerB = User::create([
            'name' => 'Bob Customer',
            'email' => 'bob@customer.test',
            'password' => bcrypt('Secret123!'),
            'role' => 'CUSTOMER',
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin Boss',
            'email' => 'admin@supreme.test',
            'password' => bcrypt('AdminSecret123!'),
            'role' => 'SUPER_ADMIN',
        ]);
    }

    protected function createProduct(string $name, float $price, int $stock = 20): Product
    {
        $product = Product::create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid(),
            'sku' => strtoupper(substr($name, 0, 4)) . '-' . uniqid(),
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'price_amount' => $price,
            'currency' => 'GBP',
            'status' => ProductStatus::ACTIVE,
            'classification' => ProductClassification::RESEARCH_USE,
            'compliance_verified_at' => Carbon::now()->subDay(),
            'batch_number' => 'BATCH-CHK-001',
            'lab_verification_reference' => 'LAB-CHK-001',
        ]);

        Inventory::create([
            'product_id' => $product->id,
            'quantity_on_hand' => $stock,
            'quantity_reserved' => 0,
            'safety_stock_threshold' => 2,
        ]);

        return $product;
    }

    public function test_shipping_free_delivery_threshold_works_precisely_at_boundary(): void
    {
        $engine = app(ShippingEngine::class);

        // £299.99 must NOT qualify for free shipping (costs £10.00)
        $ratesBelow = $engine->calculateRates(299.99, 'GB');
        $standardBelow = $ratesBelow->firstWhere('code', 'UK_STANDARD');
        $this->assertNotNull($standardBelow);
        $this->assertEquals(10.00, $standardBelow['rate']);
        $this->assertFalse($standardBelow['is_free']);

        // Exactly £300.00 DOES qualify for free shipping (£0.00)
        $ratesExact = $engine->calculateRates(300.00, 'GB');
        $standardExact = $ratesExact->firstWhere('code', 'UK_STANDARD');
        $this->assertNotNull($standardExact);
        $this->assertEquals(0.00, $standardExact['rate']);
        $this->assertTrue($standardExact['is_free']);

        $ratesAbove = $engine->calculateRates(300.01, 'GB');
        $standardAbove = $ratesAbove->firstWhere('code', 'UK_STANDARD');
        $this->assertNotNull($standardAbove);
        $this->assertEquals(0.00, $standardAbove['rate']);
        $this->assertTrue($standardAbove['is_free']);
    }

    public function test_idempotent_checkout_replay_returns_same_order(): void
    {
        $product = $this->createProduct('Oxandrolone 10mg', 50.00, 10);
        $orderCreationService = app(OrderCreationService::class);
        $idempotencyKey = 'idem_' . uniqid();

        $orderData = [
            'customer' => $this->customerA,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'shipping_destination' => [
                'country_code' => 'GB',
                'full_name' => 'Alice Customer',
                'address_line_1' => '10 Downing Street',
                'city' => 'London',
                'postal_code' => 'SW1A 2AA',
            ],
            'shipping_method_code' => 'UK_STANDARD',
            'payment_method' => PaymentMethodType::BANK_TRANSFER,
            'idempotency_key' => $idempotencyKey,
        ];

        // First execution creates Order #1
        $order1 = $orderCreationService->createOrder($orderData);
        $this->assertNotNull($order1);
        $this->assertEquals($idempotencyKey, $order1->idempotency_key);
        $this->assertStringStartsWith('SS-', $order1->order_number);

        // Replay with the same idempotency key and user returns the existing order
        $order2 = $orderCreationService->createOrder($orderData);
        $this->assertEquals($order1->id, $order2->id);
        $this->assertEquals($order1->order_number, $order2->order_number);

        // Verify only 1 order exists in database with this key
        $this->assertEquals(1, Order::where('idempotency_key', $idempotencyKey)->count());
    }

    public function test_customer_order_authorization_prevents_unauthorized_inspection(): void
    {
        $product = $this->createProduct('Masteron Propionate', 65.00, 10);
        $orderCreationService = app(OrderCreationService::class);

        $orderA = $orderCreationService->createOrder([
            'customer' => $this->customerA,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'shipping_destination' => [
                'country_code' => 'GB',
                'full_name' => 'Alice Customer',
                'address_line_1' => '221B Baker Street',
                'city' => 'London',
                'postal_code' => 'NW1 6XE',
            ],
            'shipping_method_code' => 'UK_STANDARD',
            'payment_method' => PaymentMethodType::CRYPTOCURRENCY,
        ]);

        // Customer A can view their own order
        $this->actingAs($this->customerA)
            ->get(route('orders.show', $orderA->id))
            ->assertStatus(200);

        // Customer B attempting to view Customer A's order receives 404 (scoped by user_id)
        $this->actingAs($this->customerB)
            ->get(route('orders.show', $orderA->id))
            ->assertStatus(404);
    }

    public function test_order_status_history_records_every_transition(): void
    {
        $product = $this->createProduct('HCG 5000iu', 35.00, 10);
        $orderCreationService = app(OrderCreationService::class);

        $order = $orderCreationService->createOrder([
            'customer' => $this->customerA,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'shipping_destination' => [
                'country_code' => 'GB',
                'full_name' => 'Alice Customer',
                'address_line_1' => '1 Parliament Square',
                'city' => 'London',
                'postal_code' => 'SW1P 3JX',
            ],
            'shipping_method_code' => 'UK_STANDARD',
            'payment_method' => PaymentMethodType::BANK_TRANSFER,
        ]);

        $this->assertCount(1, $order->statusHistory);
        $firstHistory = $order->statusHistory->first();
        $this->assertEquals('PENDING_PAYMENT', $firstHistory->new_status);

        // Transition through state machine
        $stateMachine = app(\App\Services\Order\OrderStateMachine::class);
        $stateMachine->transition($order, OrderStatus::PAYMENT_REVIEW, $this->customerA, 'Submitted transfer reference.');

        $order->refresh();
        $this->assertCount(2, $order->statusHistory);
        $this->assertEquals(OrderStatus::PAYMENT_REVIEW, $order->status);
    }

    public function test_payment_rejection_prevents_order_from_marking_as_paid(): void
    {
        $product = $this->createProduct('Clenbuterol 40mcg', 30.00, 10);
        $orderCreationService = app(OrderCreationService::class);

        $order = $orderCreationService->createOrder([
            'customer' => $this->customerA,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'shipping_destination' => [
                'country_code' => 'GB',
                'full_name' => 'Alice Customer',
                'address_line_1' => '10 Downing St',
                'city' => 'London',
                'postal_code' => 'SW1A 2AA',
            ],
            'shipping_method_code' => 'UK_STANDARD',
            'payment_method' => PaymentMethodType::BANK_TRANSFER,
        ]);

        /** @var Payment $payment */
        $payment = $order->payments()->first();
        $paymentStateMachine = app(PaymentStateMachine::class);

        // Move to review
        $paymentStateMachine->transition($payment, PaymentStatus::PAYMENT_SUBMITTED, $this->customerA, 'Customer submitted transfer details');
        $payment->refresh();
        $paymentStateMachine->transition($payment, PaymentStatus::UNDER_REVIEW, $this->adminUser, 'Under admin verification');
        $order->refresh();
        $this->assertEquals(OrderStatus::PAYMENT_REVIEW, $order->status);

        // Reject payment
        $paymentStateMachine->transition($payment, PaymentStatus::REJECTED, $this->adminUser, 'Invalid transaction reference');
        $order->refresh();
        $payment->refresh();

        $this->assertEquals(PaymentStatus::REJECTED, $payment->status);
        $this->assertEquals(OrderStatus::PENDING_PAYMENT, $order->status);
        $this->assertNotEquals(OrderStatus::PAID, $order->status);
    }
}
