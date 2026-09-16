<?php

declare(strict_types=1);

namespace App\Exceptions;

class InvalidOrderStateException extends CustomerFacingException
{
    public function __construct(string $internalMessage, string $customerMessage = 'This order cannot be updated in its current state.')
    {
        parent::__construct(
            $internalMessage,
            $customerMessage,
            'INVALID_ORDER_STATE'
        );
    }
}
