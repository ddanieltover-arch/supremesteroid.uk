<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class InsufficientInventoryException extends RuntimeException
{
    public function __construct(
        string $message = 'Insufficient inventory available.',
        protected int $available = 0,
        protected int $requested = 0,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getAvailable(): int
    {
        return $this->available;
    }

    public function getRequested(): int
    {
        return $this->requested;
    }

    public function getCustomerMessage(): string
    {
        return 'One item in your basket is no longer available in the selected quantity.';
    }

    public function getErrorCode(): string
    {
        return 'OUT_OF_STOCK';
    }
}
