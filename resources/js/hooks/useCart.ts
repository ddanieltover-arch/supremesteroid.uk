import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { clearCartOverride, getCartOverride, subscribeCart } from '@/lib/cartApi';
import type { CartPayload } from '@/types';

const emptyCart: CartPayload = {
  id: null,
  items: [],
  totals: {
    subtotal_amount: '0.00',
    discount_amount: '0.00',
    shipping_amount: '0.00',
    tax_amount: '0.00',
    grand_total_amount: '0.00',
    items_count: 0,
    currency: 'GBP',
  },
  shipping_eligibility: {
    free_shipping_threshold: '0.00',
    current_eligible_subtotal: '0.00',
    remaining_to_threshold: '0.00',
    free_shipping_eligible: false,
    progress_percent: 0,
    currency: 'GBP',
  },
  is_checkout_ready: false,
};

function cartSignature(cart: CartPayload): string {
  return `${cart.id ?? ''}:${cart.totals?.items_count ?? 0}:${cart.totals?.grand_total_amount ?? '0.00'}`;
}

/**
 * Prefers a JSON-mutation cart snapshot when present, otherwise Inertia shared props.
 * Keeps the snapshot across Instant Visits until the server-shared cart changes.
 */
export function useCart(): CartPayload {
  const page = usePage<{ cart?: CartPayload }>();
  const shared = page.props.cart ?? emptyCart;
  const [local, setLocal] = useState<CartPayload | null>(() => getCartOverride());
  const [seenSignature, setSeenSignature] = useState(() => cartSignature(shared));

  useEffect(() => subscribeCart(setLocal), []);

  useEffect(() => {
    const next = cartSignature(shared);
    if (next === seenSignature) {
      return;
    }
    setSeenSignature(next);
    clearCartOverride();
    setLocal(null);
  }, [shared, seenSignature]);

  return local ?? shared;
}
