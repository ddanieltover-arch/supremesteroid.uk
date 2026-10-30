<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Enums\PaymentMethodType;
use App\Http\Requests\PrepareCheckoutRequest;
use App\Http\Requests\SaveCheckoutDeliveryRequest;
use App\Http\Requests\SaveCheckoutDetailsRequest;
use App\Http\Requests\SaveCheckoutPaymentRequest;
use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\StoreCheckoutRequest;
use App\Http\Resources\OrderDetailResource;
use App\Models\Order;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutFlowService;
use App\Services\Checkout\CheckoutSessionService;
use App\Services\Order\OrderCreationService;
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
        protected CheckoutSessionService $checkoutSessionService,
        protected CheckoutFlowService $checkoutFlowService,
        protected OrderCreationService $orderCreationService,
        protected CartService $cartService
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        // Accept buy-now query params on first paint so Instant Visits can open
        // checkout immediately without waiting for a separate POST.
        if ($request->boolean('buy_now') && $request->filled('product_id')) {
            try {
                $this->checkoutFlowService->startBuyNow(
                    $request,
                    (string) $request->query('product_id'),
                    $request->filled('product_variant_id') ? (string) $request->query('product_variant_id') : null,
                    max(1, (int) $request->query('quantity', 1)),
                );
            } catch (\Throwable $exception) {
                $message = method_exists($exception, 'getCustomerMessage')
                    ? (string) $exception->getCustomerMessage()
                    : 'This item could not be checked out right now.';

                return redirect()
                    ->back()
                    ->with('error', $message);
            }
        }

        if ($redirect = $this->checkoutFlowService->gate($request, 'details')) {
            return $redirect;
        }

        return Inertia::render('Checkout/Index', $this->checkoutFlowService->pageProps($request, 'details'));
    }

    public function buyNow(StoreCartItemRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $this->checkoutFlowService->startBuyNow(
            $request,
            (string) $validated['product_id'],
            ! empty($validated['product_variant_id']) ? (string) $validated['product_variant_id'] : null,
            (int) $validated['quantity'],
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Ready for checkout.',
                'redirect' => route('checkout.show'),
            ]);
        }

        return redirect()->route('checkout.show');
    }

    public function saveDetails(SaveCheckoutDetailsRequest $request): RedirectResponse
    {
        $this->checkoutFlowService->saveDetails($request, $request->validated());

        return redirect()->route('checkout.delivery');
    }

    public function delivery(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->checkoutFlowService->gate($request, 'delivery')) {
            return $redirect;
        }

        return Inertia::render('Checkout/Delivery', $this->checkoutFlowService->pageProps($request, 'delivery'));
    }

    public function saveDelivery(SaveCheckoutDeliveryRequest $request): RedirectResponse
    {
        $this->checkoutFlowService->saveDelivery($request, $request->validated());

        return redirect()->route('checkout.payment');
    }

    public function payment(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->checkoutFlowService->gate($request, 'payment')) {
            return $redirect;
        }

        return Inertia::render('Checkout/Payment', $this->checkoutFlowService->pageProps($request, 'payment'));
    }

    public function savePayment(SaveCheckoutPaymentRequest $request): RedirectResponse
    {
        $this->checkoutFlowService->savePaymentMethod($request, $request->validated());

        return back();
    }

    public function acknowledge(Request $request): RedirectResponse
    {
        $this->checkoutFlowService->acknowledgePriceChange($request);

        return back()->with('success', 'Updated pricing confirmed.');
    }

    public function confirmation(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->checkoutFlowService->gate($request, 'confirmation')) {
            return $redirect;
        }

        $orderQuery = Order::query()->with(['items.product.images', 'payments.submissions.proofFile', 'statusHistory']);
        $actor = $this->checkoutFlowService->actor($request);
        if ($actor) {
            $orderQuery->where('user_id', $actor->id);
        }

        $order = $orderQuery->findOrFail($request->session()->get('checkout_order_id'));

        return Inertia::render('Checkout/Confirmation', [
            'order' => (new OrderDetailResource($order))->resolve(),
            'paymentInstructions' => $this->checkoutFlowService->customerPaymentInstructions(),
            'checkout_step' => 'confirmation',
            'checkout_steps' => $this->checkoutFlowService->progressSteps('confirmation', true, true, true),
            'seo' => [
                'title' => 'Order placed',
                'description' => 'Order confirmation.',
                'robots' => 'noindex,nofollow',
            ],
        ]);
    }

    public function prepare(PrepareCheckoutRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $items = $validated['items'] ?? $this->checkoutFlowService->cartItemsPayload($request);

        $snapshot = $this->checkoutSessionService->prepareCheckout([
            'customer' => $this->checkoutFlowService->requireActor($request),
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
        $user = $this->checkoutFlowService->requireActor($request);
        $items = $validated['items'] ?? $this->checkoutFlowService->cartItemsPayload($request);
        $token = $validated['checkout_token'] ?? $request->session()->get('checkout_token');
        $idempotencyKey = $validated['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? $request->session()->get('checkout_idempotency_key')
            ?? (string) Str::uuid();

        $request->session()->put('checkout_idempotency_key', $idempotencyKey);

        // Stale acknowledgement flags must not block place-order after totals were refreshed.
        $request->session()->forget('checkout_requires_acknowledgement');

        $order = $this->orderCreationService->createOrder([
            'customer' => $user,
            'items' => $items,
            'shipping_destination' => $validated['shipping_destination'] ?? [],
            'shipping_method_code' => $validated['shipping_method_code'] ?? 'UK_STANDARD',
            'payment_method' => PaymentMethodType::from($validated['payment_method']),
            'idempotency_key' => $idempotencyKey,
            'checkout_token' => $token,
            'customer_notes' => $validated['customer_notes'] ?? null,
        ]);

        if ($this->checkoutFlowService->hasBuyNow($request)) {
            $this->checkoutFlowService->clearBuyNow($request);
        } else {
            $cart = $this->cartService->resolveRequestCart($request);
            $this->cartService->clearCart($cart, $request->user(), $request->session()->getId());
        }

        $request->session()->forget('checkout_token');
        $request->session()->put('checkout_order_id', $order->id);

        $payload = (new OrderDetailResource($order))->resolve();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Order created successfully.',
                'order' => $payload,
            ]);
        }

        return redirect()
            ->route('checkout.confirmation')
            ->with('success', 'Order created successfully. Please submit your payment reference.');
    }

    /**
     * @return list<array{product_id: string, variant_id: ?string, quantity: int}>
     */
    protected function cartItemsPayload(Request $request): array
    {
        return $this->checkoutFlowService->cartItemsPayload($request);
    }
}
