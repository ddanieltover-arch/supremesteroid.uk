<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Shipping\ShippingEngine;
use Database\Seeders\InitialFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialFoundationSeeder::class);
    }

    public function test_standard_uk_shipping_under_threshold_costs_ten_pounds(): void
    {
        $engine = app(ShippingEngine::class);
        $rates = $engine->calculateRates(150.00, 'GB');

        $standard = $rates->firstWhere('code', 'UK_STANDARD');

        $this->assertNotNull($standard);
        $this->assertEquals(10.00, $standard['rate']);
        $this->assertFalse($standard['is_free']);
    }

    public function test_standard_uk_shipping_at_or_above_three_hundred_pounds_is_free(): void
    {
        $engine = app(ShippingEngine::class);
        $rates = $engine->calculateRates(300.00, 'GB');

        $standard = $rates->firstWhere('code', 'UK_STANDARD');

        $this->assertNotNull($standard);
        $this->assertEquals(0.00, $standard['rate']);
        $this->assertTrue($standard['is_free']);
    }

    public function test_free_delivery_threshold_is_exclusive_of_two_nine_nine_nine_nine(): void
    {
        $engine = app(ShippingEngine::class);

        $below = $engine->calculateRates('299.99', 'GB')->firstWhere('code', 'UK_STANDARD');
        $exact = $engine->calculateRates('300.00', 'GB')->firstWhere('code', 'UK_STANDARD');
        $above = $engine->calculateRates('300.01', 'GB')->firstWhere('code', 'UK_STANDARD');

        $this->assertEquals(10.00, $below['rate']);
        $this->assertFalse($below['is_free']);
        $this->assertEquals(0.00, $exact['rate']);
        $this->assertTrue($exact['is_free']);
        $this->assertEquals(0.00, $above['rate']);
        $this->assertTrue($above['is_free']);
    }

    public function test_europe_shipping_rate_is_thirty_five_pounds(): void
    {
        $engine = app(ShippingEngine::class);
        $rates = $engine->calculateRates(100.00, 'FR');

        $euMethod = $rates->firstWhere('code', 'EUROPE_STANDARD');

        $this->assertNotNull($euMethod);
        $this->assertEquals(35.00, $euMethod['rate']);
    }

    public function test_international_shipping_rate_is_fifty_pounds(): void
    {
        $engine = app(ShippingEngine::class);
        $rates = $engine->calculateRates(100.00, 'US');

        $intlMethod = $rates->firstWhere('code', 'INTL_PRIORITY');

        $this->assertNotNull($intlMethod);
        $this->assertEquals(50.00, $intlMethod['rate']);
    }
}
