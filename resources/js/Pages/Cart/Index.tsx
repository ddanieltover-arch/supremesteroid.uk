import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { formatMoney } from '@/lib/money';
import type { CartPayload } from '@/types';

export default function CartIndex({ cart }: { cart: CartPayload }) {
  const [pending, setPending] = useState<string | null>(null);
  const currency = cart.totals?.currency ?? 'GBP';
  const eligibility = cart.shipping_eligibility;

  return (
    <StorefrontLayout seo={{ title: 'Cart', description: 'Review your Supreme Steroids basket.' }}>
      <h1 className="text-3xl font-semibold text-white">Cart</h1>
      {eligibility ? (
        <p className="mt-3 text-sm text-slate-400">
          {eligibility.free_shipping_eligible
            ? 'Free UK Standard Delivery is available on this basket.'
            : `${formatMoney(eligibility.remaining_to_threshold, eligibility.currency)} remaining to unlock free UK delivery.`}
        </p>
      ) : null}
      <div className="mt-8 space-y-4">
        {(cart.items ?? []).map((item) => (
          <div key={item.id} className="flex flex-col justify-between gap-4 rounded-xl border border-slate-800 p-4 sm:flex-row sm:items-center">
            <div>
              <Link href={`/product/${item.product_slug}`} className="text-white hover:text-amber-300">
                {item.product_name}
              </Link>
              <p className="text-sm text-slate-400">
                {item.quantity} × {formatMoney(item.unit_price, currency)}
              </p>
              {!item.is_eligible ? <p className="text-xs text-rose-300">Unavailable</p> : null}
            </div>
            <div className="flex items-center gap-3">
              <span className="text-amber-300">{formatMoney(item.line_total, currency)}</span>
              <Button
                size="sm"
                variant="ghost"
                isLoading={pending === item.id}
                onClick={() => {
                  setPending(item.id);
                  router.delete(`/cart/items/${item.id}`, { onFinish: () => setPending(null) });
                }}
              >
                Remove
              </Button>
            </div>
          </div>
        ))}
      </div>
      <p className="mt-8 text-lg text-white">Subtotal {formatMoney(cart.totals?.subtotal_amount, currency)}</p>
      <Link href="/checkout" className="mt-4 inline-flex rounded-full bg-amber-400 px-5 py-2 text-sm font-semibold text-slate-950">
        Continue to checkout
      </Link>
    </StorefrontLayout>
  );
}
