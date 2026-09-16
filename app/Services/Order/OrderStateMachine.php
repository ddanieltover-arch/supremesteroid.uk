<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidOrderStateException;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Mail\CustomerMailer;
use App\Services\Inventory\InventoryReservationService;
use Illuminate\Support\Facades\DB;

class OrderStateMachine
{
    public function __construct(
        protected InventoryReservationService $reservationService,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Transition an order to a new status and record auditable history.
     */
    public function transition(Order $order, OrderStatus $newStatus, ?User $actor = null, ?string $notes = null): Order
    {
        $currentStatus = $order->status;

        if ($currentStatus === $newStatus) {
            return $order;
        }

        if (! $currentStatus->canTransitionTo($newStatus)) {
            throw new InvalidOrderStateException(
                "Illegal order status transition from {$currentStatus->name} to {$newStatus->name}."
            );
        }

        if ($newStatus === OrderStatus::PAID) {
            $hasPaidPayment = $order->payments()
                ->where('status', PaymentStatus::PAID)
                ->exists();

            $hasRejectedOnly = $order->payments()
                ->where('status', PaymentStatus::REJECTED)
                ->exists()
                && ! $hasPaidPayment;

            if ($hasRejectedOnly) {
                throw new InvalidOrderStateException(
                    'Cannot mark order PAID when payment is rejected.',
                    'This order cannot be marked as paid because the payment was rejected.'
                );
            }

            if (! $hasPaidPayment) {
                throw new InvalidOrderStateException(
                    'Cannot mark order PAID without a verified payment record.',
                    'This order cannot be marked as paid until payment is verified by staff.'
                );
            }
        }

        return DB::transaction(function () use ($order, $currentStatus, $newStatus, $actor, $notes) {
            $order->status = $newStatus;
            $order->save();

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'previous_status' => $currentStatus->value,
                'new_status' => $newStatus->value,
                'actor_id' => $actor?->id,
                'notes' => $notes,
            ]);

            if ($newStatus === OrderStatus::CANCELLED) {
                $this->reservationService->releaseOrderReservations($order, $notes ?? 'Order cancelled');
                $this->auditLogger->log(
                    eventType: 'order_cancellation',
                    auditable: $order,
                    oldValues: ['status' => $currentStatus->value],
                    newValues: ['status' => $newStatus->value],
                    actor: $actor,
                    reason: $notes
                );
            }

            if ($newStatus === OrderStatus::REFUNDED) {
                $this->auditLogger->log(
                    eventType: 'refund',
                    auditable: $order,
                    oldValues: ['status' => $currentStatus->value],
                    newValues: ['status' => $newStatus->value],
                    actor: $actor,
                    reason: $notes
                );
            }

            if ($newStatus === OrderStatus::SHIPPED) {
                DB::afterCommit(function () use ($order) {
                    app(\App\Services\Mail\CustomerMailer::class)->orderShipped($order);
                });
            }

            return $order;
        });
    }
}
