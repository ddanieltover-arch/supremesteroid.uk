<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Models\Address;
use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Checkout\CheckoutSessionService;
use App\Services\Inventory\InventoryReservationService;
use App\Services\Payment\PaymentService;
use App\Services\Pricing\PricingService;
use App\Services\Shipping\ShippingEngine;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class OrderCreationService
{
    public function __construct(
        protected CheckoutSessionService $checkoutSessionService,
        protected InventoryReservationService $inventoryReservationService,
        protected PricingService $pricingService,
        protected ShippingEngine $shippingEngine,
        protected AuditLogger $auditLogger,
        protected PaymentService $paymentService
    ) {}

    /**
     * Authoritative, atomic creation of an order with inventory reservation, idempotency, and historical snapshots.
     *
     * Inventory is reserved inside this transaction at order creation (checkout confirmation),
     * not when a cart item is added. Permanent quantity_on_hand decrement happens only when
     * payment is approved (PAID) via InventoryReservationService::fulfillOrderReservations().
     *
     * @param array{
     *     customer: User,
     *     items?: list<array{product_id: string, variant_id?: ?string, quantity: int}>,
     *     shipping_destination?: array<string, mixed>,
     *     shipping_method_code?: ?string,
     *     billing_destination?: ?array<string, mixed>,
     *     payment_method: PaymentMethodType|string,
     *     idempotency_key?: ?string,
     *     checkout_token?: ?string,
     *     customer_notes?: ?string
     * } $data
     */
    public function createOrder(array $data): Order
    {
        $user = $data['customer'];
        $idempotencyKey = ! empty($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null;

        if ($idempotencyKey !== null) {
            /** @var Order|null $existingOrder */
            $existingOrder = Order::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->with(['items', 'payments', 'shippingAddress', 'statusHistory'])
                ->first();

            if ($existingOrder) {
                return $existingOrder;
            }
        }

        try {
            return DB::transaction(function () use ($data, $user, $idempotencyKey) {
                if ($idempotencyKey !== null) {
                    /** @var Order|null $locked */
                    $locked = Order::query()
                        ->where('user_id', $user->id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();

                    if ($locked) {
                        return $locked->load(['items', 'payments', 'shippingAddress', 'statusHistory']);
                    }
                }

                $checkoutSession = null;
                if (! empty($data['checkout_token'])) {
                    /** @var CheckoutSession $checkoutSession */
                    $checkoutSession = CheckoutSession::query()
                        ->where('token', $data['checkout_token'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    $checkoutSnapshot = $this->checkoutSessionService->revalidateStoredSnapshot($checkoutSession, $user);
                } else {
                    $checkoutSnapshot = $this->checkoutSessionService->prepareCheckout([
                        'customer' => $user,
                        'items' => $data['items'] ?? [],
                        'shipping_destination' => $data['shipping_destination'] ?? [],
                        'shipping_method_code' => $data['shipping_method_code'] ?? 'UK_STANDARD',
                        'billing_destination' => $data['billing_destination'] ?? null,
                        'customer_notes' => $data['customer_notes'] ?? null,
                    ], persist: false);
                }

                $orderNumber = $this->generateSequentialOrderNumber();

                $shippingDest = $checkoutSnapshot['shipping_destination'];
                $shippingAddress = Address::create([
                    'user_id' => $user->id,
                    'type' => 'shipping',
                    'full_name' => $shippingDest['full_name'],
                    'address_line_1' => $shippingDest['address_line_1'],
                    'address_line_2' => $shippingDest['address_line_2'],
                    'city' => $shippingDest['city'],
                    'state_county' => $shippingDest['state_county'],
                    'postal_code' => $shippingDest['postal_code'],
                    'country_code' => $shippingDest['country_code'],
                    'phone' => $shippingDest['phone'],
                ]);

                $billingDest = $checkoutSnapshot['billing_destination'] ?? $shippingDest;
                $billingAddress = Address::create([
                    'user_id' => $user->id,
                    'type' => 'billing',
                    'full_name' => $billingDest['full_name'],
                    'address_line_1' => $billingDest['address_line_1'],
                    'address_line_2' => $billingDest['address_line_2'],
                    'city' => $billingDest['city'],
                    'state_county' => $billingDest['state_county'],
                    'postal_code' => $billingDest['postal_code'],
                    'country_code' => $billingDest['country_code'],
                    'phone' => $billingDest['phone'],
                ]);

                $totals = $checkoutSnapshot['totals'];
                $shippingMethod = $checkoutSnapshot['shipping_method'];

                /** @var Order $order */
                $order = Order::create([
                    'order_number' => $orderNumber,
                    'idempotency_key' => $idempotencyKey,
                    'user_id' => $user->id,
                    'customer_name_snapshot' => $user->name,
                    'customer_email_snapshot' => $user->email,
                    'status' => OrderStatus::PENDING_PAYMENT,
                    'subtotal_amount' => $totals['subtotal_amount'],
                    'shipping_amount' => $totals['shipping_amount'],
                    'discount_amount' => $totals['discount_amount'],
                    'tax_amount' => $totals['tax_amount'],
                    'total_amount' => $totals['grand_total_amount'],
                    'currency' => $totals['currency'],
                    'shipping_method_id' => is_string($shippingMethod['id']) && strlen($shippingMethod['id']) === 36 ? $shippingMethod['id'] : null,
                    'shipping_method_name_snapshot' => $shippingMethod['name'] . ' (' . $shippingMethod['code'] . ')',
                    'shipping_address_id' => $shippingAddress->id,
                    'billing_address_id' => $billingAddress->id,
                    'shipping_address_snapshot' => $shippingDest,
                    'billing_address_snapshot' => $billingDest,
                    'customer_notes' => $data['customer_notes'] ?? $checkoutSnapshot['customer_notes'] ?? null,
                ]);

                $itemsToReserve = [];

                foreach ($checkoutSnapshot['items'] as $itemSnapshot) {
                    /** @var Product $product */
                    $product = Product::query()->findOrFail($itemSnapshot['product_id']);
                    $variant = ! empty($itemSnapshot['variant_id'])
                        ? ProductVariant::query()->findOrFail($itemSnapshot['variant_id'])
                        : null;

                    $order->items()->create([
                        'product_id' => $product->id,
                        'product_variant_id' => $variant?->id,
                        'product_name' => $itemSnapshot['product_name'],
                        'product_sku' => $itemSnapshot['product_sku'],
                        'variant_name_snapshot' => $itemSnapshot['variant_name'],
                        'unit_price' => $itemSnapshot['unit_price'],
                        'quantity' => $itemSnapshot['quantity'],
                        'line_subtotal' => $itemSnapshot['line_subtotal'],
                        'discount_amount' => $itemSnapshot['discount'],
                        'tax_amount' => $itemSnapshot['tax'],
                        'line_total' => $itemSnapshot['line_total'],
                        'total_price' => $itemSnapshot['line_total'],
                    ]);

                    $itemsToReserve[] = [
                        'product' => $product,
                        'variant' => $variant,
                        'quantity' => $itemSnapshot['quantity'],
                    ];
                }

                $this->inventoryReservationService->reserveOrderItems($itemsToReserve, $order);

                $paymentMethod = $data['payment_method'] instanceof PaymentMethodType
                    ? $data['payment_method']
                    : PaymentMethodType::from((string) $data['payment_method']);

                $this->paymentService->createForOrder(
                    $order,
                    $paymentMethod,
                    $totals['grand_total_amount'],
                    $totals['currency']
                );

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'previous_status' => 'NONE',
                    'new_status' => OrderStatus::PENDING_PAYMENT->value,
                    'actor_id' => $user->id,
                    'notes' => 'Order placed by customer with automated inventory reservation.',
                ]);

                $this->auditLogger->log(
                    eventType: 'order_created',
                    auditable: $order,
                    oldValues: null,
                    newValues: [
                        'order_number' => $order->order_number,
                        'total_amount' => $order->total_amount,
                        'items_count' => count($itemsToReserve),
                    ],
                    actor: $user,
                    reason: 'Customer completed checkout pipeline.'
                );

                if ($checkoutSession) {
                    $checkoutSession->status = CheckoutSession::STATUS_CONVERTED;
                    $checkoutSession->order_id = $order->id;
                    $checkoutSession->save();
                }

                $loaded = $order->load(['items', 'payments', 'shippingAddress', 'billingAddress', 'statusHistory']);
                DB::afterCommit(function () use ($loaded) {
                    app(\App\Services\Mail\CustomerMailer::class)->orderPlaced($loaded);
                });

                return $loaded;
            });
        } catch (UniqueConstraintViolationException $e) {
            if ($idempotencyKey !== null) {
                /** @var Order|null $existing */
                $existing = Order::query()
                    ->where('user_id', $user->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->with(['items', 'payments', 'shippingAddress', 'statusHistory'])
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            throw $e;
        }
    }

    /**
     * Generate customer-friendly sequential order number: SS-YYYY-000001.
     */
    public function generateSequentialOrderNumber(): string
    {
        $year = (int) date('Y');
        $prefix = "SS-{$year}-";

        $latest = Order::query()
            ->where('order_number', 'like', "{$prefix}%")
            ->orderBy('order_number', 'desc')
            ->lockForUpdate()
            ->first();

        if ($latest) {
            $existingSeqStr = substr($latest->order_number, strlen($prefix));
            $seq = (int) $existingSeqStr;
            $nextSeq = $seq + 1;
        } else {
            $nextSeq = 1;
        }

        do {
            $candidate = sprintf('%s%06d', $prefix, $nextSeq);
            $exists = Order::query()->where('order_number', $candidate)->exists();
            if ($exists) {
                $nextSeq++;
            }
        } while ($exists);

        return $candidate;
    }
}
