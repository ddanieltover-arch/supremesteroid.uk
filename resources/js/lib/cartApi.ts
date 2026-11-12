import { jsonFetch } from '@/lib/http';
import type { CartItem, CartPayload } from '@/types';

type CartMutationResponse = {
  message?: string;
  cart: CartPayload;
};

function moneyTimes(amount: string | null | undefined, quantity: number): string {
  const pence = Math.round(Number(amount ?? 0) * 100) * Math.max(0, quantity);
  return (pence / 100).toFixed(2);
}

function recountCart(items: CartItem[], currency: string, base?: CartPayload | null): CartPayload {
  const subtotal = items.reduce((sum, item) => sum + Math.round(Number(item.line_total) * 100), 0);
  const itemsCount = items.reduce((sum, item) => sum + item.quantity, 0);
  const subtotalAmount = (subtotal / 100).toFixed(2);
  const threshold = Number(base?.shipping_eligibility?.free_shipping_threshold ?? 0);
  const subtotalNumber = Number(subtotalAmount);
  const remaining = threshold > 0 ? Math.max(0, threshold - subtotalNumber) : 0;
  const progress =
    threshold > 0 ? Math.min(100, Math.round((subtotalNumber / threshold) * 100)) : (base?.shipping_eligibility?.progress_percent ?? 0);

  return {
    id: base?.id ?? null,
    items,
    totals: {
      subtotal_amount: subtotalAmount,
      discount_amount: '0.00',
      shipping_amount: '0.00',
      tax_amount: '0.00',
      grand_total_amount: subtotalAmount,
      items_count: itemsCount,
      currency,
    },
    shipping_eligibility: {
      free_shipping_threshold: base?.shipping_eligibility?.free_shipping_threshold ?? '0.00',
      current_eligible_subtotal: subtotalAmount,
      remaining_to_threshold: remaining.toFixed(2),
      free_shipping_eligible: threshold > 0 ? remaining <= 0 : Boolean(base?.shipping_eligibility?.free_shipping_eligible),
      progress_percent: progress,
      currency,
    },
    is_checkout_ready: itemsCount > 0,
  };
}

type CartListener = (cart: CartPayload) => void;
type SyncListener = (syncing: boolean) => void;

let cartOverride: CartPayload | null = null;
/** Cart state before the latest optimistic add — restored if the server mutation fails. */
let optimisticRollback: CartPayload | null | undefined = undefined;
const listeners = new Set<CartListener>();
const syncListeners = new Set<SyncListener>();
let cartSync: Promise<void> = Promise.resolve();
let pendingMutations = 0;

function notifySync(): void {
  const syncing = pendingMutations > 0;
  syncListeners.forEach((listener) => listener(syncing));
}

function enqueueCartMutation<T>(run: () => Promise<T>): Promise<T> {
  pendingMutations += 1;
  notifySync();

  const next = cartSync.catch(() => undefined).then(run);
  cartSync = next.then(
    () => undefined,
    () => undefined,
  );

  return next.finally(() => {
    pendingMutations = Math.max(0, pendingMutations - 1);
    notifySync();
  });
}

export function getCartOverride(): CartPayload | null {
  return cartOverride;
}

export function publishCart(cart: CartPayload): CartPayload {
  cartOverride = cart;
  listeners.forEach((listener) => listener(cart));
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent('ss:cart', { detail: cart }));
  }
  return cart;
}

export function clearCartOverride(): void {
  cartOverride = null;
}

function restoreOptimisticRollback(): void {
  if (optimisticRollback === undefined) {
    return;
  }
  const previous = optimisticRollback;
  optimisticRollback = undefined;
  publishCart(previous ?? recountCart([], 'GBP'));
}

