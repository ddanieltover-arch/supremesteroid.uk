import { CopyButton } from '@/components/checkout/CopyButton';

type Bank = Record<string, string | null | undefined>;
type Network = { code: string; label: string; address: string };

export function PaymentInstructions({
  method,
  bank,
  crypto,
  orderNumber,
}: {
  method?: string | null;
  bank?: Bank;
  crypto?: { networks?: Network[]; reference_hint?: string | null };
  orderNumber?: string | null;
}) {
  if (method === 'BANK_TRANSFER') {
    const rows = [
      ['Account Name', bank?.account_name],
      ['Bank', bank?.bank_name],
      ['Sort Code', bank?.sort_code],
      ['Account Number', bank?.account_number],
      ['IBAN', bank?.iban],
      ['BIC', bank?.bic],
    ].filter((row): row is [string, string] => Boolean(row[1]));

    return (
      <section className="rounded-2xl border border-slate-200 p-5">
        <h2 className="text-lg font-semibold text-slate-900">Bank transfer</h2>
        <p className="mt-2 text-sm text-slate-500">
          {bank?.reference_hint || 'Use your order number as the payment reference unless the store provides another reference.'}
        </p>
        {orderNumber ? (
          <div className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <span className="text-slate-500">Reference</span>
            <span className="font-mono text-slate-900">{orderNumber}</span>
            <CopyButton value={orderNumber} label="Copy reference" />
          </div>
        ) : null}
        <ul className="mt-4 space-y-3">
          {rows.map(([label, value]) => (
            <li key={label} className="flex flex-wrap items-center justify-between gap-3 text-sm">
              <span className="text-slate-500">{label}</span>
              <span className="font-mono text-slate-900">{value}</span>
              <CopyButton value={value} label={`Copy ${label}`} />
            </li>
          ))}
        </ul>
        {rows.length === 0 ? <p className="mt-4 text-sm text-slate-500">Bank details will appear here once the store has configured them.</p> : null}
      </section>
    );
  }

  if (method === 'CRYPTOCURRENCY') {
    const networks = crypto?.networks ?? [];
    return (
      <section className="rounded-2xl border border-slate-200 p-5">
        <h2 className="text-lg font-semibold text-slate-900">Cryptocurrency</h2>
        <p className="mt-2 text-sm text-slate-500">
          {crypto?.reference_hint || 'Submit the transaction hash after sending the exact order total. Payment is not confirmed until staff approve it.'}
        </p>
        <ul className="mt-4 space-y-4">
          {networks.map((network) => (
            <li key={network.code} className="rounded-lg border border-slate-200 p-3 text-sm">
              <p className="font-semibold text-slate-900">{network.label}</p>
              <p className="mt-1 break-all font-mono text-xs text-slate-600">{network.address}</p>
              <div className="mt-3">
                <CopyButton value={network.address} label="Copy address" />
              </div>
            </li>
          ))}
        </ul>
        {networks.length === 0 ? <p className="mt-4 text-sm text-slate-500">Wallet details will appear here once the store has configured them.</p> : null}
      </section>
    );
  }

  return null;
}
