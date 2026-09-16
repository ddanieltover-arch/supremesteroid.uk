<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Exceptions\DuplicateActivePaymentException;
use App\Exceptions\InvalidCheckoutException;
use App\Exceptions\InvalidOrderStateException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Pricing\PricingService;

/**
 * Authoritative payment record boundary.
 *
 * Order → Payment → PaymentMethod
 *
 * Existing methods: BANK_TRANSFER, CRYPTOCURRENCY.
 * Submitting proof never marks an order or payment as PAID.
 * Only authorized payment-review logic may transition UNDER_REVIEW → PAID.
 */
class PaymentService
{
    public function __construct(
        protected PricingService $pricingService
    ) {}
    /**
     * Statuses that count as an "active" payment for an order.
     *
     * @return list<PaymentStatus>
     */
    public static function activeStatuses(): array
    {
        return [
            PaymentStatus::PENDING,
            PaymentStatus::PAYMENT_SUBMITTED,
            PaymentStatus::UNDER_REVIEW,
        ];
    }

    public function createForOrder(
        Order $order,
        PaymentMethodType $method,
        string $amount,
        string $currency = 'GBP',
        ?string $reference = null
    ): Payment {
        if (! $order->exists) {
            throw new InvalidOrderStateException('Cannot create a payment for an order that does not exist.');
        }

        $expectedPence = $this->pricingService->toPence((string) $order->total_amount);
        $providedPence = $this->pricingService->toPence($amount);

        if ($expectedPence <= 0) {
            throw new InvalidCheckoutException(
                "Cannot create a payment for invalid order total '{$order->total_amount}'.",
                'This order total is invalid. Please contact support.'
            );
        }

        if ($providedPence !== $expectedPence) {
            throw new InvalidCheckoutException(
                "Payment amount {$amount} does not match order total {$order->total_amount}.",
                'The payment amount does not match the order total.'
            );
        }

        $hasActive = Payment::query()
            ->where('order_id', $order->id)
            ->whereIn('status', self::activeStatuses())
            ->exists();

        if ($hasActive) {
            throw new DuplicateActivePaymentException();
        }

        return Payment::create([
            'order_id' => $order->id,
            'method' => $method,
            'status' => PaymentStatus::PENDING,
            'amount' => $this->pricingService->toDecimalString($expectedPence),
            'currency' => $currency,
            'reference' => $reference,
            'expires_at' => now()->addDays(3),
        ]);
    }
}