/** Instant local cart update used before the server round-trip finishes. */
export function optimisticAddToCart(
  current: CartPayload | null | undefined,
  line: {
    product_id: string;
    product_name: string;
    product_slug: string;
    product_sku: string;
    variant_id?: string | null;
    variant_name?: string | null;
    unit_price: string;
    quantity: number;
    image_url?: string | null;
  },
): CartPayload {
  // Only snapshot once per optimistic burst so chained adds keep the pre-burst cart.
  if (optimisticRollback === undefined) {
    optimisticRollback = cartOverride ?? (current ? { ...current } : null);
  }

  const currency = current?.totals?.currency ?? 'GBP';
  const quantity = Math.max(1, line.quantity);
  const items = [...(current?.items ?? [])];
  const existingIndex = items.findIndex(
    (item) => item.product_id === line.product_id && (item.variant_id ?? null) === (line.variant_id ?? null),
  );

  if (existingIndex >= 0) {
    const existing = items[existingIndex];
    const nextQty = existing.quantity + quantity;
    items[existingIndex] = {
      ...existing,
      quantity: nextQty,
      subtotal: moneyTimes(existing.unit_price, nextQty),
      line_total: moneyTimes(existing.unit_price, nextQty),
      is_eligible: true,
      ineligibility_reasons: [],
    };
  } else {
    items.push({
      id: `optimistic-${line.product_id}-${line.variant_id ?? 'base'}`,
      product_id: line.product_id,
      product_name: line.product_name,
      product_slug: line.product_slug,
      product_sku: line.product_sku,
      variant_id: line.variant_id ?? null,
      variant_name: line.variant_name ?? null,
      unit_price: line.unit_price,
      quantity,
      subtotal: moneyTimes(line.unit_price, quantity),
      line_total: moneyTimes(line.unit_price, quantity),
      image_url: line.image_url ?? null,
      is_eligible: true,
      ineligibility_reasons: [],
    });
  }

  return publishCart(recountCart(items, currency, current));
}

/** Instant local quantity change before the PATCH round-trip finishes. */
export function optimisticUpdateCartItemQuantity(
  current: CartPayload | null | undefined,
  itemId: string,
  quantity: number,
): CartPayload | null {
  const base = cartOverride ?? current ?? null;
  if (!base?.items?.some((item) => item.id === itemId)) {
    // Never publish an empty cart when the live override is missing — that
    // made +/- appear to wipe the basket.
    return null;
  }

  if (optimisticRollback === undefined) {
    optimisticRollback = base;
  }

  const currency = base.totals?.currency ?? 'GBP';
  const nextQty = Math.max(0, quantity);
  const items =
    nextQty === 0
      ? base.items.filter((item) => item.id !== itemId)
      : base.items.map((item) => {
          if (item.id !== itemId) {
            return item;
          }
          return {
            ...item,
            quantity: nextQty,
            subtotal: moneyTimes(item.unit_price, nextQty),
            line_total: moneyTimes(item.unit_price, nextQty),
          };
        });

  return publishCart(recountCart(items, currency, base));
}

/** Instant local remove before the DELETE round-trip finishes. */
export function optimisticRemoveCartItem(
  current: CartPayload | null | undefined,
  itemId: string,
): CartPayload | null {
  return optimisticUpdateCartItemQuantity(current, itemId, 0);
}

export function subscribeCart(listener: CartListener): () => void {
  listeners.add(listener);
  return () => {
    listeners.delete(listener);
  };
}

export function isCartSyncing(): boolean {
  return pendingMutations > 0;
}

export function subscribeCartSync(listener: SyncListener): () => void {
  syncListeners.add(listener);
  listener(isCartSyncing());
  return () => {
    syncListeners.delete(listener);
  };
}

/** Wait until every in-flight cart mutation has settled. */
export async function awaitCartSync(): Promise<void> {
  await cartSync;
}

/**
 * Wait for cart mutations, then confirm the server basket has items.
 * Throws when the basket cannot be confirmed (failed optimistic add, empty cart, etc.).
 */
export async function checkoutWhenReady(): Promise<CartPayload> {
  await awaitCartSync();

  const local = getCartOverride();
  const localCount = local?.totals?.items_count ?? 0;
  const hasOnlyOptimistic =
    (local?.items?.length ?? 0) > 0 &&
    (local?.items ?? []).every((item) => String(item.id).startsWith('optimistic-'));

  if (localCount > 0 && !hasOnlyOptimistic) {
    return local as CartPayload;
  }

  const serverCart = await jsonFetch<CartPayload>('/cart');
  if (!serverCart || (serverCart.totals?.items_count ?? 0) <= 0) {
    if (hasOnlyOptimistic) {
      restoreOptimisticRollback();
    }
    throw new Error('Basket could not be confirmed — try again.');
  }

  return publishCart(serverCart);
}

