<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Enums\ShippingZoneType;
use App\Exceptions\ShippingUnavailableException;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Services\Pricing\PricingService;
use App\Support\IsoCountry;
use Illuminate\Support\Collection;

class ShippingEngine
{
    public function __construct(
        protected PricingService $pricingService
    ) {}

    /**
     * Calculate all available shipping methods and computed rates for a given subtotal and destination country.
     *
     * Free UK Standard delivery applies at subtotal >= £300.00 (inclusive), compared in integer pence.
     *
     * @return Collection<int, array{id: string, code: string, name: string, rate: float, currency: string, estimated_days: string, is_discreet: bool, is_free: bool}>
     */
    public function calculateRates(string|float|int $subtotal, string $countryCode = 'GB'): Collection
    {
        $countryCode = IsoCountry::normalize($countryCode);
        $subtotalPence = $this->pricingService->toPence($subtotal);
        $freeThresholdPence = $this->pricingService->toPence(
            (string) config('shipping.free_shipping_threshold_gbp', '300.00')
        );

        $zone = $this->resolveZoneForCountry($countryCode);

        if ($zone) {
            $methods = ShippingMethod::query()
                ->where('shipping_zone_id', $zone->id)
                ->where('is_active', true)
                ->with(['rules' => function ($query) {
                    $query->where('is_active', true);
                }])
                ->orderBy('display_order')
                ->get();

            if ($methods->isNotEmpty()) {
                return $methods->map(function (ShippingMethod $method) use ($subtotalPence, $freeThresholdPence) {
                    $effectivePence = $this->pricingService->toPence((string) $method->rate_amount);
                    $isFree = false;

                    foreach ($method->rules as $rule) {
                        $minPence = $this->pricingService->toPence((string) ($rule->min_subtotal ?? '0'));

                        if ($rule->is_free_shipping && $subtotalPence >= $minPence) {
                            $effectivePence = 0;
                            $isFree = true;
                            break;
                        }

                        if ($rule->override_rate_amount !== null && $subtotalPence >= $minPence) {
                            $effectivePence = $this->pricingService->toPence((string) $rule->override_rate_amount);
                        }
                    }

                    $rateAmount = $this->pricingService->toDecimalString($effectivePence);

                    return [
                        'id' => $method->id,
                        'code' => $method->code,
                        'name' => $method->name,
                        'rate' => (float) $rateAmount,
                        'rate_amount' => $rateAmount,
                        'currency' => $method->currency ?? 'GBP',
                        'estimated_days' => $method->estimated_days,
                        'is_discreet' => $method->is_discreet,
                        'is_free' => $isFree,
                    ];
                });
            }
        }

        return $this->fallbackConfigurationRates($subtotalPence, $freeThresholdPence, $countryCode);
    }

    /**
     * Prefer an exact ISO country match over a wildcard international zone.
     */
    public function resolveZoneForCountry(string $countryCode): ?ShippingZone
    {
        $countryCode = IsoCountry::normalize($countryCode);

        $zones = ShippingZone::query()
            ->where('is_active', true)
            ->get();

        $exact = $zones->first(function (ShippingZone $zone) use ($countryCode) {
            $countries = $zone->countries ?? [];

            return in_array($countryCode, $countries, true);
        });

        if ($exact) {
            return $exact;
        }

        return $zones->first(function (ShippingZone $zone) {
            $countries = $zone->countries ?? [];

            return in_array('*', $countries, true) || $zone->type === ShippingZoneType::INTERNATIONAL;
        });
    }

    /**
     * @return Collection<int, array{id: string, code: string, name: string, rate: float, currency: string, estimated_days: string, is_discreet: bool, is_free: bool}>
     */
    protected function fallbackConfigurationRates(int $subtotalPence, int $freeThresholdPence, string $countryCode): Collection
    {
        if ($countryCode === 'GB') {
            $isFreeUkStandard = $subtotalPence >= $freeThresholdPence;

            $standardAmount = $isFreeUkStandard ? '0.00' : '10.00';

            return collect([
                $this->formatFallbackRate('uk_standard', 'UK_STANDARD', 'UK Standard Delivery', $standardAmount, '2-4 business days', false, $isFreeUkStandard),
                $this->formatFallbackRate('uk_express', 'UK_EXPRESS', 'UK Express Delivery', '15.00', '1-2 business days', false, false),
                $this->formatFallbackRate('uk_discreet', 'UK_DISCREET', 'UK Discreet Delivery', '25.00', '1-2 business days', true, false),
            ]);
        }

        $europeanCountries = [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU',
            'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'CH', 'NO',
        ];

        if (in_array($countryCode, $europeanCountries, true)) {
            return collect([
                $this->formatFallbackRate('europe_standard', 'EUROPE_STANDARD', 'Europe Tracked Shipping', '35.00', '5-9 business days', true, false),
            ]);
        }

        return collect([
            $this->formatFallbackRate('intl_priority', 'INTL_PRIORITY', 'International Priority Shipping', '50.00', '7-14 business days', true, false),
        ]);
    }

