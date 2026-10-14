<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentMethodType;
use App\Models\Address;
use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class CheckoutJourneyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    public function test_empty_cart_redirects_from_checkout_to_basket(): void
    {
        $this->get('/checkout')->assertRedirect('/cart');
    }

    public function test_guest_can_complete_checkout_without_an_account(): void
    {
        $product = $this->makeProduct(['name' => 'Guest Checkout Item', 'price_amount' => 55.00]);
        $this->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();
        $this->assertDatabaseCount('cart_items', 1);

        $this->get('/checkout')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Checkout/Index')->where('checkout_step', 'details'));

        $this->post('/checkout/details', [
            'email' => 'guest@customer.test',
            'shipping_destination' => $this->shippingDestination('Guest Buyer'),
        ])->assertRedirect('/checkout/delivery');

        $this->post('/checkout/delivery', ['shipping_method_code' => 'UK_STANDARD'])
            ->assertRedirect('/checkout/payment');

        $this->post('/checkout', [
            'checkout_token' => session('checkout_token'),
            'payment_method' => PaymentMethodType::BANK_TRANSFER->value,
            'idempotency_key' => 'guest-order-1',
        ])->assertRedirect('/checkout/confirmation');

        $this->get('/checkout/confirmation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Checkout/Confirmation')->has('order.order_number'));

        $this->assertDatabaseHas('users', ['email' => 'guest@customer.test']);
        $this->assertSame(1, Order::query()->count());
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertGuest();
    }

    public function test_details_step_validates_on_the_server(): void
    {
        $user = $this->makeCustomer();
        $product = $this->makeProduct();
        $this->actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user)
            ->from('/checkout')
            ->post('/checkout/details', ['shipping_destination' => ['country_code' => 'GB']])
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors();
    }

    public function test_later_steps_require_server_state(): void
    {
        $user = $this->makeCustomer();
        $product = $this->makeProduct();
        $this->actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user)->get('/checkout/delivery')->assertRedirect('/checkout');
        $this->actingAs($user)->get('/checkout/payment')->assertRedirect('/checkout');
        $this->actingAs($user)->get('/checkout/confirmation')->assertRedirect('/checkout/payment');
    }

    public function test_saved_address_must_belong_to_the_customer(): void
    {
        $owner = $this->makeCustomer('owner@test.com', 'Owner');
        $intruder = $this->makeCustomer('intruder@test.com', 'Intruder');
        $product = $this->makeProduct();
        $address = Address::query()->create([
            'user_id' => $owner->id,
            'type' => 'shipping',
            'full_name' => 'Owner',
            'address_line_1' => '1 Private Road',
            'city' => 'London',
            'postal_code' => 'SW1A 1AA',
            'country_code' => 'GB',
        ]);

        $this->actingAs($intruder)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($intruder)
            ->from('/checkout')
            ->post('/checkout/details', ['address_id' => $address->id])
            ->assertRedirect('/checkout')
            ->assertSessionHasErrors('checkout');
    }

    public function test_multi_step_checkout_creates_one_order_and_confirmation(): void
    {
        $user = $this->makeCustomer();
        $product = $this->makeProduct(['price_amount' => 55.00]);
        $this->actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user)
            ->get('/checkout')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Index')
                ->where('checkout_step', 'details')
                ->where('checkout_steps.0.state', 'current')
                ->where('checkout_steps.2.state', 'upcoming')
                ->has('paymentMethods')
            );

        $this->actingAs($user)->post('/checkout/details', [
            'shipping_destination' => $this->shippingDestination($user->name),
        ])->assertRedirect('/checkout/delivery');

        $this->actingAs($user)
            ->get('/checkout/delivery')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Delivery')
                ->where('checkout_step', 'delivery')
                ->where('checkout_steps.0.state', 'completed')
                ->where('checkout_steps.1.state', 'current')
                ->where('checkout_steps.2.state', 'upcoming')
                ->has('availableShippingMethods')
            );

        $this->actingAs($user)
            ->get('/checkout/delivery?shipping_method_code=UK_EXPRESS')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedShippingMethod.code', 'UK_EXPRESS')
                ->where('checkoutPreview.shipping', '15.00')
                ->where('checkoutPreview.grand_total', '70.00')
            );

        $this->actingAs($user)
            ->post('/checkout/delivery', ['shipping_method_code' => 'UK_EXPRESS'])
            ->assertRedirect('/checkout/payment');

        $this->actingAs($user)
            ->get('/checkout/payment')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Payment')
                ->where('checkout_step', 'payment')
                ->where('checkout_steps.2.state', 'current')
                ->where('checkout_steps.3.state', 'upcoming')
                ->where('paymentMethods.0.code', PaymentMethodType::BANK_TRANSFER->value)
                ->missing('paymentMethods.2')
            );

        $this->actingAs($user)
            ->post('/checkout/payment', ['payment_method' => PaymentMethodType::CRYPTOCURRENCY->value])
            ->assertRedirect();

        $this->actingAs($user)
            ->post('/checkout', [
                'checkout_token' => session('checkout_token'),
                'payment_method' => PaymentMethodType::BANK_TRANSFER->value,
                'idempotency_key' => 'journey-order-1',
            ])
            ->assertRedirect('/checkout/confirmation');

        $this->assertSame(1, Order::query()->where('user_id', $user->id)->count());

        $this->actingAs($user)
            ->get('/checkout/confirmation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Confirmation')
                ->where('checkout_step', 'confirmation')
                ->where('checkout_steps.2.state', 'completed')
                ->where('checkout_steps.3.state', 'current')
                ->has('order.order_number')
            );

        $this->actingAs($user)->get('/checkout/confirmation')->assertOk();
        $this->assertSame(1, Order::query()->where('user_id', $user->id)->count());

        $this->actingAs($user)->get('/checkout/payment')->assertRedirect('/checkout/confirmation');
        $this->actingAs($user)->get('/checkout/delivery')->assertRedirect('/checkout/confirmation');
    }

    public function test_duplicate_place_order_uses_idempotency_key(): void
    {
        $user = $this->makeCustomer('dup@test.com');
        $product = $this->makeProduct(['name' => 'Duplicate Checkout Item']);
        $this->actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user)->post('/checkout/details', [
            'shipping_destination' => $this->shippingDestination($user->name),
        ]);
        $this->actingAs($user)->post('/checkout/delivery', ['shipping_method_code' => 'UK_STANDARD']);

        $payload = [
            'checkout_token' => session('checkout_token'),
            'payment_method' => PaymentMethodType::BANK_TRANSFER->value,
            'idempotency_key' => 'duplicate-click-key',
        ];

        $this->actingAs($user)->post('/checkout', $payload)->assertRedirect('/checkout/confirmation');
        $this->actingAs($user)->post('/checkout', $payload)->assertRedirect('/checkout/confirmation');

        $this->assertSame(1, Order::query()->where('user_id', $user->id)->count());
    }

    public function test_expired_checkout_session_cannot_create_an_order(): void
    {
        $user = $this->makeCustomer('expire@test.com');
        $product = $this->makeProduct(['name' => 'Expiry Item']);
        $this->actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user)->post('/checkout/details', [
            'shipping_destination' => $this->shippingDestination($user->name),
        ]);
        $this->actingAs($user)->post('/checkout/delivery', ['shipping_method_code' => 'UK_STANDARD']);

        $token = session('checkout_token');
        CheckoutSession::query()->where('token', $token)->update([
            'expires_at' => now()->subMinute(),
            'status' => CheckoutSession::STATUS_EXPIRED,
        ]);

        $this->actingAs($user)
            ->get('/checkout/delivery')
            ->assertRedirect('/cart')
            ->assertSessionHas('error', 'Your checkout session has expired.');

        $this->actingAs($user)
            ->from('/checkout/payment')
            ->post('/checkout', [
                'checkout_token' => $token,
                'payment_method' => PaymentMethodType::BANK_TRANSFER->value,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('checkout');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $product = $this->makeProduct(['name' => 'Owned Item']);
        $owner = $this->makeCustomer('owned@test.com');
        $other = $this->makeCustomer('other@test.com');

        $this->actingAs($owner)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($owner)->post('/checkout/details', [
            'shipping_destination' => $this->shippingDestination($owner->name),
        ]);
        $this->actingAs($owner)->post('/checkout/delivery', ['shipping_method_code' => 'UK_STANDARD']);
        $this->actingAs($owner)->post('/checkout', [
            'checkout_token' => session('checkout_token'),
            'payment_method' => PaymentMethodType::BANK_TRANSFER->value,
            'idempotency_key' => 'ownership-1',
        ]);

        $order = Order::query()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($other)->get('/account/orders/'.$order->id)->assertNotFound();
        $this->actingAs($other)->get('/checkout/confirmation')->assertRedirect('/checkout/payment');
    }

    public function test_payment_proof_stores_object_reference_and_does_not_mark_paid(): void
    {
        Storage::fake('local');
        config(['filesystems.cloud' => 'local', 'filesystems.default' => 'local']);

        $user = $this->makeCustomer('proof@test.com');
        $product = $this->makeProduct(['name' => 'Proof Item', 'price_amount' => 40.00]);
        $this->actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($user)->post('/checkout/details', [
            'shipping_destination' => $this->shippingDestination($user->name),
        ]);
        $this->actingAs($user)->post('/checkout/delivery', ['shipping_method_code' => 'UK_STANDARD']);
        $this->actingAs($user)->post('/checkout', [
            'checkout_token' => session('checkout_token'),
            'payment_method' => PaymentMethodType::BANK_TRANSFER->value,
            'idempotency_key' => 'proof-journey-1',
        ]);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();

        $this->actingAs($user)->post("/orders/{$order->id}/payments/{$payment->id}/proof/bank", [
            'bank_payment_reference' => $order->order_number,
            'sender_account_name' => $user->name,
            'submitted_amount' => $payment->amount,
            'transfer_date' => now()->toDateString(),
            'proof_file' => UploadedFile::fake()->create('receipt.pdf', 80, 'application/pdf'),
        ])->assertRedirect();

        $payment->refresh();
        $this->assertNotNull($payment->submissions()->first()?->proof_file_id);
        $this->assertSame('PAYMENT_SUBMITTED', $payment->status->value);
        $this->assertNotSame('PAID', $payment->status->value);
    }

    public function test_inertia_pages_are_lazy_resolved(): void
    {
        $source = file_get_contents(base_path('resources/js/app.tsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString("import.meta.glob('./Pages/**/*.tsx')", $source);
        $this->assertStringNotContainsString("import.meta.glob('./Pages/**/*.tsx', { eager: true })", $source);
    }
}
