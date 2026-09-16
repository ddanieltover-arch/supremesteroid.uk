<?php

declare(strict_types=1);

namespace App\Services\Cart;

use App\Exceptions\ProductNotPurchasableException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Catalogue\ProductPurchaseEligibilityService;
use App\Services\Inventory\InventoryReservationService;
use App\Services\Pricing\PricingService;
use App\Services\Shipping\ShippingEngine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CartService
{
    public function __construct(
        protected ProductPurchaseEligibilityService $eligibilityService,
        protected PricingService $pricingService,
        protected ShippingEngine $shippingEngine,
        protected InventoryReservationService $reservations
    ) {}

    public function findCart(?User $user = null, ?string $sessionId = null): ?Cart
    {
        if ($user) {
            return Cart::query()->where('user_id', $user->id)->first();
        }

        if ($sessionId) {
            return Cart::query()
                ->where('session_id', $sessionId)
                ->whereNull('user_id')
                ->first();
        }

        return null;
    }

    /**
     * Get or create a cart for an authenticated user or guest session.
     */
    public function getOrCreateCart(?User $user = null, ?string $sessionId = null): Cart
    {
        if ($user) {
            /** @var Cart $cart */
            $cart = Cart::firstOrCreate(
                ['user_id' => $user->id],
                ['currency' => 'GBP']
            );
            return $cart;
        }

        if ($sessionId) {
            /** @var Cart $cart */
            $cart = Cart::firstOrCreate(
                ['session_id' => $sessionId, 'user_id' => null],
                ['currency' => 'GBP']
            );
            return $cart;
        }

        throw new InvalidArgumentException('Either a user or a guest session_id is required to resolve a cart.');
    }

    /**
     * Authorize that a user or session owns the given cart.
     *
     * @throws AuthorizationException
     */
    public function authorizeCart(Cart $cart, ?User $user = null, ?string $sessionId = null): void
    {
        if ($cart->user_id !== null) {
            if (! $user || $cart->user_id !== $user->id) {
                throw new AuthorizationException('Unauthorized access to user cart.');
            }
            return;
        }

        if ($cart->session_id !== null) {
            if ($user) {
                // An authenticated user can access their unattached guest session prior to merge
                return;
            }
            if (! $sessionId || $cart->session_id !== $sessionId) {
                throw new AuthorizationException('Unauthorized access to guest cart.');
            }
        }
    }

    /**
     * Add an item to cart or increment its quantity safely.
     */
    public function addItem(
        Cart $cart,
        Product $product,
        ?ProductVariant $variant = null,
        int $quantity = 1,
        ?User $actor = null,
        ?string $sessionId = null
    ): CartItem {
        $this->authorizeCart($cart, $actor, $sessionId);

        if ($quantity <= 0) {
            throw new InvalidArgumentException("Cart item quantity must be at least 1 (received {$quantity}).");
        }

        // Validate variant belongs to product
        if ($variant && $variant->product_id !== $product->id) {
            throw new InvalidArgumentException("Variant '{$variant->sku}' does not belong to product '{$product->sku}'.");
        }

        return DB::transaction(function () use ($cart, $product, $variant, $quantity) {
            /** @var CartItem|null $existing */
            $existing = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->where('product_variant_id', $variant?->id)
                ->first();

            $newQty = $existing ? $existing->quantity + $quantity : $quantity;

            // Authoritatively verify purchasability and inventory
            $this->eligibilityService->assertPurchasable($product, $variant, $newQty, true);

            if ($existing) {
                $existing->quantity = $newQty;
                $existing->save();
                return $existing;
            }

            return CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'quantity' => $quantity,
            ]);
        });
    }

    /**
     * Update quantity of a cart item.
     */
    public function updateQuantity(
        CartItem $item,
        int $quantity,
        ?User $actor = null,
        ?string $sessionId = null
    ): ?CartItem {
        $this->authorizeCart($item->cart, $actor, $sessionId);

        if ($quantity < 0) {
            throw new InvalidArgumentException('Cart quantity cannot be negative.');
        }

        if ($quantity === 0) {
            $item->delete();
            return null;
        }

        $product = $item->product;
        $variant = $item->variant;

        $this->eligibilityService->assertPurchasable($product, $variant, $quantity, true);

        $item->quantity = $quantity;
        $item->save();

        return $item;
    }

    /**
     * Remove an item from cart.
     */
    public function removeItem(CartItem $item, ?User $actor = null, ?string $sessionId = null): void
    {
        $this->authorizeCart($item->cart, $actor, $sessionId);
        $item->delete();
    }

    public function clearCart(Cart $cart, ?User $actor = null, ?string $sessionId = null): void
    {
        $this->authorizeCart($cart, $actor, $sessionId);
        $cart->items()->delete();
    }

    /**
     * Merge a guest cart into an authenticated customer's cart.
     * Combines quantities subject to available inventory without exceeding available stock.
     */
    public function mergeGuestCart(Cart $guestCart, User $user): Cart
    {
        return DB::transaction(function () use ($guestCart, $user) {
            $this->reservations->releaseExpiredReservations();
            $userCart = $this->getOrCreateCart($user);

            $guestCart->unsetRelation('items');
            $guestCart->load(['items.product', 'items.variant']);

            foreach ($guestCart->items as $guestItem) {
                $product = $guestItem->product;
                $variant = $guestItem->variant;

                // Check eligibility
                if (! $this->eligibilityService->isEligible($product, $variant, 1, false)) {
                    continue;
                }

                // Determine maximum available inventory
                $inventory = $variant ? $variant->inventory : $product->inventory;
                $availableStock = $inventory
                    ? max(0, (int) $inventory->quantity_on_hand - (int) $inventory->quantity_reserved)
                    : 999;

                if ($availableStock <= 0) {
                    continue;
                }

                /** @var CartItem|null $existing */
                $existing = CartItem::query()
                    ->where('cart_id', $userCart->id)
                    ->where('product_id', $guestItem->product_id)
                    ->where('product_variant_id', $guestItem->product_variant_id)
                    ->first();

                if ($existing) {
                    $combinedQty = $existing->quantity + $guestItem->quantity;
                    // Cap at available stock
                    $existing->quantity = min($combinedQty, $availableStock);
                    $existing->save();
                } else {
                    $qtyToSet = min($guestItem->quantity, $availableStock);
                    if ($qtyToSet > 0) {
                        CartItem::create([
                            'cart_id' => $userCart->id,
                            'product_id' => $guestItem->product_id,
                            'product_variant_id' => $guestItem->product_variant_id,
                            'quantity' => $qtyToSet,
                        ]);
                    }
                }
            }

            // Remove the guest cart once merged
            $guestCart->items()->delete();
            $guestCart->delete();

            return $userCart->load(['items.product', 'items.variant']);
        });
    }

    /**
     * Merge a guest cart identified by the pre-login session id into the authenticated customer cart.
     */
    public function mergeGuestCartBySession(string $guestSessionId, User $user): Cart
    {
        $guestCart = Cart::query()
            ->where('session_id', $guestSessionId)
            ->whereNull('user_id')
            ->first();

        if (! $guestCart) {
            return $this->getOrCreateCart($user);
        }

        return $this->mergeGuestCart($guestCart, $user);
    }

    /**
     * Authoritatively calculate cart items and summary totals.
     *
     * @return array{
     *     items: list<array{
     *         id: string,
     *         product_id: string,
     *         product_name: string,
     *         product_slug: string,
     *         product_sku: string,
     *         variant_id: ?string,
     *         variant_name: ?string,
     *         unit_price: string,
     *         quantity: int,
     *         subtotal: string,
     *         line_total: string,
     *         image_url: ?string,
     *         is_eligible: bool,
     *         ineligibility_reasons: list<string>
     *     }>,
     *     totals: array{
     *         subtotal_amount: string,
     *         subtotal_pence: int,
     *         discount_amount: string,
     *         shipping_amount: string,
     *         tax_amount: string,
     *         grand_total_amount: string,
     *         grand_total_pence: int,
     *         items_count: int,
     *         currency: string
     *     },
     *     shipping_eligibility: array<string, mixed>,
     *     is_checkout_ready: bool
     * }
     */
    public function calculateCart(Cart $cart): array
    {
        $cart->load(['items.product.images', 'items.variant']);

        $preparedItems = [];
        $calculatedItems = [];
        $allEligible = true;

        foreach ($cart->items as $item) {
            $product = $item->product;
            $variant = $item->variant;

            $eligibility = $this->eligibilityService->checkEligibility($product, $variant, $item->quantity, true);
            if (! $eligibility['is_eligible']) {
                $allEligible = false;
            }

            $lineCalc = $this->pricingService->calculateLineItem($product, $variant, $item->quantity, 0);

            $preparedItems[] = [
                'product' => $product,
                'variant' => $variant,
                'quantity' => $item->quantity,
                'discount' => 0,
            ];

            $calculatedItems[] = [
                'id' => $item->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_slug' => $product->slug,
                'product_sku' => $product->sku,
                'variant_id' => $variant?->id,
                'variant_name' => $variant?->name,
                'unit_price' => $lineCalc['unit_price_amount'],
                'quantity' => $item->quantity,
                'subtotal' => $lineCalc['line_subtotal_amount'],
                'line_total' => $lineCalc['line_total_amount'],
                'image_url' => $this->primaryImageUrl($product),
                'is_eligible' => $eligibility['is_eligible'],
                'ineligibility_reasons' => $eligibility['reasons'],
            ];
        }

        $totals = $this->pricingService->calculateTotals($preparedItems, 0, 0, 0, $cart->currency);

        return [
            'items' => $calculatedItems,
            'totals' => $totals,
            'shipping_eligibility' => $this->shippingEngine->freeShippingEligibility($totals['subtotal_amount'] ?? '0.00'),
            'is_checkout_ready' => $allEligible && count($calculatedItems) > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summarize(?Cart $cart): array
    {
        if (! $cart) {
            return [
                'id' => null,
                'items' => [],
                'totals' => [
                    'subtotal_amount' => '0.00',
                    'subtotal_pence' => 0,
                    'discount_amount' => '0.00',
                    'shipping_amount' => '0.00',
                    'tax_amount' => '0.00',
                    'grand_total_amount' => '0.00',
                    'grand_total_pence' => 0,
                    'items_count' => 0,
                    'currency' => 'GBP',
                ],
                'shipping_eligibility' => $this->shippingEngine->freeShippingEligibility('0.00'),
                'is_checkout_ready' => false,
            ];
        }

        return [
            'id' => $cart->id,
            ...$this->calculateCart($cart),
        ];
    }

    protected function primaryImageUrl(Product $product): ?string
    {
        if (! $product->relationLoaded('images')) {
            return null;
        }

        $image = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

        return $image?->url;
    }
}
