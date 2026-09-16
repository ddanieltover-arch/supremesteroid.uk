<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

class InvalidPriceException extends InvalidArgumentException
{
    public function getCustomerMessage(): string
    {
        return 'An item price is invalid or has changed. Please review your cart and try again.';
    }

    public function getErrorCode(): string
    {
        return 'INVALID_PRICE';
    }
}
