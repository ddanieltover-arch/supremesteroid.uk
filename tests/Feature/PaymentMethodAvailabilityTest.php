<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentMethodType;
use App\Services\Checkout\CheckoutFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_under_one_hundred_offer_cryptocurrency_only(): void
    {
        $flow = app(CheckoutFlowService::class);

        $methods = $flow->configuredPaymentMethods('99.99');
        $codes = array_column($methods, 'code');

        $this->assertSame([PaymentMethodType::CRYPTOCURRENCY->value], $codes);
        $this->assertNotNull($flow->paymentMethodNotice('99.99'));
    }

    public function test_orders_of_one_hundred_or_more_offer_bank_and_crypto(): void
    {
        $flow = app(CheckoutFlowService::class);

        $methods = $flow->configuredPaymentMethods('100.00');
        $codes = array_column($methods, 'code');

        $this->assertContains(PaymentMethodType::BANK_TRANSFER->value, $codes);
        $this->assertContains(PaymentMethodType::CRYPTOCURRENCY->value, $codes);
        $this->assertNull($flow->paymentMethodNotice('100.00'));
    }
}
