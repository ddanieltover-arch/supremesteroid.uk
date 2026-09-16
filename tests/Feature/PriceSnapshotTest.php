<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentMethodType;
use App\Services\Cart\CartService;
use App\Services\Order\OrderCreationService;
use App\Services\Pricing\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class PriceSnapshotTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    public function test_cart_uses_current_price_after_catalogue_change(): void
    {
        $product = $this->makeProduct(['price_amount' => 40.00]);
        $sessionId = 'sess-price-' . uniqid();
        $cartService = app(CartService::class);
        $cart = $cartService->getOrCreateCart(null, $sessionId);
        $cartService->addItem($cart, $product, null, 1, null, $sessionId);

        $before = $cartService->calculateCart($cart);
        $this->assertEquals('40.00', $before['items'][0]['unit_price']);

        $product->price_amount = 55.00;
        $product->save();

        $after = $cartService->calculateCart($cart->fresh('items'));
        $this->assertEquals('55.00', $after['items'][0]['unit_price']);
    }

    public function test_order_item_snapshot_is_not_affected_by_later_product_price_change(): void
    {
        $product = $this->makeProduct(['name' => 'Snapshot Product', 'price_amount' => 80.00]);
        $customer = $this->makeCustomer();

        $order = app(OrderCreationService::class)->createOrder([
            'customer' => $customer,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'shipping_destination' => $this->shippingDestination($customer->name),
            'shipping_method_code' => 'UK_STANDARD',
            'payment_method' => PaymentMethodType::BANK_TRANSFER,
        ]);

        $item = $order->items->first();
        $this->assertEquals('80.00', (string) $item->unit_price);
        $this->assertEquals('160.00', (string) $item->line_subtotal);
        $this->assertEquals($product->name, $item->product_name_snapshot);
        $this->assertEquals($product->sku, $item->sku_snapshot);

        $product->price_amount = 999.00;
        $product->name = 'Renamed After Purchase';
        $product->save();

        $order->refresh()->load('items');
        $item = $order->items->first();
        $this->assertEquals('80.00', (string) $item->unit_price);
        $this->assertEquals('160.00', (string) $item->line_total);
        $this->assertEquals('Snapshot Product', $item->product_name);
        $this->assertNotEquals('Renamed After Purchase', $item->product_name);
    }

    public function test_checkout_token_rejects_price_changes_before_order_creation(): void
    {
        $this->seed(\Database\Seeders\InitialFoundationSeeder::class);

        $product = $this->makeProduct(['price_amount' => 50.00]);
        $customer = $this->makeCustomer('pricechange@customer.test');

        $snapshot = app(\App\Services\Checkout\CheckoutSessionService::class)->prepareCheckout([
            'customer' => $customer,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'shipping_destination' => $this->shippingDestination($customer->name),
            'shipping_method_code' => 'UK_STANDARD',
        ]);

        $product->price_amount = 70.00;
        $product->save();

        $this->expectException(\App\Exceptions\PriceChangedException::class);
        app(OrderCreationService::class)->createOrder([
            'customer' => $customer,
            'checkout_token' => $snapshot['checkout_token'],
            'payment_method' => PaymentMethodType::BANK_TRANSFER,
        ]);
    }

    public function test_pricing_service_line_totals_ignore_client_supplied_prices(): void
    {
        $product = $this->makeProduct(['price_amount' => 25.00]);
        $line = app(PricingService::class)->calculateLineItem($product, null, 3, 0);

        $this->assertEquals('75.00', $line['line_total_amount']);
        $this->assertNotEquals('1.00', $line['unit_price_amount']);
    }
}
