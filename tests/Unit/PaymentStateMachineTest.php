<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\PaymentStatus;
use PHPUnit\Framework\TestCase;

class PaymentStateMachineTest extends TestCase
{
    public function test_valid_transitions_from_pending(): void
    {
        $status = PaymentStatus::PENDING;

        $this->assertTrue($status->canTransitionTo(PaymentStatus::PAYMENT_SUBMITTED));
        $this->assertTrue($status->canTransitionTo(PaymentStatus::EXPIRED));
        $this->assertTrue($status->canTransitionTo(PaymentStatus::REJECTED));
        $this->assertFalse($status->canTransitionTo(PaymentStatus::PAID));
    }

    public function test_valid_transitions_from_submitted(): void
    {
        $status = PaymentStatus::PAYMENT_SUBMITTED;

        $this->assertTrue($status->canTransitionTo(PaymentStatus::UNDER_REVIEW));
        $this->assertTrue($status->canTransitionTo(PaymentStatus::PAID));
        $this->assertTrue($status->canTransitionTo(PaymentStatus::REJECTED));
        $this->assertFalse($status->canTransitionTo(PaymentStatus::EXPIRED));
    }

    public function test_terminal_states_cannot_transition_further(): void
    {
        $expired = PaymentStatus::EXPIRED;
        $refunded = PaymentStatus::REFUNDED;

        $this->assertFalse($expired->canTransitionTo(PaymentStatus::PAID));
        $this->assertFalse($refunded->canTransitionTo(PaymentStatus::PAID));
    }
}
