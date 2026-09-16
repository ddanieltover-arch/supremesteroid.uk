<?php

declare(strict_types=1);

namespace App\Actions\Shipping;

use App\Services\Shipping\ShippingEngine;
use Illuminate\Support\Collection;

class CalculateOrderShippingAction
{
    public function __construct(
        protected ShippingEngine $shippingEngine
    ) {}

    public function execute(float $subtotal, string $countryCode = 'GB'): Collection
    {
        return $this->shippingEngine->calculateRates($subtotal, $countryCode);
    }
}
