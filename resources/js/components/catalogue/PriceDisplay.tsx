import { formatMoney } from '@/lib/money';
import type { MoneyPricing } from '@/types';

export function PriceDisplay({ pricing, size = 'md' }: { pricing?: MoneyPricing | null; size?: 'sm' | 'md' | 'lg' }) {
  if (!pricing) {
    return <p className="text-slate-500">Unavailable</p>;
  }

  const priceClass = size === 'lg' ? 'text-3xl' : size === 'sm' ? 'text-base' : 'text-lg';

  return (
    <div>
      <p className={`${priceClass} font-bold text-slate-100`}>{formatMoney(pricing.effective_price, pricing.currency)}</p>
      {pricing.is_on_sale ? <p className="text-sm text-slate-500 line-through">{formatMoney(pricing.base_price, pricing.currency)}</p> : null}
      {pricing.discount_percent ? <p className="text-xs font-semibold text-amber-400">-{pricing.discount_percent}%</p> : null}
    </div>
  );
}
