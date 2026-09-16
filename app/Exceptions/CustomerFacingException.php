<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class CustomerFacingException extends RuntimeException
{
    public function __construct(
        string $internalMessage,
        protected string $customerMessage = 'We could not complete that request. Please review your details and try again.',
        protected string $errorCode = 'CHECKOUT_ERROR',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($internalMessage, $code, $previous);
    }

    public function getCustomerMessage(): string
    {
        return $this->customerMessage;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
