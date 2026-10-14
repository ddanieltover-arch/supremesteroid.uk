import { formatMoney } from '@/lib/money';

export type OrderSummaryItem = {
  product_name?: string;
  product_name_snapshot?: string;
  variant_name?: string | null;
  quantity: number;
  unit_price?: string;
  line_total: string;
  image_url?: string | null;
};

export type OrderSummaryTotals = {
  subtotal?: string;
  discount?: string;
  shipping?: string;
  tax?: string;
  grand_total: string;
  currency?: string;
};

export function OrderSummary({
  items = [],
  totals,
  title = 'Order summary',
}: {
  items?: OrderSummaryItem[];
  totals: OrderSummaryTotals;
  title?: string;
}) {
  const currency = totals.currency ?? 'GBP';

  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-950/70 p-5">
      <h2 className="text-lg font-semibold text-white">{title}</h2>
      <ul className="mt-4 space-y-3">
        {items.map((item, index) => {
          const name = item.product_name_snapshot ?? item.product_name ?? 'Item';
          return (
            <li key={`${name}-${index}`} className="flex gap-3 text-sm">
              {item.image_url ? (
                <img src={item.image_url} alt="" className="h-14 w-14 shrink-0 rounded-lg object-cover" />
              ) : (
                <div className="h-14 w-14 shrink-0 rounded-lg bg-slate-800" aria-hidden="true" />
              )}
              <div className="min-w-0 flex-1">
                <p className="font-medium text-white">{name}</p>
                {item.variant_name ? <p className="text-slate-400">{item.variant_name}</p> : null}
                <p className="text-slate-500">
                  Qty {item.quantity}
                  {item.unit_price ? ` · ${formatMoney(item.unit_price, currency)} each` : ''}
                </p>
              </div>
              <p className="shrink-0 font-medium text-slate-200">{formatMoney(item.line_total, currency)}</p>
            </li>
          );
        })}
      </ul>
      <dl className="mt-6 space-y-2 text-sm">
        {totals.subtotal ? (
          <div className="flex justify-between text-slate-400">
            <dt>Subtotal</dt>
            <dd>{formatMoney(totals.subtotal, currency)}</dd>
          </div>
        ) : null}
        {totals.discount && totals.discount !== '0.00' ? (
          <div className="flex justify-between text-slate-400">
            <dt>Discount</dt>
            <dd>{formatMoney(totals.discount, currency)}</dd>
          </div>
        ) : null}
        {totals.shipping ? (
          <div className="flex justify-between text-slate-400">
            <dt>Shipping</dt>
            <dd>{formatMoney(totals.shipping, currency)}</dd>
          </div>
        ) : null}
        {totals.tax && totals.tax !== '0.00' ? (
          <div className="flex justify-between text-slate-400">
            <dt>Tax</dt>
            <dd>{formatMoney(totals.tax, currency)}</dd>
          </div>
        ) : null}
        <div className="flex justify-between border-t border-slate-800 pt-3 text-lg font-bold text-white">
          <dt>Total</dt>
          <dd>{formatMoney(totals.grand_total, currency)}</dd>
        </div>
      </dl>
    </section>
  );
}
