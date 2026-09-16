<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Enums\PaymentMethodType;
use App\Http\Requests\PrepareCheckoutRequest;
use App\Http\Requests\StoreCheckoutRequest;
use App\Http\Resources\OrderDetailResource;
use App\Models\Address;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutSessionService;
use App\Services\Order\OrderCreationService;
use App\Services\Pricing\PricingService;
use App\Services\Shipping\ShippingEngine;
use App\Support\IsoCountry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        protected ShippingEngine $shippingEngine,
        protected CheckoutSessionService $checkoutSessionService,
        protected OrderCreationService $orderCreationService,
        protected CartService $cartService,
        protected PricingService $pricingService
    ) {}

    public function show(Request $request): Response
    {
        $user = $request->user();
        $sessionId = $request->session()->getId();
        $cart = $this->cartService->getOrCreateCart($user, $sessionId);
        $cartData = $this->cartService->summarize($cart);

        try {
            $countryCode = IsoCountry::normalize((string) $request->query('country_code', 'GB'));
        } catch (\InvalidArgumentException) {
            $countryCode = 'GB';
        }

        $cart->load(['items.product', 'items.variant']);
        $shippingMethods = $this->shippingEngine->calculateRates(
            $cartData['totals']['subtotal_amount'] ?? '0.00',
            $countryCode
        );

        $requestedMethod = (string) $request->query('shipping_method_code', $shippingMethods->first()['code'] ?? 'UK_STANDARD');
        $selected = $this->shippingEngine->requireRate($shippingMethods, $requestedMethod, $countryCode);

        $previewTotals = $this->pricingService->calculateTotals(
            $cart->items->map(fn ($item) => [
                'product' => $item->product,
                'variant' => $item->variant,
                'quantity' => $item->quantity,
                'discount' => 0,
            ]),
            $selected['rate_amount'] ?? $selected['rate'],
            0,
            0,
            $cartData['totals']['currency'] ?? 'GBP'
        );

        $addresses = $user
            ? Address::query()->where('user_id', $user->id)->orderByDesc('is_default')->get([
                'id', 'full_name', 'address_line_1', 'address_line_2', 'city', 'state_county', 'postal_code', 'country_code', 'phone', 'is_default', 'type',
            ])
            : collect();

        return Inertia::render('Checkout/Index', [
            'cart' => $cartData,
            'availableShippingMethods' => $shippingMethods->values(),
            'selectedShippingMethod' => $selected,
            'checkoutPreview' => [
                'country_code' => $countryCode,
                'shipping_method_code' => $selected['code'],
                'items_subtotal' => $previewTotals['subtotal_amount'],
                'discount' => $previewTotals['discount_amount'],
                'shipping' => $previewTotals['shipping_amount'],
                'tax' => $previewTotals['tax_amount'],
                'grand_total' => $previewTotals['grand_total_amount'],
                'currency' => $previewTotals['currency'],
            ],
            'addresses' => $addresses,
            'paymentMethods' => [
                [
                    'code' => PaymentMethodType::BANK_TRANSFER->value,
                    'name' => PaymentMethodType::BANK_TRANSFER->label(),
                    'description' => 'Direct transfer to our designated business bank account. Reference number required.',
                ],
                [
                    'code' => PaymentMethodType::CRYPTOCURRENCY->value,
                    'name' => PaymentMethodType::CRYPTOCURRENCY->label(),
                    'description' => 'Settlement via the supported cryptocurrency networks listed after you place the order.',
                ],
            ],
            'paymentInstructions' => $this->customerPaymentInstructions(),
        ]);
    }

    public function prepare(PrepareCheckoutRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $items = $validated['items'] ?? $this->cartItemsPayload($request);

        $snapshot = $this->checkoutSessionService->prepareCheckout([
            'customer' => $request->user(),
            'items' => $items,
            'shipping_destination' => $validated['shipping_destination'],
            'shipping_method_code' => $validated['shipping_method_code'] ?? 'UK_STANDARD',
            'billing_destination' => $validated['billing_destination'] ?? null,
            'customer_notes' => $validated['customer_notes'] ?? null,
        ]);

        $request->session()->put('checkout_token', $snapshot['checkout_token'] ?? null);

        if ($request->wantsJson()) {
            return response()->json($snapshot);
        }

        return back()->with('success', 'Checkout totals confirmed.');
    }

    public function store(StoreCheckoutRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $items = $validated['items'] ?? $this->cartItemsPayload($request);

        $order = $this->orderCreationService->createOrder([
            'customer' => $user,
            'items' => $items,
            'shipping_destination' => $validated['shipping_destination'] ?? [],
            'shipping_method_code' => $validated['shipping_method_code'] ?? 'UK_STANDARD',
            'payment_method' => PaymentMethodType::from($validated['payment_method']),
            'idempotency_key' => $validated['idempotency_key'] ?? $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            'checkout_token' => $validated['checkout_token'] ?? $request->session()->get('checkout_token'),
            'customer_notes' => $validated['customer_notes'] ?? null,
        ]);

        $request->session()->forget('checkout_token');

        $payload = (new OrderDetailResource($order))->resolve();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Order created successfully.',
                'order' => $payload,
            ]);
        }

        return redirect()
            ->route('orders.show', $order->id)
            ->with('success', 'Order created successfully. Please submit your payment reference.');
    }

    /**
     * @return list<array{product_id: string, variant_id: ?string, quantity: int}>
     */
    protected function cartItemsPayload(Request $request): array
    {
        $cart = $this->cartService->getOrCreateCart($request->user(), $request->session()->getId());
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
    protected function customerPaymentInstructions(): array
    {
        return [
            'bank_transfer' => array_filter(config('payments.bank_transfer', [])),
            'crypto' => config('payments.crypto', ['networks' => [], 'reference_hint' => null]),
        ];
    }
}
