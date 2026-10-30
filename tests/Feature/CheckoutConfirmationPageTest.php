<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Mail\CustomerMessage;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Mail\CustomerMailer;
use App\Services\Payment\PaymentStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class CheckoutConfirmationPageTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    public function test_confirmation_is_noindex_and_shows_pending_payment(): void
    {
        $user = $this->makeCustomer('confirm@test.com');
        $this->placeOrder($user, 'confirm-pending-1');

        $this->actingAs($user)
            ->get('/checkout/confirmation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Confirmation')
                ->where('seo.robots', 'noindex,nofollow')
                ->where('order.payments.0.status', PaymentStatus::PENDING->value)
                ->where('order.payments.0.status_label', 'Payment pending')
            );

        $this->assertSame(1, Order::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_confirmation_refresh_keeps_the_same_order(): void
    {
        $user = $this->makeCustomer('refresh@test.com');
        $this->placeOrder($user, 'confirm-refresh-1');

        $orderNumber = Order::query()->where('user_id', $user->id)->value('order_number');
        $this->assertIsString($orderNumber);

        $this->actingAs($user)->get('/checkout/confirmation')->assertOk();

        $this->actingAs($user)
            ->get('/checkout/confirmation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Confirmation')
                ->where('order.order_number', $orderNumber)
            );

        $this->actingAs($user)->get('/checkout/payment')->assertRedirect('/checkout/confirmation');
        $this->actingAs($user)->post('/checkout', [
            'checkout_token' => session('checkout_token'),
            'payment_method' => PaymentMethodType::BANK_TRANSFER->value,
            'idempotency_key' => 'confirm-refresh-1',
        ]);

        $this->assertSame(1, Order::query()->where('user_id', $user->id)->count());
    }

    public function test_confirmation_is_owned_by_the_placing_customer(): void
    {
        $owner = $this->makeCustomer('owner-confirm@test.com', 'Owner Confirm');
        $other = $this->makeCustomer('other-confirm@test.com', 'Other Confirm');
        $this->placeOrder($owner, 'confirm-owner-1');
        $order = Order::query()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($other)->get('/checkout/confirmation')->assertRedirect('/checkout/payment');
        $this->actingAs($other)->get('/account/orders/'.$order->id)->assertNotFound();
        $this->actingAs($other)->get('/orders/'.$order->id.'/payments/'.$order->payments()->firstOrFail()->id.'/proof')->assertNotFound();
    }

    public function test_confirmation_shows_paid_only_after_state_machine_approval(): void
    {
        Storage::fake('local');
        config(['filesystems.cloud' => 'local', 'filesystems.default' => 'local']);

        $user = $this->makeCustomer('paid-confirm@test.com');
        $admin = $this->makeAdmin('paid-admin@test.com');
        $this->placeOrder($user, 'confirm-paid-1');
        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();

        $this->actingAs($user)
            ->get('/checkout/confirmation')
            ->assertInertia(fn (Assert $page) => $page->where('order.payments.0.status', PaymentStatus::PENDING->value));

        $this->actingAs($user)->post("/orders/{$order->id}/payments/{$payment->id}/proof/bank", [
            'bank_payment_reference' => $order->order_number,
            'sender_account_name' => $user->name,
            'submitted_amount' => $payment->amount,
            'transfer_date' => now()->toDateString(),
            'proof_file' => UploadedFile::fake()->create('receipt.pdf', 80, 'application/pdf'),
        ])->assertRedirect();

        $payment->refresh();
        $this->assertSame(PaymentStatus::PAYMENT_SUBMITTED, $payment->status);

        app(PaymentStateMachine::class)->transition(
            $payment->fresh(),
            PaymentStatus::PAID,
            $admin,
            'Approved in confirmation coverage'
        );

        $this->actingAs($user)
            ->get('/checkout/confirmation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Confirmation')
                ->where('order.payments.0.status', PaymentStatus::PAID->value)
                ->where('order.payments.0.status_label', 'Payment approved')
            );
    }

    public function test_account_and_order_pages_are_noindex(): void
    {
        $user = $this->makeCustomer('account-seo@test.com');
        $this->placeOrder($user, 'confirm-account-seo-1');
        $order = Order::query()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'noindex,nofollow'));

        $this->actingAs($user)
            ->get('/account/orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'noindex,nofollow'));

        $this->actingAs($user)
            ->get('/account/orders/'.$order->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'noindex,nofollow'));
    }

    public function test_order_placed_mail_does_not_claim_payment_is_complete(): void
    {
        Mail::fake();
        $user = $this->makeCustomer('mail-confirm@test.com');
        $this->placeOrder($user, 'confirm-mail-1');
        $order = Order::query()->where('user_id', $user->id)->firstOrFail();

        app(CustomerMailer::class)->orderPlaced($order);

        Mail::assertSent(CustomerMessage::class, function (CustomerMessage $mail) use ($order) {
            $combined = strtolower($mail->bodyText.' '.implode(' ', $mail->bodyLines));

            return str_contains($mail->subjectLine, $order->order_number)
                && str_contains($combined, 'pending review')
                && ! str_contains($combined, 'has been paid')
                && $mail->orderNumber === $order->order_number
                && is_string($mail->actionUrl)
                && str_contains($mail->actionUrl, '/orders/'.$order->id);
        });
    }

    protected function placeOrder(object $user, string $idempotencyKey): void
    {
        $product = $this->makeProduct(['name' => 'Confirmation Sample', 'price_amount' => 45.00]);
        $this->actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($user)->post('/checkout/details', [
            'shipping_destination' => $this->shippingDestination($user->name),
        ]);
        $this->actingAs($user)->post('/checkout/delivery', ['shipping_method_code' => 'UK_STANDARD']);
        $this->actingAs($user)->post('/checkout', [
            'checkout_token' => session('checkout_token'),
            'payment_method' => PaymentMethodType::BANK_TRANSFER->value,
            'idempotency_key' => $idempotencyKey,
        ])->assertRedirect('/checkout/confirmation');
    }
}
