<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Mail\CustomerMessage;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\Payment\PaymentStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class PaymentFlowEmailVerificationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    public function test_checkout_proof_and_admin_approve_send_customer_emails_and_fulfill_stock(): void
    {
        Mail::fake();

        $user = $this->makeCustomer('flow-approve@test.com');
        $admin = $this->makeAdmin('flow-approve-admin@test.com');
        $product = $this->makePurchasableProduct('Approve Flow Item', 120.00, stock: 10);

        [$order, $payment] = $this->placeBankOrder($user, $product, 'flow-approve-1');

        $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->status);
        $this->assertSame(PaymentStatus::PENDING, $payment->status);

        Mail::assertSent(CustomerMessage::class, function (CustomerMessage $mail) use ($order) {
            return str_contains($mail->subjectLine, 'Order '.$order->order_number.' received')
                && str_contains($mail->bodyText, 'pending review')
                && ! str_contains(strtolower($mail->bodyText), 'has been paid');
        });

        $inventory = Inventory::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame(1, (int) $inventory->quantity_reserved);
        $this->assertSame(10, (int) $inventory->quantity_on_hand);

        $this->submitBankProof($user, $order, $payment);

        $payment->refresh();
        $order->refresh();
        $this->assertSame(PaymentStatus::PAYMENT_SUBMITTED, $payment->status);
        $this->assertSame(OrderStatus::PAYMENT_REVIEW, $order->status);

        Mail::assertSent(CustomerMessage::class, function (CustomerMessage $mail) use ($order) {
            return str_contains($mail->subjectLine, 'Payment submission received for '.$order->order_number)
                && str_contains($mail->bodyText, 'Staff must review');
        });

        app(PaymentStateMachine::class)->transition(
            $payment->fresh(),
            PaymentStatus::PAID,
            $admin,
            'Approved in payment flow email coverage'
        );

        $payment->refresh();
        $order->refresh();
        $inventory->refresh();

        $this->assertSame(PaymentStatus::PAID, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(OrderStatus::PAID, $order->status);
        $this->assertSame(9, (int) $inventory->quantity_on_hand);
        $this->assertSame(0, (int) $inventory->quantity_reserved);

        Mail::assertSent(CustomerMessage::class, function (CustomerMessage $mail) use ($order) {
            return str_contains($mail->subjectLine, 'Payment approved for '.$order->order_number)
                && str_contains($mail->bodyText, 'was approved by staff');
        });
    }

    public function test_checkout_proof_and_admin_reject_send_customer_emails_and_release_stock(): void
    {
        Mail::fake();

        $user = $this->makeCustomer('flow-reject@test.com');
        $admin = $this->makeAdmin('flow-reject-admin@test.com');
        $product = $this->makePurchasableProduct('Reject Flow Item', 120.00, stock: 10);

        [$order, $payment] = $this->placeBankOrder($user, $product, 'flow-reject-1');

        Mail::assertSent(CustomerMessage::class, function (CustomerMessage $mail) use ($order) {
            return str_contains($mail->subjectLine, 'Order '.$order->order_number.' received');
        });

        $this->submitBankProof($user, $order, $payment);

        Mail::assertSent(CustomerMessage::class, function (CustomerMessage $mail) use ($order) {
            return str_contains($mail->subjectLine, 'Payment submission received for '.$order->order_number);
        });

        $inventory = Inventory::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame(1, (int) $inventory->quantity_reserved);
        $this->assertSame(10, (int) $inventory->quantity_on_hand);

        app(PaymentStateMachine::class)->transition(
            $payment->fresh(),
            PaymentStatus::REJECTED,
            $admin,
            'Rejected in payment flow email coverage'
        );

        $payment->refresh();
        $order->refresh();
        $inventory->refresh();

        $this->assertSame(PaymentStatus::REJECTED, $payment->status);
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->status);
        $this->assertSame(10, (int) $inventory->quantity_on_hand);
        $this->assertSame(0, (int) $inventory->quantity_reserved);

        Mail::assertSent(CustomerMessage::class, function (CustomerMessage $mail) use ($order) {
            return str_contains($mail->subjectLine, 'Payment not approved for '.$order->order_number)
                && str_contains($mail->bodyText, 'was not approved');
        });
    }

    protected function makePurchasableProduct(string $name, float $price, int $stock = 10): Product
    {
        $product = $this->makeProduct(['name' => $name, 'price_amount' => $price], stock: $stock);

        ProductImage::query()->create([
            'product_id' => $product->id,
            'url' => '/storage/product_images/'.$product->id.'/primary.jpg',
            'alt_text' => $product->name,
            'display_order' => 0,
            'is_primary' => true,
        ]);

        return $product->fresh(['inventory', 'images']);
    }

    /**
     * @return array{0: Order, 1: Payment}
     */
    protected function placeBankOrder(User $user, Product $product, string $idempotencyKey): array
    {
        $this->actingAs($user)->post('/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        $this->actingAs($user)->post('/checkout/details', [
            'shipping_destination' => array_merge($this->shippingDestination($user->name), [
                'phone' => '+441234567890',
            ]),
        ])->assertRedirect('/checkout/delivery');
        $this->actingAs($user)->post('/checkout/delivery', [
            'shipping_method_code' => 'UK_STANDARD',
        ])->assertRedirect('/checkout/payment');
        $this->actingAs($user)
            ->from('/checkout/payment')
            ->post('/checkout/payment', [
                'payment_method' => PaymentMethodType::BANK_TRANSFER->value,
            ])
            ->assertRedirect('/checkout/payment');
        $this->actingAs($user)->post('/checkout', [
            'checkout_token' => session('checkout_token'),
            'payment_method' => PaymentMethodType::BANK_TRANSFER->value,
            'idempotency_key' => $idempotencyKey,
        ])->assertRedirect('/checkout/confirmation');

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();

        return [$order, $payment];
    }

    protected function submitBankProof(User $user, Order $order, Payment $payment): void
    {
        $this->actingAs($user)->post("/orders/{$order->id}/payments/{$payment->id}/proof/bank", [
            'bank_payment_reference' => $order->order_number,
            'sender_account_name' => $user->name,
            'submitted_amount' => $payment->amount,
            'transfer_date' => now()->toDateString(),
        ])->assertRedirect();
    }
}