/** Prefer the latest optimistic UI while more mutations are still queued. */
function publishAuthoritativeCart(cart: CartPayload): CartPayload {
  optimisticRollback = undefined;
  // pendingMutations still includes the mutation that just finished.
  if (pendingMutations > 1) {
    return getCartOverride() ?? cart;
  }
  return publishCart(cart);
}

export async function addCartItem(input: {
  product_id: string;
  product_variant_id?: string | null;
  quantity?: number;
}): Promise<CartPayload> {
  return enqueueCartMutation(async () => {
    try {
      const payload = await jsonFetch<CartMutationResponse>('/cart/items', {
        method: 'POST',
        body: JSON.stringify({
          product_id: input.product_id,
          product_variant_id: input.product_variant_id ?? null,
          quantity: input.quantity ?? 1,
        }),
      });
      return publishAuthoritativeCart(payload.cart);
    } catch (error) {
      restoreOptimisticRollback();
      throw error;
    }
  });
}

/**
 * Resolve the next absolute quantity from the live override (not React props),
 * so rapid +/- clicks do not all reuse the same stale quantity.
 */
export function bumpCartItemQuantity(
  itemId: string,
  delta: number,
  fallback?: CartPayload | null,
): Promise<CartPayload> {
  const current = getCartOverride() ?? fallback ?? null;
  const item = current?.items?.find((entry) => entry.id === itemId);
  if (!item) {
    return Promise.reject(new Error('That item is no longer in your basket.'));
  }

  const nextQty = Math.max(0, item.quantity + delta);
  if (nextQty === 0) {
    return removeCartItem(itemId, current);
  }

  return updateCartItemQuantity(itemId, nextQty, current);
}

export async function updateCartItemQuantity(
  itemId: string,
  quantity: number,
  fallback?: CartPayload | null,
): Promise<CartPayload> {
  const current = getCartOverride() ?? fallback ?? null;
  optimisticUpdateCartItemQuantity(current, itemId, quantity);

  return enqueueCartMutation(async () => {
    try {
      // Prefer the latest optimistic qty. If a later remove cleared the line,
      // DELETE — do not fall back to this call's stale absolute quantity.
      const latestItem = getCartOverride()?.items?.find((entry) => entry.id === itemId);
      if (!latestItem || latestItem.quantity <= 0) {
        const payload = await jsonFetch<CartMutationResponse>(`/cart/items/${itemId}`, {
          method: 'DELETE',
        });
        return publishAuthoritativeCart(payload.cart);
      }

      const payload = await jsonFetch<CartMutationResponse>(`/cart/items/${itemId}`, {
        method: 'PATCH',
        body: JSON.stringify({ quantity: latestItem.quantity }),
      });
      return publishAuthoritativeCart(payload.cart);
    } catch (error) {
      restoreOptimisticRollback();
      throw error;
    }
  });
}

export async function removeCartItem(
  itemId: string,
  fallback?: CartPayload | null,
): Promise<CartPayload> {
  const current = getCartOverride() ?? fallback ?? null;
  optimisticRemoveCartItem(current, itemId);

  return enqueueCartMutation(async () => {
    try {
      // A later bump may have put the line back while we were queued.
      const revived = getCartOverride()?.items?.find((entry) => entry.id === itemId);
      if (revived && revived.quantity > 0) {
        const payload = await jsonFetch<CartMutationResponse>(`/cart/items/${itemId}`, {
          method: 'PATCH',
          body: JSON.stringify({ quantity: revived.quantity }),
        });
        return publishAuthoritativeCart(payload.cart);
      }

      const payload = await jsonFetch<CartMutationResponse>(`/cart/items/${itemId}`, {
        method: 'DELETE',
      });
      return publishAuthoritativeCart(payload.cart);
    } catch (error) {
      restoreOptimisticRollback();
      throw error;
    }
  });
}

export async function clearCart(): Promise<CartPayload> {
  return enqueueCartMutation(async () => {
    const payload = await jsonFetch<CartMutationResponse>('/cart', {
      method: 'DELETE',
    });
    return publishCart(payload.cart);
  });
}
