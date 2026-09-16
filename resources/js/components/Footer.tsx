import { Link, usePage } from '@inertiajs/react';
import { AlertOctagon, ShieldCheck } from 'lucide-react';
import { formatMoney } from '@/lib/money';
import type { ShippingMethod } from '@/types';

export function Footer() {
  const page = usePage<{
    store?: { name?: string; support_email?: string };
    shippingOverview?: {
      currency: string;
      free_shipping_threshold: string;
      methods: ShippingMethod[];
    };
  }>();
  const methods = page.props.shippingOverview?.methods ?? [];
  const currency = page.props.shippingOverview?.currency ?? 'GBP';
  const storeName = page.props.store?.name ?? 'Supreme Steroids';

  return (
    <footer className="mt-20 border-t border-slate-900 bg-slate-950 text-xs text-slate-400">
      <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div className="mb-10 rounded-xl border border-slate-800/80 bg-slate-900/50 p-4">
          <div className="flex items-start gap-3">
            <AlertOctagon className="mt-0.5 h-5 w-5 shrink-0 text-amber-500" aria-hidden="true" />
            <div>
              <h2 className="mb-1 text-sm font-semibold uppercase tracking-wider text-slate-200">
                Regulatory & product compliance
              </h2>
              <p className="leading-relaxed text-slate-400">
                {storeName} offers lawful OTC nutritional products, wellness cofactors, and analytical research reagents.
                Controlled substances and unlicensed medicines are not sold.
              </p>
            </div>
          </div>
        </div>

        <div className="mb-10 grid grid-cols-1 gap-8 md:grid-cols-3">
          <div>
            <h3 className="mb-3 text-sm font-bold text-slate-100">{storeName}</h3>
            <p className="mb-4 leading-relaxed">Precision-formulated sports nutrition and verified laboratory reagents.</p>
            <p className="inline-flex items-center gap-2 font-mono text-[11px] text-emerald-400">
              <ShieldCheck className="h-4 w-4" aria-hidden="true" /> Lab verified batches
            </p>
          </div>
          <div>
            <h3 className="mb-3 text-sm font-bold text-slate-100">Shipping</h3>
            <ul className="space-y-2">
              {methods.map((method) => (
                <li key={method.code}>
                  {method.name} — {formatMoney(method.rate_amount ?? '0.00', currency)}
                  {method.is_eligible_for_free_threshold ? ' (free when eligible)' : ''}
                </li>
              ))}
            </ul>
          </div>
          <div>
            <h3 className="mb-3 text-sm font-bold text-slate-100">Help</h3>
            <ul className="space-y-2">
              <li>
                <Link href="/shop" className="hover:text-slate-200">
                  Shop
                </Link>
              </li>
              <li>
                <Link href="/account" className="hover:text-slate-200">
                  Account
                </Link>
              </li>
              <li>
                <a href={`mailto:${page.props.store?.support_email ?? 'info@supremesteroid.uk'}`} className="hover:text-slate-200">
                  Support
                </a>
              </li>
            </ul>
          </div>
        </div>

        <div className="flex flex-col items-center justify-between gap-4 border-t border-slate-900 pt-6 text-slate-500 sm:flex-row">
          <p>© {new Date().getFullYear()} {storeName}. All rights reserved.</p>
        </div>
      </div>
    </footer>
  );
}
