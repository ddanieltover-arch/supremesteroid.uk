<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING_PAYMENT = 'PENDING_PAYMENT';
    case PAYMENT_REVIEW = 'PAYMENT_REVIEW';
    case PAID = 'PAID';
    case PROCESSING = 'PROCESSING';
    case SHIPPED = 'SHIPPED';
    case DELIVERED = 'DELIVERED';
    case CANCELLED = 'CANCELLED';
    case REFUNDED = 'REFUNDED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_PAYMENT => 'Pending Payment',
            self::PAYMENT_REVIEW => 'Payment Under Review',
            self::PAID => 'Payment Confirmed',
            self::PROCESSING => 'Processing Order',
            self::SHIPPED => 'Dispatched / Shipped',
            self::DELIVERED => 'Delivered',
            self::CANCELLED => 'Cancelled',
            self::REFUNDED => 'Refunded',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::PENDING_PAYMENT => in_array($target, [self::PAYMENT_REVIEW, self::PAID, self::CANCELLED], true),
            self::PAYMENT_REVIEW => in_array($target, [self::PAID, self::PENDING_PAYMENT, self::CANCELLED], true),
            self::PAID => in_array($target, [self::PROCESSING, self::REFUNDED, self::CANCELLED], true),
            self::PROCESSING => in_array($target, [self::SHIPPED, self::REFUNDED, self::CANCELLED], true),
            self::SHIPPED => in_array($target, [self::DELIVERED, self::REFUNDED], true),
            self::DELIVERED => in_array($target, [self::REFUNDED], true),
            self::CANCELLED, self::REFUNDED => false,
        };
    }
}
