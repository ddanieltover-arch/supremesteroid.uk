<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Exceptions\DuplicateActivePaymentException;
use App\Exceptions\InvalidCheckoutException;
use App\Exceptions\InvalidOrderStateException;
use App\Exceptions\InvalidPaymentTransitionException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Order\OrderCreationService;
use App\Services\Order\OrderStateMachine;
use App\Services\Payment\PaymentService;
use App\Services\Payment\PaymentStateMachine;
use Database\Seeders\InitialFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class PaymentOrderConsistencyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialFoundationSeeder::class);
    }

    public function test_cannot_mark_order_paid_when_payment_is_rejected(): void
    {
        $order = $this->placeOrder();
        $payment = $order->payments()->first();
        $admin = $this->makeAdmin();

        $machine = app(PaymentStateMachine::class);
        $machine->transition($payment, PaymentStatus::PAYMENT_SUBMITTED, $this->makeCustomer('submitter@test.com'));
        $payment->refresh();
        $machine->transition($payment, PaymentStatus::UNDER_REVIEW, $admin);
        $payment->refresh();
        $machine->transition($payment, PaymentStatus::REJECTED, $admin, 'Bad reference');

        $order->refresh();
        $this->expectException(InvalidOrderStateException::class);
        app(OrderStateMachine::class)->transition($order, \App\Enums\OrderStatus::PAID, $admin);
    }

    public function test_cannot_mark_rejected_payment_as_paid(): void
    {
        $order = $this->placeOrder();
        $payment = $order->payments()->first();
        $admin = $this->makeAdmin();
        $machine = app(PaymentStateMachine::class);

        $machine->transition($payment, PaymentStatus::PAYMENT_SUBMITTED, $order->user);
        $payment->refresh();
        $machine->transition($payment, PaymentStatus::REJECTED, $admin, 'No funds');

        $this->expectException(InvalidPaymentTransitionException::class);
        $machine->transition($payment->fresh(), PaymentStatus::PAID, $admin);
    }

    public function test_cannot_create_payment_for_mismatched_total(): void
    {
        $order = $this->placeOrder();

        $this->expectException(InvalidCheckoutException::class);
        app(PaymentService::class)->createForOrder(
            $order,
            PaymentMethodType::CRYPTOCURRENCY,
            '1.00',
            'GBP'
        );
    }

    public function test_cannot_create_second_active_payment_for_the_same_order(): void
    {
        $order = $this->placeOrder();

        $this->expectException(DuplicateActivePaymentException::class);
        app(PaymentService::class)->createForOrder(
            $order,
            PaymentMethodType::CRYPTOCURRENCY,
            (string) $order->total_amount,
            'GBP'
        );
    }

    public function test_cannot_mark_payment_paid_when_order_is_missing(): void
    {
        $payment = new Payment([
            'method' => PaymentMethodType::BANK_TRANSFER,
            'status' => PaymentStatus::UNDER_REVIEW,
            'amount' => 10.00,
            'currency' => 'GBP',
        ]);
        $payment->order_id = '00000000-0000-0000-0000-000000000000';
        $payment->setRelation('order', null);

        $this->expectException(InvalidPaymentTransitionException::class);
        app(PaymentStateMachine::class)->transition($payment, PaymentStatus::PAID, $this->makeAdmin('ghost@test.com'));
    }

    public function test_proof_submission_does_not_mark_order_paid(): void
    {
        $order = $this->placeOrder();
        $payment = $order->payments()->first();

        app(\App\Actions\Payment\SubmitBankTransferPaymentAction::class)->execute($payment, $order->user, [
            'bank_payment_reference' => 'SS-REF-1',
            'sender_account_name' => 'Alice Customer',
            'submitted_amount' => $order->total_amount,
            'transfer_date' => now()->toDateString(),
        ]);

        $order->refresh();
        $payment->refresh();
        $this->assertEquals(PaymentStatus::PAYMENT_SUBMITTED, $payment->status);
        $this->assertNotEquals(\App\Enums\OrderStatus::PAID, $order->status);
    }

    protected function placeOrder(): Order
    {
        $product = $this->makeProduct(['price_amount' => 40.00]);
        $customer = $this->makeCustomer('pay-' . uniqid() . '@customer.test');

        return app(OrderCreationService::class)->createOrder([
            'customer' => $customer,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'shipping_destination' => $this->shippingDestination($customer->name),
            'shipping_method_code' => 'UK_STANDARD',
            'payment_method' => PaymentMethodType::BANK_TRANSFER,
        ]);
    }
}
