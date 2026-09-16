<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidOrderStateException;
use App\Exceptions\InvalidPaymentTransitionException;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\InventoryReservationService;
use App\Services\Order\OrderStateMachine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentStateMachine
{
    public function __construct(
        protected OrderStateMachine $orderStateMachine,
        protected InventoryReservationService $reservationService,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Transition a payment to a new status with validation and audit logging.
     *
     * Submitting proof never marks a payment PAID. Only authorized review
     * logic may move UNDER_REVIEW (or PAYMENT_SUBMITTED) → PAID.
     *
     * @throws InvalidPaymentTransitionException
     */
    public function transition(Payment $payment, PaymentStatus $newStatus, ?User $actor = null, ?string $reason = null): Payment
    {
        $payment->loadMissing('order');

        if ($newStatus === PaymentStatus::PAID && $payment->order === null) {
            throw new InvalidPaymentTransitionException('Cannot mark payment as PAID for an order that does not exist.');
        }

        if ($newStatus === PaymentStatus::PAID && $payment->status === PaymentStatus::REJECTED) {
            throw new InvalidPaymentTransitionException('Cannot mark a rejected payment as PAID.');
        }

        if ($newStatus === PaymentStatus::PAID && $payment->order?->status === OrderStatus::CANCELLED) {
            throw new InvalidPaymentTransitionException('Cannot mark payment as PAID for a cancelled order.');
        }

        $currentStatus = $payment->status;

        if (! $currentStatus->canTransitionTo($newStatus)) {
            throw new InvalidPaymentTransitionException(
                "Illegal payment status transition from {$currentStatus->name} to {$newStatus->name}."
            );
        }

        return DB::transaction(function () use ($payment, $currentStatus, $newStatus, $actor, $reason) {
            $payment->status = $newStatus;

            if ($newStatus === PaymentStatus::PAID) {
                $payment->paid_at = now();
            }

            $payment->save();

            if ($payment->order) {
                if ($newStatus === PaymentStatus::PAYMENT_SUBMITTED || $newStatus === PaymentStatus::UNDER_REVIEW) {
                    $this->orderStateMachine->transition(
                        $payment->order,
                        OrderStatus::PAYMENT_REVIEW,
                        $actor,
                        $reason ?? 'Customer submitted payment confirmation'
                    );
                } elseif ($newStatus === PaymentStatus::PAID) {
                    $this->orderStateMachine->transition(
                        $payment->order,
                        OrderStatus::PAID,
                        $actor,
                        $reason ?? 'Payment verified and marked as paid by admin'
                    );

                    $this->reservationService->fulfillOrderReservations($payment->order, $actor);
                } elseif ($newStatus === PaymentStatus::REJECTED) {
                    $this->orderStateMachine->transition(
                        $payment->order,
                        OrderStatus::PENDING_PAYMENT,
                        $actor,
                        $reason ?? 'Payment submission rejected. Awaiting correct details.'
                    );

                    $this->reservationService->releaseOrderReservations($payment->order, $reason ?? 'payment_rejected');
                } elseif ($newStatus === PaymentStatus::EXPIRED) {
                    $this->reservationService->releaseOrderReservations($payment->order, 'payment_expired');
                }
            }

            if (in_array($newStatus, [PaymentStatus::PAID, PaymentStatus::REJECTED, PaymentStatus::REFUNDED], true)) {
                $event = match ($newStatus) {
                    PaymentStatus::PAID => 'payment_approved',
                    PaymentStatus::REJECTED => 'payment_rejected',
                    PaymentStatus::REFUNDED => 'refund',
                    default => 'payment_updated',
                };

                $this->auditLogger->log(
                    eventType: $event,
                    auditable: $payment,
                    oldValues: ['status' => $currentStatus->value],
                    newValues: ['status' => $newStatus->value, 'order_id' => $payment->order_id],
                    actor: $actor,
                    reason: $reason
                );

                if (in_array($newStatus, [PaymentStatus::PAID, PaymentStatus::REJECTED], true)) {
                    $reviewed = $payment;
                    DB::afterCommit(function () use ($reviewed, $newStatus) {
                        app(\App\Services\Mail\CustomerMailer::class)->paymentReviewed(
                            $reviewed,
                            $newStatus === PaymentStatus::PAID
                        );
                    });
                }
            }

            return $payment;
        });
    }

    /**
     * Process staff review of a payment submission.
     */
    public function reviewSubmission(
        PaymentSubmission $submission,
        bool $approved,
        User $reviewer,
        string $adminNotes
    ): PaymentSubmission {
        return DB::transaction(function () use ($submission, $approved, $reviewer, $adminNotes) {
            $submission->loadMissing('payment.order');

            if ($submission->payment === null) {
                throw new InvalidOrderStateException('Payment submission is not attached to a payment.');
            }

            $submission->reviewed_by = $reviewer->id;
            $submission->reviewed_at = now();
            $submission->admin_notes = $adminNotes;
            $submission->review_status = $approved ? PaymentStatus::PAID : PaymentStatus::REJECTED;
            $submission->save();

            $this->transition(
                $submission->payment,
                $submission->review_status,
                $reviewer,
                $adminNotes
            );

            Log::info('Payment submission reviewed', [
                'submission_id' => $submission->id,
                'payment_id' => $submission->payment_id,
                'approved' => $approved,
                'reviewer_id' => $reviewer->id,
            ]);

            return $submission;
        });
    }
}
