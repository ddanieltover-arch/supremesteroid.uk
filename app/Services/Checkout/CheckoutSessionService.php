<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Exceptions\InvalidCheckoutException;
use App\Exceptions\PriceChangedException;
use App\Models\CheckoutSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Catalogue\ProductPurchaseEligibilityService;
use App\Services\Pricing\PricingService;
use App\Services\Shipping\ShippingEngine;
use App\Support\IsoCountry;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CheckoutSessionService
{
    public const DEFAULT_TTL_MINUTES = 30;

    public function __construct(
        protected ProductPurchaseEligibilityService $eligibilityService,
        protected PricingService $pricingService,
        protected ShippingEngine $shippingEngine
    ) {}

    /**
     * Create an authoritative server-side checkout snapshot.
     *
     * Client-supplied unit prices, shipping prices, discounts, and grand totals are ignored.
     *
     * @param array{
     *     customer: User,
     *     items: list<array{product_id: string, variant_id?: ?string, quantity: int}>,
     *     shipping_destination: array{
     *         country_code: string,
     *         full_name: string,
     *         address_line_1: string,
     *         address_line_2?: ?string,
     *         city: string,
     *         state_county?: ?string,
     *         postal_code: string,
     *         phone?: ?string
     *     },
     *     shipping_method_code: string,
     *     billing_destination?: ?array{
     *         country_code: string,
     *         full_name: string,
     *         address_line_1: string,
     *         address_line_2?: ?string,
     *         city: string,
     *         state_county?: ?string,
     *         postal_code: string,
     *         phone?: ?string
     *     },
     *     customer_notes?: ?string
     * } $data
     * @return array<string, mixed>
     */
    public function prepareCheckout(array $data, bool $persist = true): array
    {
        $snapshot = $this->buildSnapshot($data);

        if ($persist) {
            $token = Str::lower(Str::random(48));

            CheckoutSession::create([
                'token' => $token,
                'user_id' => $data['customer']->id,
                'snapshot' => $snapshot,
                'status' => CheckoutSession::STATUS_OPEN,
                'expires_at' => now()->addMinutes(self::DEFAULT_TTL_MINUTES),
            ]);

            $snapshot['checkout_token'] = $token;
            $snapshot['expires_at'] = now()->addMinutes(self::DEFAULT_TTL_MINUTES)->toIso8601String();
        }

        return $snapshot;
    }

    /**
     * Recalculate a stored snapshot and fail if server prices no longer match.
     *
     * @return array<string, mixed>
     */
    public function revalidateStoredSnapshot(CheckoutSession $session, User $customer): array
    {
        if ($session->user_id !== $customer->id) {
            throw new InvalidCheckoutException('Checkout session does not belong to this customer.');
        }

        if (! $session->isOpen()) {
            throw new InvalidCheckoutException(
                'Checkout session is expired or already converted.',
                'Your checkout session has expired.'
            );
        }

        $stored = $session->snapshot ?? [];
        $recalculated = $this->buildSnapshot([
            'customer' => $customer,
            'items' => collect($stored['items'] ?? [])->map(fn (array $item) => [
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'] ?? null,
                'quantity' => $item['quantity'],
            ])->all(),
            'shipping_destination' => $stored['shipping_destination'],
            'shipping_method_code' => $stored['shipping_method']['code'] ?? 'UK_STANDARD',
            'billing_destination' => $stored['billing_destination'] ?? null,
            'customer_notes' => $stored['customer_notes'] ?? null,
        ]);

        $storedTotal = (int) ($stored['totals']['grand_total_pence'] ?? -1);
        $freshTotal = (int) $recalculated['totals']['grand_total_pence'];

        if ($storedTotal !== $freshTotal) {
            throw new PriceChangedException(
                "Checkout {$session->token} total changed from {$storedTotal} pence to {$freshTotal} pence."
            );
        }

        $recalculated['checkout_token'] = $session->token;

        return $recalculated;
    }

    public function findOwnedSession(?User $customer, ?string $token): ?CheckoutSession
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        return CheckoutSession::query()
            ->where('token', $token)
            ->when($customer, fn ($query) => $query->where('user_id', $customer->id))
            ->first();
    }

    /**
     * Create or refresh an OPEN checkout session. Converted tokens start a new session.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function syncOpenSession(User $customer, ?string $token, array $data): array
    {
        $data['customer'] = $customer;
        $session = $this->findOwnedSession($customer, $token);

        if ($session && $session->status === CheckoutSession::STATUS_CONVERTED) {
            $session = null;
        }

        if ($session && ! $session->isOpen()) {
            throw new InvalidCheckoutException(
                'Checkout session is expired or already converted.',
                'Your checkout session has expired.'
            );
        }

        $existing = $session?->snapshot ?? [];
        $data['shipping_method_code'] = $data['shipping_method_code']
            ?? $existing['shipping_method']['code']
            ?? 'UK_STANDARD';
        $data['payment_method'] = $data['payment_method'] ?? $existing['payment_method'] ?? null;
        $data['idempotency_key'] = $data['idempotency_key'] ?? $existing['idempotency_key'] ?? (string) Str::uuid();
        $data['address_id'] = $data['address_id'] ?? $existing['address_id'] ?? null;
        $data['details_completed_at'] = $data['details_completed_at'] ?? $existing['details_completed_at'] ?? null;
        $data['delivery_completed_at'] = $data['delivery_completed_at'] ?? $existing['delivery_completed_at'] ?? null;
        $data['customer_notes'] = $data['customer_notes'] ?? $existing['customer_notes'] ?? null;
        $data['billing_destination'] = $data['billing_destination'] ?? $existing['billing_destination'] ?? null;

        $snapshot = $this->buildSnapshot($data);

        if ($session) {
            $session->update([
                'snapshot' => $snapshot,
                'expires_at' => now()->addMinutes(self::DEFAULT_TTL_MINUTES),
            ]);

            $snapshot['checkout_token'] = $session->token;
            $snapshot['expires_at'] = $session->fresh()?->expires_at?->toIso8601String();

            return $snapshot;
        }

        return $this->prepareCheckout($data);
    }

    /**
     * Rebuild and persist an OPEN session from its stored destination and items.
     *
     * @return array<string, mixed>
     */
    public function refreshOpenSnapshot(CheckoutSession $session, User $customer, array $overrides = []): array
    {
        $stored = $session->snapshot ?? [];

        return $this->syncOpenSession($customer, $session->token, array_merge([
            'items' => collect($stored['items'] ?? [])->map(fn (array $item) => [
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'] ?? null,
                'quantity' => $item['quantity'],
            ])->all(),
            'shipping_destination' => $overrides['shipping_destination'] ?? $stored['shipping_destination'] ?? [],
            'shipping_method_code' => $overrides['shipping_method_code'] ?? $stored['shipping_method']['code'] ?? 'UK_STANDARD',
            'billing_destination' => $overrides['billing_destination'] ?? $stored['billing_destination'] ?? null,
            'customer_notes' => $overrides['customer_notes'] ?? $stored['customer_notes'] ?? null,
            'payment_method' => $overrides['payment_method'] ?? $stored['payment_method'] ?? null,
            'details_completed_at' => $overrides['details_completed_at'] ?? $stored['details_completed_at'] ?? null,
            'delivery_completed_at' => $overrides['delivery_completed_at'] ?? $stored['delivery_completed_at'] ?? null,
            'address_id' => $overrides['address_id'] ?? $stored['address_id'] ?? null,
            'idempotency_key' => $stored['idempotency_key'] ?? null,
        ], $overrides));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function buildSnapshot(array $data): array
    {
        $customer = $data['customer'];
        $rawItems = $data['items'] ?? [];

        if (empty($rawItems)) {
            throw new InvalidCheckoutException('Checkout requires at least one item.');
        }

        $countryCode = IsoCountry::normalize((string) ($data['shipping_destination']['country_code'] ?? 'GB'));

        $preparedPricingItems = [];
        $snapshotItems = [];

        foreach ($rawItems as $input) {
            $qty = (int) ($input['quantity'] ?? 1);
            if ($qty <= 0) {
                throw new InvalidArgumentException('Item quantity must be strictly positive.');
            }

            /** @var Product $product */
            $product = Product::query()->findOrFail($input['product_id']);

            /** @var ProductVariant|null $variant */
            $variant = ! empty($input['variant_id'])
                ? ProductVariant::query()->where('product_id', $product->id)->findOrFail($input['variant_id'])
                : null;

            $this->eligibilityService->assertPurchasable($product, $variant, $qty, true);

            $line = $this->pricingService->calculateLineItem($product, $variant, $qty, 0);

            $preparedPricingItems[] = [
                'product' => $product,
                'variant' => $variant,
                'quantity' => $qty,
                'discount' => 0,
            ];

            $product->loadMissing('images');
            $image = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

            $snapshotItems[] = [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'sku_snapshot' => $product->sku,
                'product_name_snapshot' => $product->name,
                'variant_name' => $variant?->name,
                'unit_price' => $line['unit_price_amount'],
                'quantity' => $qty,
                'line_subtotal' => $line['line_subtotal_amount'],
                'discount' => $line['discount_amount'],
                'tax' => '0.00',
                'line_total' => $line['line_total_amount'],
                'image_url' => $image?->publicUrl() ?? $image?->url,
            ];
        }

        $initialTotals = $this->pricingService->calculateTotals($preparedPricingItems, 0, 0, 0, 'GBP');
        $shippingRates = $this->shippingEngine->calculateRates($initialTotals['subtotal_amount'], $countryCode)
            ->map(fn (array $method) => $this->decorateShippingMethod($method))
            ->values();
        $selectedShipping = $this->decorateShippingMethod($this->shippingEngine->requireRate(
            $shippingRates,
            (string) ($data['shipping_method_code'] ?? 'UK_STANDARD'),
            $countryCode
        ));

        $finalTotals = $this->pricingService->calculateTotals(
            items: $preparedPricingItems,
            shippingAmount: $selectedShipping['rate'],
            orderDiscount: 0,
            taxAmount: 0,
            currency: 'GBP'
        );

        $normalizedShippingDest = $this->normalizeDestination($data['shipping_destination'], $countryCode);

        $normalizedBillingDest = ! empty($data['billing_destination']['address_line_1'] ?? null)
            ? $this->normalizeDestination(
                $data['billing_destination'],
                IsoCountry::normalize((string) ($data['billing_destination']['country_code'] ?? $countryCode))
            )
            : $normalizedShippingDest;

        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
            ],
            'items' => $snapshotItems,
            'prices' => [
                'subtotal' => $finalTotals['subtotal_amount'],
                'discount' => $finalTotals['discount_amount'],
                'shipping' => $finalTotals['shipping_amount'],
                'tax' => $finalTotals['tax_amount'],
                'grand_total' => $finalTotals['grand_total_amount'],
                'currency' => $finalTotals['currency'],
            ],
            'shipping_destination' => $normalizedShippingDest,
            'billing_destination' => $normalizedBillingDest,
            'shipping_method' => $selectedShipping,
            'available_shipping_methods' => $shippingRates->all(),
            'shipping_eligibility' => $this->shippingEngine->freeShippingEligibility($initialTotals['subtotal_amount']),
            'shipping_cost' => $finalTotals['shipping_amount'],
            'discount' => $finalTotals['discount_amount'],
            'tax' => $finalTotals['tax_amount'],
            'grand_total' => $finalTotals['grand_total_amount'],
            'totals' => $finalTotals,
            'customer_notes' => $data['customer_notes'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'address_id' => $data['address_id'] ?? null,
            'details_completed_at' => $data['details_completed_at'] ?? null,
            'delivery_completed_at' => $data['delivery_completed_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $method
     * @return array<string, mixed>
     */
    public function decorateShippingMethod(array $method): array
    {
        if (! empty($method['description'])) {
            return $method;
        }

        $parts = [];

        if (! empty($method['estimated_days'])) {
            $parts[] = 'Typical transit: '.$method['estimated_days'];
        }

        if (! empty($method['is_discreet'])) {
            $parts[] = 'Discreet packaging';
        }

        if (! empty($method['is_free'])) {
            $parts[] = 'No shipping charge on this order';
        }

        $method['description'] = $parts !== [] ? implode('. ', $parts).'.' : null;

        return $method;
    }

    /**
     * @param array<string, mixed> $destination
     * @return array{
     *     country_code: string,
     *     full_name: string,
     *     address_line_1: string,
     *     address_line_2: ?string,
     *     city: string,
     *     state_county: ?string,
     *     postal_code: string,
     *     phone: ?string
     * }
     */
    public function normalizeDestination(array $destination, string $countryCode): array
    {
        $fullName = trim((string) ($destination['full_name'] ?? ''));
        $line1 = trim((string) ($destination['address_line_1'] ?? ''));
        $city = trim((string) ($destination['city'] ?? ''));
        $postal = strtoupper(trim((string) ($destination['postal_code'] ?? '')));

        if ($fullName === '' || $line1 === '' || $city === '' || $postal === '') {
            throw new InvalidCheckoutException('Delivery name, address line 1, city, and postcode are required.');
        }

        return [
            'country_code' => IsoCountry::normalize($countryCode),
            'full_name' => $fullName,
            'address_line_1' => $line1,
            'address_line_2' => isset($destination['address_line_2']) ? trim((string) $destination['address_line_2']) ?: null : null,
            'city' => $city,
            'state_county' => isset($destination['state_county']) ? trim((string) $destination['state_county']) ?: null : null,
            'postal_code' => $postal,
            'phone' => isset($destination['phone']) ? trim((string) $destination['phone']) ?: null : null,
        ];
    }
}
