import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { useCart } from '@/hooks/useCart';
import { checkoutWhenReady, isCartSyncing, removeCartItem, subscribeCartSync } from '@/lib/cartApi';
import { formatMoney } from '@/lib/money';
import type { CartPayload } from '@/types';

export default function CartIndex({ cart: initialCart }: { cart: CartPayload }) {
  const page = usePage<{ cart?: { totals?: { items_count?: number } } }>();
  const liveCart = useCart();
  // Prefer local/JSON cart only when it has line items (shared props are a count shell).
  const cart = (liveCart.items?.length ?? 0) > 0 ? liveCart : initialCart;
  const [pending, setPending] = useState<string | null>(null);
  const [syncing, setSyncing] = useState(() => isCartSyncing());
  const [checkingOut, setCheckingOut] = useState(false);
  const [checkoutError, setCheckoutError] = useState<string | null>(null);
  const currency = cart.totals?.currency ?? 'GBP';
  const eligibility = cart.shipping_eligibility;
  const serverItemsCount = page.props.cart?.totals?.items_count ?? initialCart.totals?.items_count ?? 0;
  const canCheckout = (cart.totals?.items_count ?? 0) > 0 || serverItemsCount > 0;

  useEffect(() => subscribeCartSync(setSyncing), []);

  return (
    <StorefrontLayout seo={{ title: 'Cart', description: 'Review your Supreme Steroids basket.', robots: 'noindex,nofollow' }}>
      <h1 className="text-3xl font-semibold text-slate-900">Cart</h1>
      {checkoutError ? <p className="mt-3 text-sm text-rose-600">{checkoutError}</p> : null}
      {eligibility ? (
        <p className="mt-3 text-sm text-slate-500">
          {eligibility.free_shipping_eligible
            ? 'Free UK Standard Delivery is available on this basket.'
            : `${formatMoney(eligibility.remaining_to_threshold, eligibility.currency)} remaining to unlock free UK delivery.`}
        </p>
      ) : null}
      <div className="mt-8 space-y-4">
        {(cart.items ?? []).map((item) => (
          <div key={item.id} className="flex flex-col justify-between gap-4 rounded-xl border border-slate-200 p-4 sm:flex-row sm:items-center">
            <div>
              <Link href={`/product/${item.product_slug}`} className="text-slate-900 hover:text-blue-600">
                {item.product_name}
              </Link>
              <p className="text-sm text-slate-500">
                {item.quantity} × {formatMoney(item.unit_price, currency)}
              </p>
              {!item.is_eligible ? <p className="text-xs text-rose-600">Unavailable</p> : null}
            </div>
            <div className="flex items-center gap-3">
              <span className="text-blue-600">{formatMoney(item.line_total, currency)}</span>
              <Button
                size="sm"
                variant="ghost"
                isLoading={pending === item.id}
                onClick={() => {
                  setPending(item.id);
                  void removeCartItem(item.id).finally(() => setPending(null));
                }}
              >
                Remove
              </Button>
            </div>
          </div>
        ))}
      </div>
      <p className="mt-8 text-lg text-slate-900">Subtotal {formatMoney(cart.totals?.subtotal_amount, currency)}</p>
      <Button
        className="mt-4"
        disabled={!canCheckout || syncing || checkingOut || pending !== null}
        isLoading={syncing || checkingOut}
        onClick={() => {
          if (checkingOut || syncing) {
            return;
          }
          setCheckoutError(null);
          setCheckingOut(true);
          void checkoutWhenReady()
            .then(() => router.visit('/checkout'))
            .catch((error: unknown) => {
              setCheckoutError(
                error instanceof Error ? error.message : 'Basket could not be confirmed — try again.',
              );
            })
            .finally(() => setCheckingOut(false));
        }}
      >
        {syncing || checkingOut ? 'Preparing checkout…' : 'Continue to checkout'}
      </Button>
    </StorefrontLayout>
  );
}