    /**
     * @return array{free_shipping_threshold: string, current_eligible_subtotal: string, remaining_to_threshold: string, free_shipping_eligible: bool, progress_percent: int, currency: string}
     */
    public function freeShippingEligibility(string|float|int $subtotal): array
    {
        $subtotalPence = $this->pricingService->toPence($subtotal);
        $thresholdPence = $this->pricingService->toPence(
            (string) config('shipping.free_shipping_threshold_gbp', '300.00')
        );
        $remainingPence = max(0, $thresholdPence - $subtotalPence);
        $eligible = $subtotalPence >= $thresholdPence;
        $percent = $thresholdPence === 0
            ? 100
            : min(100, (int) round(($subtotalPence / $thresholdPence) * 100));

        return [
            'free_shipping_threshold' => $this->pricingService->toDecimalString($thresholdPence),
            'current_eligible_subtotal' => $this->pricingService->toDecimalString($subtotalPence),
            'remaining_to_threshold' => $this->pricingService->toDecimalString($remainingPence),
            'free_shipping_eligible' => $eligible,
            'progress_percent' => $percent,
            'currency' => (string) config('shipping.currency', 'GBP'),
        ];
    }

    /**
     * Server-owned catalogue of shipping options for display (not a quote).
     *
     * @return array{currency: string, free_shipping_threshold: string, methods: list<array<string, mixed>>}
     */
    public function publicOverview(): array
    {
        $threshold = $this->pricingService->toDecimalString(
            $this->pricingService->toPence((string) config('shipping.free_shipping_threshold_gbp', '300.00'))
        );

        $methods = [];
        foreach (config('shipping.initial_rates', []) as $rate) {
            $amountPence = $this->pricingService->toPence((string) ($rate['amount'] ?? '0'));
            $methods[] = [
                'code' => $rate['code'],
                'name' => $rate['name'],
                'rate_amount' => $this->pricingService->toDecimalString($amountPence),
                'estimated_days' => $rate['estimated_days'] ?? null,
                'is_eligible_for_free_threshold' => (bool) ($rate['is_eligible_for_free_threshold'] ?? false),
            ];
        }

        return [
            'currency' => (string) config('shipping.currency', 'GBP'),
            'free_shipping_threshold' => $threshold,
            'methods' => $methods,
        ];
    }

    /**
     * @return array{id: string, code: string, name: string, rate: float, rate_amount: string, currency: string, estimated_days: string, is_discreet: bool, is_free: bool}
     */
    protected function formatFallbackRate(
        string $id,
        string $code,
        string $name,
        string $rateAmount,
        string $estimatedDays,
        bool $isDiscreet,
        bool $isFree
    ): array {
        return [
            'id' => $id,
            'code' => $code,
            'name' => $name,
            'rate' => (float) $rateAmount,
            'rate_amount' => $rateAmount,
            'currency' => 'GBP',
            'estimated_days' => $estimatedDays,
            'is_discreet' => $isDiscreet,
            'is_free' => $isFree,
        ];
    }

    /**
     * @param  array<string, mixed>  $rate
     */
    public function requireRate(Collection $rates, string $methodCode, string $countryCode): array
    {
        $selectedMethodCode = strtoupper(trim($methodCode));

        $selected = $rates->first(function ($rate) use ($selectedMethodCode) {
            return strtoupper((string) $rate['code']) === $selectedMethodCode
                || (string) $rate['id'] === $selectedMethodCode;
        });

        if (! $selected) {
            $selected = $rates->first();
        }

        if (! $selected) {
            throw new ShippingUnavailableException(
                "No shipping methods available for destination '{$countryCode}'."
            );
        }

        return $selected;
    }
}
