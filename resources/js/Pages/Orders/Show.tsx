import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { useForm } from '@inertiajs/react';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { formatMoney } from '@/lib/money';

type Payment = {
  id: string;
  method?: string;
  method_label?: string;
  status?: string;
  status_label?: string;
  amount_due: string;
  currency?: string;
  reference?: string | null;
};

type Order = {
  id: string;
  order_number: string;
  status_label?: string;
  total_amount: string;
  currency?: string;
  shipping_method?: string | null;
  shipping_address?: { address_line_1?: string; city?: string; postal_code?: string; country_code?: string };
  items?: Array<{ product_name_snapshot: string; sku_snapshot: string; quantity: number; line_total: string }>;
  payments?: Payment[];
};

type Instructions = {
  bank_transfer?: Record<string, string>;
  crypto?: { networks?: Array<{ code: string; label: string; address: string }>; reference_hint?: string };
};

export default function OrdersShow({ order, paymentInstructions }: { order: Order; paymentInstructions?: Instructions }) {
  const payment = order.payments?.[0];
  const currency = order.currency ?? 'GBP';
  const bankForm = useForm({
    bank_payment_reference: '',
    sender_account_name: '',
    submitted_amount: payment?.amount_due ?? '',
    transfer_date: '',
    proof_file: null as File | null,
  });
  const cryptoForm = useForm({
    crypto_network: paymentInstructions?.crypto?.networks?.[0]?.code ?? '',
    crypto_transaction_hash: '',
    crypto_recipient_wallet: paymentInstructions?.crypto?.networks?.[0]?.address ?? '',
    crypto_submitted_amount: '',
    submitted_fiat_amount: payment?.amount_due ?? '',
  });

  const canSubmitProof = payment && !['PAID', 'REJECTED'].includes(payment.status ?? '');

  return (
    <StorefrontLayout seo={{ title: order.order_number, description: 'Order details.', robots: 'noindex,nofollow' }}>
      <h1 className="text-3xl font-semibold text-white">{order.order_number}</h1>
      <p className="mt-2 text-slate-400">{order.status_label}</p>
      <p className="mt-2 text-amber-300">{formatMoney(order.total_amount, currency)}</p>
      <p className="mt-2 text-sm text-slate-500">{order.shipping_method}</p>
      <ul className="mt-8 space-y-2">
        {(order.items ?? []).map((item) => (
          <li key={item.sku_snapshot} className="text-sm text-slate-300">
            {item.product_name_snapshot} × {item.quantity} — {formatMoney(item.line_total, currency)}
          </li>
        ))}
      </ul>

      {payment?.method === 'BANK_TRANSFER' && paymentInstructions?.bank_transfer ? (
        <section className="mt-8 max-w-lg rounded-xl border border-slate-800 p-4 text-sm text-slate-300">
          <h2 className="text-lg text-white">Bank transfer instructions</h2>
          {Object.entries(paymentInstructions.bank_transfer).map(([key, value]) =>
            value ? (
              <p key={key} className="mt-1">
                <span className="text-slate-500">{key.replaceAll('_', ' ')}: </span>
                {value}
              </p>
            ) : null,
          )}
        </section>
      ) : null}

      {payment?.method === 'CRYPTOCURRENCY' && paymentInstructions?.crypto?.networks?.length ? (
        <section className="mt-8 max-w-lg rounded-xl border border-slate-800 p-4 text-sm text-slate-300">
          <h2 className="text-lg text-white">Crypto instructions</h2>
          {paymentInstructions.crypto.networks.map((network) => (
            <p key={network.code} className="mt-2 font-mono text-xs">
              {network.label}: {network.address}
            </p>
          ))}
          {paymentInstructions.crypto.reference_hint ? <p className="mt-2">{paymentInstructions.crypto.reference_hint}</p> : null}
        </section>
      ) : null}

      {canSubmitProof && payment?.method === 'BANK_TRANSFER' ? (
        <form
          className="mt-8 grid max-w-lg gap-3"
          onSubmit={(event) => {
            event.preventDefault();
            bankForm.post(`/orders/${order.id}/payments/${payment.id}/proof/bank`, { forceFormData: true });
          }}
        >
          <h2 className="text-lg text-white">Submit bank transfer proof</h2>
          <Alert type="info">Submitting proof does not mark the order as paid. Staff review the payment first.</Alert>
          <Input label="Payment reference" value={bankForm.data.bank_payment_reference} onChange={(event) => bankForm.setData('bank_payment_reference', event.target.value)} required />
          <Input label="Sender account name" value={bankForm.data.sender_account_name} onChange={(event) => bankForm.setData('sender_account_name', event.target.value)} required />
          <Input label="Amount sent" value={bankForm.data.submitted_amount} onChange={(event) => bankForm.setData('submitted_amount', event.target.value)} required />
          <Input label="Transfer date" type="date" value={bankForm.data.transfer_date} onChange={(event) => bankForm.setData('transfer_date', event.target.value)} required />
          <Input
            label="Proof file"
            type="file"
            accept="image/jpeg,image/png,image/webp,application/pdf"
            onChange={(event) => bankForm.setData('proof_file', event.target.files?.[0] ?? null)}
          />
          <Button type="submit" variant="gold" isLoading={bankForm.processing}>
            Submit proof
          </Button>
        </form>
      ) : null}

      {canSubmitProof && payment?.method === 'CRYPTOCURRENCY' ? (
        <form
          className="mt-8 grid max-w-lg gap-3"
          onSubmit={(event) => {
            event.preventDefault();
            cryptoForm.post(`/orders/${order.id}/payments/${payment.id}/proof/crypto`);
          }}
        >
          <h2 className="text-lg text-white">Submit crypto proof</h2>
          <Input label="Network" value={cryptoForm.data.crypto_network} onChange={(event) => cryptoForm.setData('crypto_network', event.target.value)} required />
          <Input label="Transaction hash" value={cryptoForm.data.crypto_transaction_hash} onChange={(event) => cryptoForm.setData('crypto_transaction_hash', event.target.value)} required />
          <Input label="Recipient wallet" value={cryptoForm.data.crypto_recipient_wallet} onChange={(event) => cryptoForm.setData('crypto_recipient_wallet', event.target.value)} required />
          <Input label="Crypto amount" value={cryptoForm.data.crypto_submitted_amount} onChange={(event) => cryptoForm.setData('crypto_submitted_amount', event.target.value)} required />
          <Input label="Fiat amount" value={cryptoForm.data.submitted_fiat_amount} onChange={(event) => cryptoForm.setData('submitted_fiat_amount', event.target.value)} required />
          <Button type="submit" variant="gold" isLoading={cryptoForm.processing}>
            Submit proof
          </Button>
        </form>
      ) : null}
    </StorefrontLayout>
  );
}
