<?php

declare(strict_types=1);

namespace App\Exceptions;

class DuplicateActivePaymentException extends CustomerFacingException
{
    public function __construct(string $internalMessage = 'An active payment already exists for this order.')
    {
        parent::__construct(
            $internalMessage,
            'This order already has an active payment. Please wait for review or contact support.',
            'DUPLICATE_PAYMENT'
        );
    }
}
