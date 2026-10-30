<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected CheckoutFlowService $checkoutFlowService,
    ) {}

    protected function resolveSessionId(Request $request): string
    {
        return $request->session()->getId();
    }

    public function index(Request $request): Response|JsonResponse
    {
        $cart = $this->cartService->resolveRequestCart($request);
        $payload = $this->cartService->summarize($cart);

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Cart/Index', [
            'cart' => $payload,
            'seo' => [
                'title' => 'Cart',
                'description' => 'Review your basket.',
                'robots' => 'noindex,nofollow',
            ],
        ]);
    }

    public function addItem(StoreCartItemRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $sessionId = $this->resolveSessionId($request);
        $cart = $this->cartService->resolveRequestCart($request);

        $product = Product::query()->findOrFail($validated['product_id']);
        $variant = ! empty($validated['product_variant_id'])
            ? ProductVariant::query()->findOrFail($validated['product_variant_id'])
            : null;

        $this->cartService->addItem(
            cart: $cart,
            product: $product,
            variant: $variant,
            quantity: (int) $validated['quantity'],
            actor: $user,
            sessionId: $sessionId
        );

        $summarized = $this->cartService->summarize($cart->fresh('items'));

        // Only cancel express buy-now after a successful add that leaves a shopping cart.
        // Failed adds never reach here; empty carts must not wipe buy-now into a double-empty gate.
        if (((int) ($summarized['totals']['items_count'] ?? 0)) > 0) {
            $this->checkoutFlowService->clearBuyNow($request);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Item added to cart.',
                'cart' => $summarized,
            ]);
        }

        return back()->with('success', 'Item added to cart.');
    }

    public function updateQuantity(UpdateCartItemRequest $request, string $itemId): RedirectResponse|JsonResponse
    {
        $this->cartService->resolveRequestCart($request);
        $validated = $request->validated();
        $item = CartItem::query()->with('cart')->findOrFail($itemId);
        $user = $request->user();
        $sessionId = $this->resolveSessionId($request);

        if ((int) $validated['quantity'] === 0) {
            $cart = $item->cart;
            $this->cartService->removeItem($item, $user, $sessionId);
        } else {
            $this->cartService->updateQuantity(
                item: $item,
                quantity: (int) $validated['quantity'],
                actor: $user,
                sessionId: $sessionId
            );
            $cart = $item->cart()->first();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Cart updated.',
                'cart' => $this->cartService->summarize($cart?->fresh('items') ?? $cart),
            ]);
        }

        return back()->with('success', 'Cart updated.');
    }

    public function removeItem(Request $request, string $itemId): RedirectResponse|JsonResponse
    {
        $this->cartService->resolveRequestCart($request);
        $item = CartItem::query()->with('cart')->findOrFail($itemId);
        $user = $request->user();
        $sessionId = $this->resolveSessionId($request);
        $cart = $item->cart;
        $this->cartService->removeItem($item, $user, $sessionId);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Item removed.',
                'cart' => $this->cartService->summarize($cart->fresh('items') ?? $cart),
            ]);
        }

        return back()->with('success', 'Item removed from cart.');
    }

    public function clear(Request $request): RedirectResponse|JsonResponse
    {
        $cart = $this->cartService->resolveRequestCart($request);
        $this->cartService->clearCart($cart, $request->user(), $this->resolveSessionId($request));

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Cart cleared.',
                'cart' => $this->cartService->summarize($cart->fresh('items')),
            ]);
        }

        return back()->with('success', 'Cart cleared.');
    }
}
