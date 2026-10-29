<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Enums\PaymentMethodType;
use App\Exceptions\InsufficientInventoryException;
use App\Exceptions\InvalidCheckoutException;
use App\Exceptions\PriceChangedException;
use App\Exceptions\ProductNotPurchasableException;
use App\Models\Address;
use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Catalogue\ProductPurchaseEligibilityService;
use App\Services\Pricing\PricingService;
use App\Services\Shipping\ShippingEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CheckoutFlowService
{
    public const BUY_NOW_SESSION_KEY = 'checkout_buy_now';

    public function __construct(
        protected CartService $cartService,
        protected CheckoutSessionService $checkoutSessionService,
        protected ShippingEngine $shippingEngine,
        protected ProductPurchaseEligibilityService $eligibilityService,
        protected GuestCustomerService $guestCustomerService,
        protected PricingService $pricingService,
    ) {}

    public function actor(Request $request): ?User
    {
        if ($request->user()) {
            return $request->user();
        }

        $customerId = $request->session()->get('checkout_customer_id');

        return is_string($customerId) && $customerId !== ''
            ? User::query()->find($customerId)
            : null;
    }

    public function gate(Request $request, string $step): ?RedirectResponse
    {
        $user = $this->actor($request);
        $orderId = $request->session()->get('checkout_order_id');
        $completedOrder = is_string($orderId)
            ? Order::query()
                ->when($user, fn ($query) => $query->where('user_id', $user->id))
                ->find($orderId)
            : null;

        if ($step === 'confirmation') {
            return $completedOrder ? null : redirect()->route('checkout.payment');
        }

        if (in_array($step, ['delivery', 'payment'], true) && $completedOrder) {
            return redirect()->route('checkout.confirmation');
        }

        $basket = $this->checkoutBasket($request);
        $empty = ((int) ($basket['totals']['items_count'] ?? 0)) === 0;

        if ($step === 'details') {
            if ($completedOrder && $empty) {
                return redirect()->route('checkout.confirmation');
            }

            if ($completedOrder && ! $empty) {
                $request->session()->forget('checkout_order_id');
                $request->session()->forget('checkout_token');
            }

            return $empty ? redirect()->route('cart.index') : null;
        }

        if ($empty) {
            return redirect()->route('cart.index');
        }

        try {
            $session = $this->openSession($request);
        } catch (InvalidCheckoutException $exception) {
            $request->session()->forget('checkout_token');

            return redirect()
                ->route('cart.index')
                ->with('error', $exception->getCustomerMessage());
        }

        if (! $session || ! $this->hasDetails($session)) {
            return redirect()->route('checkout.show');
        }

        if ($step === 'payment' && ! $this->hasDelivery($session)) {
            return redirect()->route('checkout.delivery');
        }

        return null;
    }

    public function openSession(Request $request): ?CheckoutSession
    {
        $user = $this->actor($request);
        $session = $this->checkoutSessionService->findOwnedSession(
            $user,
            $request->session()->get('checkout_token')
        );

        if (! $session) {
            return null;
        }

        if ($session->status === CheckoutSession::STATUS_CONVERTED && $session->order_id) {
            return $session;
        }

        if (! $session->isOpen()) {
            throw new InvalidCheckoutException(
                'Checkout session is expired or already converted.',
                'Your checkout session has expired.'
            );
        }

        return $session;
    }

    /**
     * @return array<string, mixed>
     */
    public function pageProps(Request $request, string $step): array
    {
        $user = $this->actor($request);
        $cartData = $this->checkoutBasket($request);

        $warnings = [];
        $affectedItems = [];
        $snapshot = null;
        $session = null;
        $requiresAcknowledgement = (bool) $request->session()->get('checkout_requires_acknowledgement', false);

        try {
            $session = $this->openSession($request);
        } catch (InvalidCheckoutException $exception) {
            $warnings[] = $exception->getCustomerMessage();
        }

        if ($session && $session->isOpen() && $user) {
            try {
                if ($step === 'delivery' && $request->filled('shipping_method_code')) {
                    $snapshot = $this->checkoutSessionService->refreshOpenSnapshot($session, $user, [
                        'shipping_method_code' => (string) $request->query('shipping_method_code'),
                    ]);
                    $session->refresh();
                } else {
                    $this->checkoutSessionService->revalidateStoredSnapshot($session, $user);
                    $snapshot = $session->snapshot;
                    $snapshot['checkout_token'] = $session->token;
                    $snapshot['expires_at'] = $session->expires_at?->toIso8601String();
                }
            } catch (PriceChangedException $exception) {
                $snapshot = $this->checkoutSessionService->refreshOpenSnapshot($session, $user);
                $session->refresh();
                $request->session()->put('checkout_requires_acknowledgement', true);
                $requiresAcknowledgement = true;
                $warnings[] = $exception->getCustomerMessage();
            } catch (InsufficientInventoryException|ProductNotPurchasableException $exception) {
                $affectedItems = $this->affectedItems($session);
                $warnings[] = $exception->getCustomerMessage();
                $snapshot = $session->snapshot;
                $snapshot['checkout_token'] = $session->token;
            } catch (InvalidCheckoutException $exception) {
                $warnings[] = $exception->getCustomerMessage();
            }
        }

        $addresses = $request->user()
            ? Address::query()
                ->where('user_id', $request->user()->id)
                ->orderByDesc('is_default')
                ->get([
                    'id', 'full_name', 'address_line_1', 'address_line_2', 'city', 'state_county',
                    'postal_code', 'country_code', 'phone', 'is_default', 'type',
                ])
            : collect();

        $shippingMethods = collect($snapshot['available_shipping_methods'] ?? []);
        if ($shippingMethods->isEmpty()) {
            $country = $request->query('country_code', $snapshot['shipping_destination']['country_code'] ?? 'GB');
            try {
                $shippingMethods = $this->shippingEngine->calculateRates(
                    $cartData['totals']['subtotal_amount'] ?? '0.00',
                    (string) $country
                )->map(fn (array $method) => $this->checkoutSessionService->decorateShippingMethod($method));
            } catch (\Throwable) {
                $shippingMethods = collect();
            }
        }

        $selected = $snapshot['shipping_method'] ?? $shippingMethods->first();
        $totals = $snapshot['totals'] ?? $snapshot['prices'] ?? [];

        return [
            'cart' => $cartData,
            'checkout' => $snapshot,
            'availableShippingMethods' => $shippingMethods->values(),
            'selectedShippingMethod' => $selected,
            'checkoutPreview' => [
                'country_code' => $snapshot['shipping_destination']['country_code'] ?? $request->query('country_code', 'GB'),
                'shipping_method_code' => $selected['code'] ?? null,
                'items_subtotal' => $totals['subtotal_amount'] ?? $cartData['totals']['subtotal_amount'] ?? '0.00',
                'discount' => $totals['discount_amount'] ?? $cartData['totals']['discount_amount'] ?? '0.00',
                'shipping' => $totals['shipping_amount'] ?? '0.00',
                'tax' => $totals['tax_amount'] ?? '0.00',
                'grand_total' => $totals['grand_total_amount'] ?? $snapshot['grand_total'] ?? $cartData['totals']['grand_total_amount'] ?? '0.00',
                'currency' => $totals['currency'] ?? $cartData['totals']['currency'] ?? 'GBP',
            ],
            'addresses' => $addresses,
            'paymentMethods' => $this->configuredPaymentMethods(
                (string) ($totals['grand_total_amount'] ?? $snapshot['grand_total'] ?? $cartData['totals']['grand_total_amount'] ?? '0.00')
            ),
            'payment_method_notice' => $this->paymentMethodNotice(
                (string) ($totals['grand_total_amount'] ?? $snapshot['grand_total'] ?? $cartData['totals']['grand_total_amount'] ?? '0.00')
            ),
            'paymentInstructions' => $this->customerPaymentInstructions(),
            'countries' => $this->countryOptions(),
            'checkout_step' => $step,
            'checkout_steps' => $this->progressSteps(
                $step,
                $this->hasDetails($session),
                $this->hasDelivery($session),
                false
            ),
            'checkout_token' => $snapshot['checkout_token'] ?? $session?->token,
            'idempotency_key' => $snapshot['idempotency_key'] ?? $request->session()->get('checkout_idempotency_key'),
            'requires_acknowledgement' => $requiresAcknowledgement,
            'warnings' => $warnings,
            'affected_items' => $affectedItems,
            'seo' => [
                'title' => match ($step) {
                    'delivery' => 'Delivery',
                    'payment' => 'Payment',
                    'confirmation' => 'Order placed',
                    default => 'Checkout details',
                },
                'description' => 'Secure checkout.',
                'robots' => 'noindex,nofollow',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function saveDetails(Request $request, array $validated): array
    {
        $destination = $this->resolveDestination($request, $validated);
        $user = $this->rememberCustomer($request, $validated, $destination);

        if ($request->user() && ! empty($validated['save_address'])) {
            $this->persistOwnedAddress($request->user(), $destination);
        }

        $items = $this->cartItemsPayload($request);
        if ($items === []) {
            throw new InvalidCheckoutException('Checkout requires at least one item.', 'Your cart is empty.');
        }

        $snapshot = $this->checkoutSessionService->syncOpenSession(
            $user,
            $request->session()->get('checkout_token'),
            [
                'items' => $items,
                'shipping_destination' => $destination,
                'shipping_method_code' => $validated['shipping_method_code'] ?? null,
                'customer_notes' => $validated['customer_notes'] ?? null,
                'address_id' => $validated['address_id'] ?? null,
                'details_completed_at' => now()->toIso8601String(),
                'delivery_completed_at' => null,
            ]
        );

        $request->session()->put('checkout_token', $snapshot['checkout_token'] ?? null);
        $request->session()->put('checkout_idempotency_key', $snapshot['idempotency_key'] ?? (string) Str::uuid());
        $request->session()->forget('checkout_requires_acknowledgement');

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function saveDelivery(Request $request, array $validated): array
    {
        $this->assertNotAcknowledgedBlocked($request);

        $session = $this->openSession($request);
        if (! $session || ! $this->hasDetails($session)) {
            throw new InvalidCheckoutException('Delivery details are missing.', 'Please complete your delivery details first.');
        }

        $snapshot = $this->checkoutSessionService->refreshOpenSnapshot($session, $this->requireActor($request), [
            'shipping_method_code' => $validated['shipping_method_code'],
            'details_completed_at' => $session->snapshot['details_completed_at'] ?? now()->toIso8601String(),
            'delivery_completed_at' => now()->toIso8601String(),
        ]);

        $request->session()->put('checkout_token', $snapshot['checkout_token'] ?? $session->token);

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function savePaymentMethod(Request $request, array $validated): array
    {
        $this->assertNotAcknowledgedBlocked($request);

        $session = $this->openSession($request);
        if (! $session || ! $this->hasDelivery($session)) {
            throw new InvalidCheckoutException('Payment cannot start yet.', 'Please choose a delivery method first.');
        }

        $method = PaymentMethodType::from($validated['payment_method'])->value;
        $grandTotal = $this->resolveCheckoutGrandTotal($session->snapshot ?? []);
        $allowed = $this->allowedPaymentMethodCodes($grandTotal);
        if (! in_array($method, $allowed, true)) {
            throw new InvalidCheckoutException(
                'Payment method not available for this order total.',
                $this->paymentMethodNotice($grandTotal) ?? 'Please choose an available payment method.'
            );
        }

        $snapshot = $this->checkoutSessionService->refreshOpenSnapshot($session, $this->requireActor($request), [
            'payment_method' => $method,
            'details_completed_at' => $session->snapshot['details_completed_at'] ?? now()->toIso8601String(),
            'delivery_completed_at' => $session->snapshot['delivery_completed_at'] ?? now()->toIso8601String(),
        ]);

        $request->session()->put('checkout_token', $snapshot['checkout_token'] ?? $session->token);

        return $snapshot;
    }

    public function acknowledgePriceChange(Request $request): void
    {
        $request->session()->forget('checkout_requires_acknowledgement');
    }

    public function hasDetails(?CheckoutSession $session): bool
    {
        if (! $session) {
            return false;
        }

        $snapshot = $session->snapshot ?? [];

        return filled($snapshot['details_completed_at'] ?? null)
            && filled($snapshot['shipping_destination']['address_line_1'] ?? null);
    }

    public function hasDelivery(?CheckoutSession $session): bool
    {
        if (! $this->hasDetails($session)) {
            return false;
        }

        $snapshot = $session->snapshot ?? [];

        return filled($snapshot['delivery_completed_at'] ?? null)
            && filled($snapshot['shipping_method']['code'] ?? null);
    }

    /**
     * @return list<array{key: string, label: string, state: string}>
     */
    public function progressSteps(string $current, bool $hasDetails, bool $hasDelivery, bool $hasOrder): array
    {
        $flags = [
            'details' => $hasDetails,
            'delivery' => $hasDelivery,
            'payment' => $hasOrder,
            'confirmation' => $hasOrder,
        ];
        $order = ['details', 'delivery', 'payment', 'confirmation'];
        $labels = [
            'details' => 'Details',
            'delivery' => 'Delivery',
            'payment' => 'Payment',
            'confirmation' => 'Confirmation',
        ];
        $currentIndex = array_search($current, $order, true);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;

        $steps = [];
        foreach ($order as $index => $key) {
            $state = 'upcoming';
            if ($key === $current) {
                $state = 'current';
            } elseif ($index < $currentIndex && $flags[$key]) {
                $state = 'completed';
            }

            $steps[] = [
                'key' => $key,
                'label' => $labels[$key],
                'state' => $state,
            ];
        }

        return $steps;
    }

    /**
     * @return list<array{code: string, name: string, description: string}>
     */
    public function configuredPaymentMethods(?string $grandTotal = null): array
    {
        $configured = config('payments.methods', []);

        $methods = array_values(array_filter(array_map(function (array $method): ?array {
            try {
                $type = PaymentMethodType::from((string) $method['code']);
            } catch (\ValueError) {
                return null;
            }

            return [
                'code' => $type->value,
                'name' => $method['name'] ?? $type->label(),
                'description' => $method['description'] ?? '',
            ];
        }, $configured)));

        if ($grandTotal === null) {
            return $methods;
        }

        $threshold = (float) config('payments.bank_transfer_minimum_total', 100);
        if ((float) $grandTotal >= $threshold) {
            return $methods;
        }

        return array_values(array_filter(
            $methods,
            static fn (array $method): bool => $method['code'] === PaymentMethodType::CRYPTOCURRENCY->value
        ));
    }

    /**
     * @return list<string>
     */
    public function allowedPaymentMethodCodes(?string $grandTotal = null): array
    {
        return array_values(array_map(
            static fn (array $method): string => $method['code'],
            $this->configuredPaymentMethods($grandTotal)
        ));
    }

    public function resolveCheckoutGrandTotal(?array $snapshot = null, ?array $cartData = null): string
    {
        $totals = $snapshot['totals'] ?? $snapshot['prices'] ?? [];

        return (string) ($totals['grand_total_amount']
            ?? $snapshot['grand_total']
            ?? $cartData['totals']['grand_total_amount']
            ?? '0.00');
    }

    public function paymentMethodNotice(?string $grandTotal = null): ?string
    {
        if ($grandTotal === null) {
            return null;
        }

        $threshold = (float) config('payments.bank_transfer_minimum_total', 100);
        if ((float) $grandTotal >= $threshold) {
            return null;
        }

        return sprintf(
            'Orders under £%s can be paid by cryptocurrency only. Bank transfer is available for orders of £%s or more.',
            number_format($threshold, 0),
            number_format($threshold, 0)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function customerPaymentInstructions(): array
    {
        return [
            'bank_transfer' => array_filter(config('payments.bank_transfer', [])),
            'crypto' => config('payments.crypto', ['networks' => [], 'reference_hint' => null]),
        ];
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    public function countryOptions(): array
    {
        return [
            ['code' => 'GB', 'name' => 'United Kingdom'],
            ['code' => 'IE', 'name' => 'Ireland'],
            ['code' => 'FR', 'name' => 'France'],
            ['code' => 'DE', 'name' => 'Germany'],
            ['code' => 'NL', 'name' => 'Netherlands'],
            ['code' => 'BE', 'name' => 'Belgium'],
            ['code' => 'ES', 'name' => 'Spain'],
            ['code' => 'IT', 'name' => 'Italy'],
            ['code' => 'PT', 'name' => 'Portugal'],
            ['code' => 'AT', 'name' => 'Austria'],
            ['code' => 'CH', 'name' => 'Switzerland'],
            ['code' => 'SE', 'name' => 'Sweden'],
            ['code' => 'NO', 'name' => 'Norway'],
            ['code' => 'DK', 'name' => 'Denmark'],
            ['code' => 'PL', 'name' => 'Poland'],
            ['code' => 'US', 'name' => 'United States'],
            ['code' => 'CA', 'name' => 'Canada'],
            ['code' => 'AU', 'name' => 'Australia'],
        ];
    }

    /**
     * Begin an express checkout for one product without mutating the shopping cart.
     */
    public function startBuyNow(Request $request, string $productId, ?string $variantId, int $quantity): void
    {
        $product = Product::query()->findOrFail($productId);
        $variant = filled($variantId)
            ? ProductVariant::query()->findOrFail($variantId)
            : null;

        if ($variant && $variant->product_id !== $product->id) {
            throw new InvalidCheckoutException(
                'Buy now variant does not belong to product.',
                'The selected option is not available for this product.'
            );
        }

        $this->eligibilityService->assertPurchasable($product, $variant, $quantity, true);

        $request->session()->put(self::BUY_NOW_SESSION_KEY, [
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'quantity' => max(1, $quantity),
        ]);
        $request->session()->forget([
            'checkout_token',
            'checkout_idempotency_key',
            'checkout_requires_acknowledgement',
            'checkout_order_id',
        ]);
    }

    public function clearBuyNow(Request $request): void
    {
        $request->session()->forget(self::BUY_NOW_SESSION_KEY);
    }

    public function hasBuyNow(Request $request): bool
    {
        $payload = $request->session()->get(self::BUY_NOW_SESSION_KEY);

        return is_array($payload)
            && filled($payload['product_id'] ?? null)
            && (int) ($payload['quantity'] ?? 0) > 0;
    }

    /**
     * Basket used for checkout gates and UI — buy-now takes priority over the cart.
     *
     * @return array<string, mixed>
     */
    public function checkoutBasket(Request $request): array
    {
        if ($this->hasBuyNow($request)) {
            return $this->summarizeBuyNow($request);
        }

        return $this->cartService->summarize($this->cartService->findRequestCart($request));
    }

    /**
     * @return list<array{product_id: string, variant_id: ?string, quantity: int}>
     */
    public function cartItemsPayload(Request $request): array
    {
        if ($this->hasBuyNow($request)) {
            $payload = $request->session()->get(self::BUY_NOW_SESSION_KEY);

            return [[
                'product_id' => (string) $payload['product_id'],
                'variant_id' => filled($payload['product_variant_id'] ?? null)
                    ? (string) $payload['product_variant_id']
                    : null,
                'quantity' => (int) $payload['quantity'],
            ]];
        }

        $cart = $this->cartService->findRequestCart($request);
        if (! $cart) {
            return [];
        }

        $data = $this->cartService->calculateCart($cart);

        return array_map(static fn (array $item) => [
            'product_id' => $item['product_id'],
            'variant_id' => $item['variant_id'],
            'quantity' => $item['quantity'],
        ], $data['items']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function summarizeBuyNow(Request $request): array
    {
        $payload = $request->session()->get(self::BUY_NOW_SESSION_KEY);
        $product = Product::query()
            ->with(['images', 'inventory'])
            ->findOrFail((string) $payload['product_id']);
        $variant = filled($payload['product_variant_id'] ?? null)
            ? ProductVariant::query()
                ->with('inventory')
                ->find((string) $payload['product_variant_id'])
            : null;
        $quantity = (int) $payload['quantity'];

        $eligibility = $this->eligibilityService->checkCartLineEligibility($product, $variant, $quantity);
        $lineCalc = $this->pricingService->calculateLineItem($product, $variant, $quantity, 0);
        $totals = $this->pricingService->calculateTotals([
            [
                'product' => $product,
                'variant' => $variant,
                'quantity' => $quantity,
                'discount' => 0,
            ],
        ], 0, 0, 0, $product->currency ?? 'GBP');

        $image = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

        return [
            'id' => null,
            'items' => [[
                'id' => 'buy-now',
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_slug' => $product->slug,
                'product_sku' => $product->sku,
                'variant_id' => $variant?->id,
                'variant_name' => $variant?->name,
                'unit_price' => $lineCalc['unit_price_amount'],
                'quantity' => $quantity,
                'subtotal' => $lineCalc['line_subtotal_amount'],
                'line_total' => $lineCalc['line_total_amount'],
                'image_url' => $image?->url,
                'is_eligible' => $eligibility['is_eligible'],
                'ineligibility_reasons' => $eligibility['reasons'],
            ]],
            'totals' => $totals,
            'shipping_eligibility' => $this->shippingEngine->freeShippingEligibility($totals['subtotal_amount'] ?? '0.00'),
            'is_checkout_ready' => $eligibility['is_eligible'],
            'buy_now' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $destination
     */
    public function rememberCustomer(Request $request, array $validated, array $destination): User
    {
        if ($request->user()) {
            $request->session()->put('checkout_customer_id', $request->user()->id);

            return $request->user();
        }

        $email = (string) ($validated['email'] ?? '');
        $name = (string) ($destination['full_name'] ?? $validated['name'] ?? '');
        $user = $this->guestCustomerService->resolve($name, $email);
        $request->session()->put('checkout_customer_id', $user->id);

        return $user;
    }

    public function requireActor(Request $request): User
    {
        $user = $this->actor($request);
        if (! $user) {
            throw new InvalidCheckoutException(
                'Checkout customer is missing.',
                'Please complete your details before continuing checkout.'
            );
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function resolveDestination(Request $request, array $validated): array
    {
        $addressId = $validated['address_id'] ?? null;
        $user = $request->user();

        if (is_string($addressId) && $addressId !== '') {
            if (! $user) {
                throw new InvalidCheckoutException(
                    'Saved addresses are only available to signed-in customers.',
                    'Please enter a delivery address.'
                );
            }

            $address = Address::query()
                ->where('user_id', $user->id)
                ->where('id', $addressId)
                ->first();

            if (! $address) {
                throw new InvalidCheckoutException(
                    'Address does not belong to the authenticated customer.',
                    'Please choose a saved address from your account or enter a new address.'
                );
            }

            return [
                'country_code' => $address->country_code,
                'full_name' => $address->full_name,
                'address_line_1' => $address->address_line_1,
                'address_line_2' => $address->address_line_2,
                'city' => $address->city,
                'state_county' => $address->state_county,
                'postal_code' => $address->postal_code,
                'phone' => $address->phone,
            ];
        }

        return $validated['shipping_destination'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $destination
     */
    protected function persistOwnedAddress(User $user, array $destination): void
    {
        Address::query()->create([
            'user_id' => $user->id,
            'type' => 'shipping',
            'full_name' => $destination['full_name'] ?? $user->name,
            'address_line_1' => $destination['address_line_1'] ?? '',
            'address_line_2' => $destination['address_line_2'] ?? null,
            'city' => $destination['city'] ?? '',
            'state_county' => $destination['state_county'] ?? null,
            'postal_code' => $destination['postal_code'] ?? '',
            'country_code' => $destination['country_code'] ?? 'GB',
            'phone' => $destination['phone'] ?? null,
            'is_default' => ! Address::query()->where('user_id', $user->id)->exists(),
        ]);
    }

    /**
     * @return list<array{name: string, quantity: int}>
     */
    protected function affectedItems(CheckoutSession $session): array
    {
        $affected = [];

        foreach ($session->snapshot['items'] ?? [] as $item) {
            $product = Product::query()->find($item['product_id'] ?? null);
            if (! $product) {
                $affected[] = [
                    'name' => (string) ($item['product_name'] ?? 'An item'),
                    'quantity' => (int) ($item['quantity'] ?? 0),
                ];

                continue;
            }

            $variant = ! empty($item['variant_id'])
                ? ProductVariant::query()->find($item['variant_id'])
                : null;

            $eligibility = $this->eligibilityService->checkEligibility(
                $product,
                $variant,
                (int) ($item['quantity'] ?? 1),
                true
            );

            if (! $eligibility['is_eligible']) {
                $affected[] = [
                    'name' => $product->name,
                    'quantity' => (int) ($item['quantity'] ?? 0),
                ];
            }
        }

        return $affected;
    }

    protected function assertNotAcknowledgedBlocked(Request $request): void
    {
        if ($request->session()->get('checkout_requires_acknowledgement')) {
            throw new PriceChangedException();
        }
    }
}
