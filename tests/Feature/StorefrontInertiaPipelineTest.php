<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class StorefrontInertiaPipelineTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    public function test_home_and_shop_and_product_pages_render(): void
    {
        $product = $this->makeProduct(['name' => 'Visible Cypionate']);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Home'));
        $this->get('/shop')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Catalogue/Index'));
        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Catalogue/Show')->where('product.sku', $product->sku));
    }

    public function test_guest_can_add_item_via_http(): void
    {
        $product = $this->makeProduct();

        $this->post('/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertRedirect();

        $item = \App\Models\CartItem::query()->first();
        $this->assertNotNull($item);
        $this->assertSame(2, $item->quantity);
        $this->assertNull($item->cart->user_id);
    }

    public function test_authenticated_customer_can_update_and_remove_cart_item(): void
    {
        $user = $this->makeCustomer();
        $product = $this->makeProduct();

        $this->actingAs($user)->post('/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertRedirect();

        $item = \App\Models\CartItem::query()->first();
        $this->assertNotNull($item);

        $this->actingAs($user)->patch('/cart/items/'.$item->id, ['quantity' => 1])->assertRedirect();
        $this->assertSame(1, $item->fresh()->quantity);

        $this->actingAs($user)->delete('/cart/items/'.$item->id)->assertRedirect();
        $this->assertNull($item->fresh());
    }

    public function test_client_supplied_cart_price_is_rejected(): void
    {
        $product = $this->makeProduct();

        $this->post('/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => '0.01',
        ])->assertSessionHasErrors('unit_price');
    }

    public function test_checkout_and_account_require_authentication(): void
    {
        $this->get('/checkout')->assertRedirect('/login');
        $this->get('/account')->assertRedirect('/login');
        $this->get('/orders')->assertRedirect('/login');
    }

    public function test_authenticated_customer_can_open_account_and_checkout(): void
    {
        $user = $this->makeCustomer();
        $product = $this->makeProduct();

        $this->actingAs($user)->post('/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($user)->get('/account')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Account/Profile'));
        $this->actingAs($user)->get('/checkout')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Checkout/Index'));
    }

    public function test_client_submitted_totals_are_ignored_and_server_totals_used(): void
    {
        $user = $this->makeCustomer();
        $product = $this->makeProduct(['price_amount' => 55.00]);

        $this->actingAs($user)->post('/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($user)->post('/checkout', [
            'payment_method' => 'BANK_TRANSFER',
            'shipping_method_code' => 'UK_STANDARD',
            'grand_total' => '0.01',
            'shipping_amount' => '0.00',
            'unit_price' => '0.01',
            'shipping_destination' => [
                'country_code' => 'GB',
                'full_name' => 'Alice Customer',
                'address_line_1' => '1 King Street',
                'city' => 'London',
                'postal_code' => 'W6 9HW',
            ],
        ])->assertRedirect();

        $order = Order::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($order);
        $this->assertNotSame('0.01', (string) $order->total_amount);
        $this->assertTrue((float) $order->total_amount >= 55.00);
    }

    public function test_shipping_quote_rejects_client_subtotal(): void
    {
        $this->getJson('/api/shipping/quote?country_code=GB&subtotal=1')->assertStatus(422);
        $this->getJson('/api/shipping/quote?country_code=GB')->assertOk()->assertJsonPath('country_code', 'GB');
    }
}
