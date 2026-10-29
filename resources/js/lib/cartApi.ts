import { jsonFetch } from '@/lib/http';
import type { CartPayload } from '@/types';

type CartMutationResponse = {
  message?: string;
  cart: CartPayload;
};

type CartListener = (cart: CartPayload) => void;

let cartOverride: CartPayload | null = null;
const listeners = new Set<CartListener>();

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

export async function addCartItem(input: {
  product_id: string;
  product_variant_id?: string | null;
  quantity?: number;
}): Promise<CartPayload> {
  const payload = await jsonFetch<CartMutationResponse>('/cart/items', {
    method: 'POST',
    body: JSON.stringify({
      product_id: input.product_id,
      product_variant_id: input.product_variant_id ?? null,
      quantity: input.quantity ?? 1,
    }),
  });
  return publishCart(payload.cart);
}

export async function updateCartItemQuantity(itemId: string, quantity: number): Promise<CartPayload> {
  const payload = await jsonFetch<CartMutationResponse>(`/cart/items/${itemId}`, {
    method: 'PATCH',
    body: JSON.stringify({ quantity }),
  });
  return publishCart(payload.cart);
}

export async function removeCartItem(itemId: string): Promise<CartPayload> {
  const payload = await jsonFetch<CartMutationResponse>(`/cart/items/${itemId}`, {
    method: 'DELETE',
  });
  return publishCart(payload.cart);
}

export async function clearCart(): Promise<CartPayload> {
  const payload = await jsonFetch<CartMutationResponse>('/cart', {
    method: 'DELETE',
  });
  return publishCart(payload.cart);
}
