<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentMethodType;
use App\Models\Order;
use App\Services\Checkout\CheckoutSessionService;
use App\Services\Order\OrderCreationService;
use Database\Seeders\InitialFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class CheckoutSessionAndOrderSnapshotTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialFoundationSeeder::class);
    }

    public function test_checkout_snapshot_ignores_client_supplied_totals(): void
    {
        $product = $this->makeProduct(['price_amount' => 50.00]);
        $customer = $this->makeCustomer('snap@test.com');

        $snapshot = app(CheckoutSessionService::class)->prepareCheckout([
            'customer' => $customer,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'shipping_destination' => $this->shippingDestination($customer->name),
            'shipping_method_code' => 'UK_STANDARD',
        ]);

        $this->assertArrayHasKey('checkout_token', $snapshot);
        $this->assertEquals('100.00', $snapshot['totals']['subtotal_amount']);
        $this->assertEquals('10.00', $snapshot['totals']['shipping_amount']);
        $this->assertEquals('110.00', $snapshot['grand_total']);
        $this->assertSame('GB', $snapshot['shipping_destination']['country_code']);
    }

    public function test_order_preserves_customer_and_address_snapshots(): void
    {
        $product = $this->makeProduct(['price_amount' => 90.00]);
        $customer = $this->makeCustomer('hist@test.com', 'Historical Name');

        $order = app(OrderCreationService::class)->createOrder([
            'customer' => $customer,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'shipping_destination' => $this->shippingDestination('Historical Name'),
            'shipping_method_code' => 'UK_EXPRESS',
            'payment_method' => PaymentMethodType::CRYPTOCURRENCY,
        ]);

        $customer->name = 'Changed Name';
        $customer->email = 'changed@test.com';
        $customer->save();

        $order->refresh();
        $this->assertEquals('Historical Name', $order->customer_name_snapshot);
        $this->assertEquals('hist@test.com', $order->customer_email_snapshot);
        $this->assertEquals('SW1A 2AA', $order->shipping_address_snapshot['postal_code']);
        $this->assertStringContainsString('UK Express', $order->shipping_method_name_snapshot);
        $this->assertEquals('15.00', (string) $order->shipping_amount);
    }

    public function test_order_creation_from_checkout_token(): void
    {
        $product = $this->makeProduct(['price_amount' => 60.00]);
        $customer = $this->makeCustomer('token@test.com');

        $snapshot = app(CheckoutSessionService::class)->prepareCheckout([
            'customer' => $customer,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'shipping_destination' => $this->shippingDestination($customer->name),
            'shipping_method_code' => 'UK_STANDARD',
        ]);

        $order = app(OrderCreationService::class)->createOrder([
            'customer' => $customer,
            'checkout_token' => $snapshot['checkout_token'],
            'payment_method' => PaymentMethodType::BANK_TRANSFER,
            'idempotency_key' => 'tok-1',
        ]);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($snapshot['grand_total'], (string) $order->total_amount);
        $this->assertDatabaseHas('checkout_sessions', [
            'token' => $snapshot['checkout_token'],
            'status' => 'CONVERTED',
            'order_id' => $order->id,
        ]);
    }
}
