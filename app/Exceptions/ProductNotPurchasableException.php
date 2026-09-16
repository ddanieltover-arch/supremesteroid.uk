<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class ProductNotPurchasableException extends RuntimeException
{
    /**
     * @param list<string> $reasons
     */
    public function __construct(
        string $message,
        protected array $reasons = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return list<string>
     */
    public function getReasons(): array
    {
        return $this->reasons;
    }

    public function getCustomerMessage(): string
    {
        return 'This product is currently unavailable for purchase.';
    }

    public function getErrorCode(): string
    {
        return 'PRODUCT_UNAVAILABLE';
    }
}
