<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Exceptions\InsufficientInventoryException;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Authoritative Inventory Reservation & Concurrency Service.
 *
 * ARCHITECTURAL DECISION:
 * 1. Reservation Phase: During checkout or order creation, inventory records are locked
 *    using database transactions with `lockForUpdate()`. Available stock (quantity_on_hand - quantity_reserved)
 *    is strictly verified. If available, `quantity_reserved` is incremented and an InventoryReservation
 *    record is created with an explicit expiration window.
 * 2. Release Phase: If a payment is rejected, checkout is abandoned, or an order is cancelled,
 *    the reservation is marked RELEASED and `quantity_reserved` is decremented.
 * 3. Permanent Decrement Phase: Permanent deduction from `quantity_on_hand` occurs only once payment
 *    is approved and verified (status transitions to PAID/FULFILLED), guaranteeing that physical stock
 *    is only decremented when revenue is secured and audited.
 */
class InventoryReservationService
{
    /**
     * Default reservation hold duration in minutes.
     */
    public const DEFAULT_RESERVATION_MINUTES = 30;

    /**
     * Reserve inventory for a single item under pessimistic database locking.
     *
     * @throws InsufficientInventoryException
     */
    public function reserveItem(
        Product $product,
        ?ProductVariant $variant = null,
        int $quantity = 1,
        ?Order $order = null,
        ?string $sessionId = null,
        int $durationMinutes = self::DEFAULT_RESERVATION_MINUTES
    ): InventoryReservation {
        if ($quantity <= 0) {
            throw new InsufficientInventoryException('Reservation quantity must be strictly positive.');
        }

        return DB::transaction(function () use ($product, $variant, $quantity, $order, $sessionId, $durationMinutes) {
            $query = Inventory::query();
            if ($variant) {
                $query->where('product_variant_id', $variant->id);
            } else {
                $query->where('product_id', $product->id)->whereNull('product_variant_id');
            }

            /** @var Inventory|null $inventory */
            $inventory = $query->lockForUpdate()->first();

            if (! $inventory) {
                // If no inventory record exists yet, create one with 0 on hand to safely prevent phantom stock
                $inventory = Inventory::create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                    'safety_stock_threshold' => 5,
                ]);
            }

            $available = (int) $inventory->quantity_on_hand - (int) $inventory->quantity_reserved;

            if ($available < $quantity) {
                $itemIdentifier = $variant ? "variant '{$variant->sku}'" : "product '{$product->sku}'";
                throw new InsufficientInventoryException(
                    "Insufficient stock available for {$itemIdentifier}: {$available} available, {$quantity} requested.",
                    $available,
                    $quantity
                );
            }

            // Atomically increment reserved count
            $inventory->quantity_reserved += $quantity;
            $inventory->save();

            // Create inventory movement record for reservation audit
            InventoryMovement::create([
                'inventory_id' => $inventory->id,
                'movement_type' => 'order_reservation',
                'quantity_change' => 0, // on-hand not yet decremented
                'reference_type' => $order ? Order::class : 'checkout_session',
                'reference_id' => $order?->id ?? $sessionId,
                'notes' => "Reserved {$quantity} units for " . ($order ? "Order {$order->order_number}" : "Session {$sessionId}"),
            ]);

            return InventoryReservation::create([
                'inventory_id' => $inventory->id,
                'order_id' => $order?->id,
                'session_id' => $sessionId,
                'quantity' => $quantity,
                'status' => 'ACTIVE',
                'expires_at' => Carbon::now()->addMinutes($durationMinutes),
            ]);
        });
    }

    /**
     * Reserve inventory for multiple items atomically in a single transaction.
     *
     * @param iterable<array{product: Product, variant?: ?ProductVariant, quantity: int}> $items
     * @return Collection<int, InventoryReservation>
     *
     * @throws InsufficientInventoryException
     */
    public function reserveOrderItems(
        iterable $items,
        Order $order,
        int $durationMinutes = self::DEFAULT_RESERVATION_MINUTES
    ): Collection {
        return DB::transaction(function () use ($items, $order, $durationMinutes) {
            $reservations = collect();

            foreach ($items as $item) {
                $reservations->push(
                    $this->reserveItem(
                        product: $item['product'],
                        variant: $item['variant'] ?? null,
                        quantity: (int) $item['quantity'],
                        order: $order,
                        sessionId: null,
                        durationMinutes: $durationMinutes
                    )
                );
            }

            return $reservations;
        });
    }

    /**
     * Release an active reservation.
     */
    public function releaseReservation(InventoryReservation $reservation, string $reason = 'cancelled'): void
    {
        if ($reservation->status !== 'ACTIVE') {
            return;
        }

        DB::transaction(function () use ($reservation, $reason) {
            /** @var Inventory $inventory */
            $inventory = Inventory::query()->lockForUpdate()->findOrFail($reservation->inventory_id);

            $inventory->quantity_reserved = max(0, $inventory->quantity_reserved - $reservation->quantity);
            $inventory->save();

            $reservation->status = 'RELEASED';
            $reservation->save();

            InventoryMovement::create([
                'inventory_id' => $inventory->id,
                'movement_type' => 'adjustment',
                'quantity_change' => 0,
                'reference_type' => InventoryReservation::class,
                'reference_id' => $reservation->id,
                'notes' => "Released {$reservation->quantity} reserved units: {$reason}",
            ]);
        });
    }

    /**
     * Release all reservations for an order (e.g. if order is cancelled or payment rejected).
     */
    public function releaseOrderReservations(Order $order, string $reason = 'order_cancelled'): void
    {
        $reservations = InventoryReservation::query()
            ->where('order_id', $order->id)
            ->where('status', 'ACTIVE')
            ->get();

        foreach ($reservations as $reservation) {
            $this->releaseReservation($reservation, $reason);
        }
    }

    /**
     * Permanently fulfill an order's reservations (decrements quantity_on_hand and logs fulfillment movement).
     */
    public function fulfillOrderReservations(Order $order, ?User $actor = null): void
    {
        DB::transaction(function () use ($order, $actor) {
            $reservations = InventoryReservation::query()
                ->where('order_id', $order->id)
                ->where('status', 'ACTIVE')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                /** @var Inventory $inventory */
                $inventory = Inventory::query()->lockForUpdate()->findOrFail($reservation->inventory_id);

                // Decrement both reserved count and on-hand physical count
                $inventory->quantity_reserved = max(0, $inventory->quantity_reserved - $reservation->quantity);
                $inventory->quantity_on_hand = max(0, $inventory->quantity_on_hand - $reservation->quantity);
                $inventory->save();

                $reservation->status = 'FULFILLED';
                $reservation->save();

                InventoryMovement::create([
                    'inventory_id' => $inventory->id,
                    'movement_type' => 'order_fulfillment',
                    'quantity_change' => -$reservation->quantity,
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'actor_id' => $actor?->id,
                    'notes' => "Fulfilled {$reservation->quantity} units for Order {$order->order_number}",
                ]);
            }
        });
    }

    /**
     * Garbage-collect expired reservations (can be invoked by cron).
     *
     * Re-running is safe: already released rows are ignored.
     *
     * @return array{examined: int, released: int}
     */
    public function releaseExpiredReservations(): array
    {
        $expiredReservations = InventoryReservation::query()
            ->where('status', 'ACTIVE')
            ->where('expires_at', '<', Carbon::now())
            ->get();

        $released = 0;
        foreach ($expiredReservations as $reservation) {
            $this->releaseReservation($reservation, 'expired_reservation_timeout');
            if ($reservation->fresh()?->status === 'RELEASED') {
                $released++;
            }
        }

        return [
            'examined' => $expiredReservations->count(),
            'released' => $released,
        ];
    }
}
