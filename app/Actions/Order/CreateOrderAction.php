<?php

declare(strict_types=1);

namespace App\Actions\Order;

use App\Enums\PaymentMethodType;
use App\Models\Order;
use App\Services\Order\OrderCreationService;
use InvalidArgumentException;

class CreateOrderAction
{
    public function __construct(
        protected OrderCreationService $orderCreationService
    ) {}

    /**
     * @param array{
     *     user: \App\Models\User,
     *     items: list<array{product_id: string, quantity: int, variant_id?: ?string}>,
     *     shipping_destination: array<string, mixed>,
     *     shipping_method_code?: ?string,
     *     billing_destination?: ?array<string, mixed>,
     *     payment_method: PaymentMethodType|string,
     *     idempotency_key?: ?string,
     *     checkout_token?: ?string,
     *     customer_notes?: ?string
     * } $data
     */
    public function execute(array $data): Order
    {
        if (empty($data['shipping_destination']['address_line_1'] ?? null) && empty($data['checkout_token'] ?? null)) {
            throw new InvalidArgumentException(
                'Order creation requires a server-validated shipping destination or checkout token. Client-supplied totals are not accepted.'
            );
        }

        $paymentMethod = $data['payment_method'] ?? null;
        if (! $paymentMethod instanceof PaymentMethodType && ! is_string($paymentMethod)) {
            throw new InvalidArgumentException('A payment method is required.');
        }

        return $this->orderCreationService->createOrder([
            'customer' => $data['user'] ?? $data['customer'] ?? null,
            'items' => $data['items'] ?? [],
            'shipping_destination' => $data['shipping_destination'] ?? [],
            'shipping_method_code' => $data['shipping_method_code'] ?? 'UK_STANDARD',
            'billing_destination' => $data['billing_destination'] ?? null,
            'payment_method' => $paymentMethod,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'checkout_token' => $data['checkout_token'] ?? null,
            'customer_notes' => $data['customer_notes'] ?? null,
        ]);
    }
}
