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
    <StorefrontLayout seo={{ title: 'Cart', description: 'Review your Supreme Steroids basket.', robots: 'noindex,nofollow' }}>
      <h1 className="text-3xl font-semibold text-slate-900">Cart</h1>
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
                  router.delete(`/cart/items/${item.id}`, { onFinish: () => setPending(null) });
                }}
              >
                Remove
              </Button>
            </div>
          </div>
        ))}
      </div>
      <p className="mt-8 text-lg text-slate-900">Subtotal {formatMoney(cart.totals?.subtotal_amount, currency)}</p>
      <Link href="/checkout" className="mt-4 inline-flex rounded-full bg-blue-600 px-5 py-2 text-sm font-semibold text-white">
        Continue to checkout
      </Link>
    </StorefrontLayout>
  );
}
