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
    <section className="rounded-2xl border border-slate-200 bg-white/70 p-5">
      <h2 className="text-lg font-semibold text-slate-900">Payment</h2>
      <dl className="mt-4 space-y-2 text-sm">
        {method ? (
          <div className="flex justify-between gap-3 text-slate-500">
            <dt>Method</dt>
            <dd className="text-slate-800">{method}</dd>
          </div>
        ) : null}
        {amountDue ? (
          <div className="flex justify-between gap-3 text-slate-500">
            <dt>Amount due</dt>
            <dd className="text-slate-800">{formatMoney(amountDue, currency)}</dd>
          </div>
        ) : null}
        <div className="flex justify-between gap-3 text-slate-500">
          <dt>Status</dt>
          <dd className={paid ? 'text-emerald-600' : 'text-blue-600'}>
            {statusLabel ?? (paid ? 'Payment approved' : 'Payment pending verification')}
          </dd>
        </div>
        {reference ? (
          <div className="flex justify-between gap-3 text-slate-500">
            <dt>Reference</dt>
            <dd className="font-mono text-slate-800">{reference}</dd>
          </div>
        ) : null}
      </dl>
    </section>
  );
}
