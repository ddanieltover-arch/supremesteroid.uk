import { formatMoney } from '@/lib/money';

export function PaymentSummary({
  method,
  amountDue,
  currency = 'GBP',
  status,
  statusLabel,
  reference,
}: {
  method?: string | null;
  amountDue?: string | null;
  currency?: string;
  status?: string | null;
  statusLabel?: string | null;
  reference?: string | null;
}) {
  const paid = status === 'PAID';

  return (
    <section className="rounded-2xl border border-slate-800 bg-slate-950/70 p-5">
      <h2 className="text-lg font-semibold text-white">Payment</h2>
      <dl className="mt-4 space-y-2 text-sm">
        {method ? (
          <div className="flex justify-between gap-3 text-slate-400">
            <dt>Method</dt>
            <dd className="text-slate-200">{method}</dd>
          </div>
        ) : null}
        {amountDue ? (
          <div className="flex justify-between gap-3 text-slate-400">
            <dt>Amount due</dt>
            <dd className="text-slate-200">{formatMoney(amountDue, currency)}</dd>
          </div>
        ) : null}
        <div className="flex justify-between gap-3 text-slate-400">
          <dt>Status</dt>
          <dd className={paid ? 'text-emerald-300' : 'text-amber-300'}>
            {statusLabel ?? (paid ? 'Payment approved' : 'Payment pending verification')}
          </dd>
        </div>
        {reference ? (
          <div className="flex justify-between gap-3 text-slate-400">
            <dt>Reference</dt>
            <dd className="font-mono text-slate-200">{reference}</dd>
          </div>
        ) : null}
      </dl>
    </section>
  );
}
