<?php

declare(strict_types=1);

namespace App\Exceptions;

class InvalidCheckoutException extends CustomerFacingException
{
    public function __construct(string $internalMessage = 'Checkout payload is invalid.', string $customerMessage = 'Your checkout details are incomplete or invalid. Please review and try again.')
    {
        parent::__construct(
            $internalMessage,
            $customerMessage,
            'INVALID_CHECKOUT'
        );
    }
}
