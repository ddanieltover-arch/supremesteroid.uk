import { useForm } from '@inertiajs/react';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';

type Payment = {
  id: string;
  method?: string;
  status?: string;
  amount_due: string;
  proof?: { filename?: string | null; download_url?: string | null } | null;
};

type Instructions = {
  crypto?: { networks?: Array<{ code: string; label: string; address: string }> };
};

export function PaymentProofForm({
  orderId,
  payment,
  paymentInstructions,
}: {
  orderId: string;
  payment?: Payment | null;
  paymentInstructions?: Instructions;
}) {
  const canSubmit = Boolean(payment && !['PAID', 'REJECTED', 'REFUNDED'].includes(payment.status ?? ''));
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
    proof_file: null as File | null,
  });

  if (!payment || !canSubmit) {
    return payment?.proof ? (
      <p className="mt-4 text-sm text-slate-500">
        Proof uploaded: {payment.proof.filename}
        {payment.proof.download_url ? (
          <a className="ml-2 text-blue-600" href={payment.proof.download_url}>
            View proof
          </a>
        ) : null}
      </p>
    ) : null;
  }

  if (payment.method === 'BANK_TRANSFER') {
    return (
      <form
        className="mt-6 grid gap-3"
        onSubmit={(event) => {
          event.preventDefault();
          if (bankForm.processing) {
            return;
          }
          bankForm.post(`/orders/${orderId}/payments/${payment.id}/proof/bank`, { forceFormData: true });
        }}
      >
        <h3 className="text-lg font-semibold text-slate-900">Submit bank transfer proof</h3>
        <Alert type="info">Submitting proof does not mark the order as paid. Staff review the payment first.</Alert>
        <Input label="Payment reference" value={bankForm.data.bank_payment_reference} onChange={(event) => bankForm.setData('bank_payment_reference', event.target.value)} />
        <Input label="Sender account name" value={bankForm.data.sender_account_name} onChange={(event) => bankForm.setData('sender_account_name', event.target.value)} />
        <Input label="Amount sent" value={bankForm.data.submitted_amount} onChange={(event) => bankForm.setData('submitted_amount', event.target.value)} />
        <Input label="Transfer date" type="date" value={bankForm.data.transfer_date} onChange={(event) => bankForm.setData('transfer_date', event.target.value)} />
        <ProofFileInput
          filename={bankForm.data.proof_file?.name}
          processing={bankForm.processing}
          recentlySuccessful={bankForm.recentlySuccessful}
          onChange={(file) => bankForm.setData('proof_file', file)}
          onRemove={() => bankForm.setData('proof_file', null)}
        />
        <Button type="submit" variant="gold" isLoading={bankForm.processing} disabled={bankForm.processing}>
          Submit proof
        </Button>
      </form>
    );
  }

  return (
    <form
      className="mt-6 grid gap-3"
      onSubmit={(event) => {
        event.preventDefault();
        if (cryptoForm.processing) {
          return;
        }
        cryptoForm.post(`/orders/${orderId}/payments/${payment.id}/proof/crypto`, { forceFormData: true });
      }}
    >
      <h3 className="text-lg font-semibold text-slate-900">Submit crypto proof</h3>
      <Alert type="info">A transaction hash is a submission for review. It does not confirm payment by itself.</Alert>
      <Input label="Network" value={cryptoForm.data.crypto_network} onChange={(event) => cryptoForm.setData('crypto_network', event.target.value)} />
      <Input label="Transaction hash" value={cryptoForm.data.crypto_transaction_hash} onChange={(event) => cryptoForm.setData('crypto_transaction_hash', event.target.value)} />
      <Input label="Recipient wallet" value={cryptoForm.data.crypto_recipient_wallet} onChange={(event) => cryptoForm.setData('crypto_recipient_wallet', event.target.value)} />
      <Input label="Crypto amount" value={cryptoForm.data.crypto_submitted_amount} onChange={(event) => cryptoForm.setData('crypto_submitted_amount', event.target.value)} />
      <Input label="Fiat amount" value={cryptoForm.data.submitted_fiat_amount} onChange={(event) => cryptoForm.setData('submitted_fiat_amount', event.target.value)} />
      <ProofFileInput
        filename={cryptoForm.data.proof_file?.name}
        processing={cryptoForm.processing}
        recentlySuccessful={cryptoForm.recentlySuccessful}
        onChange={(file) => cryptoForm.setData('proof_file', file)}
        onRemove={() => cryptoForm.setData('proof_file', null)}
      />
      <Button type="submit" variant="gold" isLoading={cryptoForm.processing} disabled={cryptoForm.processing}>
        Submit proof
      </Button>
    </form>
  );
}

function ProofFileInput({
  filename,
  processing,
  recentlySuccessful,
  onChange,
  onRemove,
}: {
  filename?: string;
  processing: boolean;
  recentlySuccessful: boolean;
  onChange: (file: File | null) => void;
  onRemove: () => void;
}) {
  return (
    <div>
      <Input
        label="Proof file"
        type="file"
        accept="image/jpeg,image/png,image/webp,application/pdf"
        onChange={(event) => onChange(event.target.files?.[0] ?? null)}
      />
      {filename ? <p className="mt-2 text-xs text-slate-500">Selected: {filename}</p> : null}
      {processing ? <p className="mt-1 text-xs text-blue-600">Uploading…</p> : null}
      {recentlySuccessful ? <p className="mt-1 text-xs text-emerald-600">Submission received.</p> : null}
      {filename ? (
        <button type="button" className="mt-2 text-xs text-slate-500 underline" onClick={onRemove}>
          Remove file
        </button>
      ) : null}
    </div>
  );
}
