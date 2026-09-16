<?php

declare(strict_types=1);

namespace App\Exceptions;

class ShippingUnavailableException extends CustomerFacingException
{
    public function __construct(string $internalMessage = 'No shipping methods available for the destination.')
    {
        parent::__construct(
            $internalMessage,
            'We cannot currently ship to the address provided. Please check the country and try again.',
            'SHIPPING_UNAVAILABLE'
        );
    }
}
