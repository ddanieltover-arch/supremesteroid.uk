<?php

declare(strict_types=1);

namespace App\Exceptions;

class PriceChangedException extends CustomerFacingException
{
    public function __construct(string $internalMessage = 'One or more item prices changed before order creation.')
    {
        parent::__construct(
            $internalMessage,
            'Your basket has changed. We have refreshed the latest pricing and availability.',
            'PRICE_CHANGED'
        );
    }
}
