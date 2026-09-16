<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethodType: string
{
    case BANK_TRANSFER = 'BANK_TRANSFER';
    case CRYPTOCURRENCY = 'CRYPTOCURRENCY';

    public function label(): string
    {
        return match ($this) {
            self::BANK_TRANSFER => 'Manual Bank Transfer',
            self::CRYPTOCURRENCY => 'Manual Cryptocurrency',
        };
    }
}
