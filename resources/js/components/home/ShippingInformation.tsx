import type { HomepagePayload } from '@/types';
import { formatMoney } from '@/lib/money';

export function ShippingInformation({ shipping }: { shipping: HomepagePayload['shipping'] }) {
  if (!shipping.methods?.length) {
    return null;
  }

  return (
    <section aria-labelledby="shipping-heading" className="mt-16 rounded-3xl border border-slate-200 bg-white/60 p-6 sm:p-8">
      <h2 id="shipping-heading" className="text-2xl font-semibold text-slate-900">
        Delivery
      </h2>
      <p className="mt-2 text-sm text-slate-500">
        Rates come from the store shipping configuration. Free UK delivery applies on eligible orders over {formatMoney(shipping.free_shipping_threshold, shipping.currency)}.
      </p>
      <ul className="mt-6 grid gap-3 sm:grid-cols-2">
        {shipping.methods.map((method) => (
          <li key={method.code} className="hover-lift rounded-xl border border-slate-200 bg-white/70 px-4 py-3">
            <p className="font-semibold text-slate-900">{method.name}</p>
            <p className="mt-1 text-sm text-slate-500">
              {formatMoney(method.rate_amount ?? method.rate ?? '0.00', shipping.currency)}
              {method.estimated_days ? ` · ${method.estimated_days}` : ''}
              {method.is_eligible_for_free_threshold ? ' · free when eligible' : ''}
            </p>
          </li>
        ))}
      </ul>
    </section>
  );
}
