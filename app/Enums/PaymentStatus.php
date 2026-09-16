<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case PAYMENT_SUBMITTED = 'PAYMENT_SUBMITTED';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case PAID = 'PAID';
    case REJECTED = 'REJECTED';
    case EXPIRED = 'EXPIRED';
    case REFUNDED = 'REFUNDED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Awaiting Payment Submission',
            self::PAYMENT_SUBMITTED => 'Payment Submitted by Customer',
            self::UNDER_REVIEW => 'Under Staff Review',
            self::PAID => 'Payment Verified & Approved',
            self::REJECTED => 'Payment Rejected',
            self::EXPIRED => 'Payment Window Expired',
            self::REFUNDED => 'Refunded',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::PENDING => in_array($target, [self::PAYMENT_SUBMITTED, self::EXPIRED, self::REJECTED], true),
            self::PAYMENT_SUBMITTED => in_array($target, [self::UNDER_REVIEW, self::PAID, self::REJECTED], true),
            self::UNDER_REVIEW => in_array($target, [self::PAID, self::REJECTED, self::PENDING], true),
            self::PAID => in_array($target, [self::REFUNDED], true),
            self::REJECTED => in_array($target, [self::PENDING, self::EXPIRED], true),
            self::EXPIRED, self::REFUNDED => false,
        };
    }
}
