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

function recountCart(items: CartItem[], currency: string): CartPayload {
  const subtotal = items.reduce((sum, item) => sum + Math.round(Number(item.line_total) * 100), 0);
  const itemsCount = items.reduce((sum, item) => sum + item.quantity, 0);
  const subtotalAmount = (subtotal / 100).toFixed(2);

  return {
    id: null,
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
      free_shipping_threshold: '0.00',
      current_eligible_subtotal: subtotalAmount,
      remaining_to_threshold: '0.00',
      free_shipping_eligible: false,
      progress_percent: 0,
      currency,
    },
    is_checkout_ready: itemsCount > 0,
  };
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

  return publishCart(recountCart(items, currency));
}

type CartListener = (cart: CartPayload) => void;
type SyncListener = (syncing: boolean) => void;

let cartOverride: CartPayload | null = null;
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

export async function addCartItem(input: {
  product_id: string;
  product_variant_id?: string | null;
  quantity?: number;
}): Promise<CartPayload> {
  return enqueueCartMutation(async () => {
    const payload = await jsonFetch<CartMutationResponse>('/cart/items', {
      method: 'POST',
      body: JSON.stringify({
        product_id: input.product_id,
        product_variant_id: input.product_variant_id ?? null,
        quantity: input.quantity ?? 1,
      }),
    });
    return publishCart(payload.cart);
  });
}

export async function updateCartItemQuantity(itemId: string, quantity: number): Promise<CartPayload> {
  return enqueueCartMutation(async () => {
    const payload = await jsonFetch<CartMutationResponse>(`/cart/items/${itemId}`, {
      method: 'PATCH',
      body: JSON.stringify({ quantity }),
    });
    return publishCart(payload.cart);
  });
}

export async function removeCartItem(itemId: string): Promise<CartPayload> {
  return enqueueCartMutation(async () => {
    const payload = await jsonFetch<CartMutationResponse>(`/cart/items/${itemId}`, {
      method: 'DELETE',
    });
    return publishCart(payload.cart);
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
