import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { Link } from '@inertiajs/react';
import { CheckoutProgress, type CheckoutStep } from '@/components/checkout/CheckoutProgress';
import { OrderSummary } from '@/components/checkout/OrderSummary';
import { PaymentInstructions } from '@/components/checkout/PaymentInstructions';
import { PaymentProofForm } from '@/components/checkout/PaymentProofForm';
import { PaymentSummary } from '@/components/checkout/PaymentSummary';
import { Button } from '@/components/ui/Button';

type Payment = {
  id: string;
  method?: string;
  method_label?: string;
  status?: string;
  status_label?: string;
  amount_due: string;
  currency?: string;
  reference?: string | null;
  proof?: { filename?: string | null; download_url?: string | null } | null;
};

type Order = {
  id: string;
  order_number: string;
  placed_at?: string | null;
  total_amount: string;
  subtotal_amount?: string;
  shipping_amount?: string;
  discount_amount?: string;
  tax_amount?: string;
  currency?: string;
  shipping_method?: string | null;
  shipping_address?: { full_name?: string; address_line_1?: string; city?: string; postal_code?: string; country_code?: string };
  items?: Array<{ product_name_snapshot: string; variant_name?: string | null; quantity: number; unit_price?: string; line_total: string; image_url?: string | null }>;
  payments?: Payment[];
};

export default function CheckoutConfirmation({
  order,
  paymentInstructions,
  checkout_step = 'confirmation',
  checkout_steps = [],
}: {
  order: Order;
  paymentInstructions?: {
    bank_transfer?: Record<string, string>;
    crypto?: { networks?: Array<{ code: string; label: string; address: string }>; reference_hint?: string };
  };
  checkout_step?: string;
  checkout_steps?: CheckoutStep[];
}) {
  const payment = order.payments?.[0];
  const paid = payment?.status === 'PAID';
  const currency = order.currency ?? payment?.currency ?? 'GBP';
  const paymentStatusLabel = paid
    ? (payment?.status_label ?? 'Payment approved')
    : 'Payment pending verification';

  return (
    <StorefrontLayout chrome="checkout" seo={{ title: 'Order placed', description: 'Order confirmation.', robots: 'noindex,nofollow' }}>
      <div className="print:hidden">
        <CheckoutProgress current={checkout_step} steps={checkout_steps} />
      </div>
      <div
        className="mx-auto max-w-3xl"
        data-qa-order-id={order.id}
        data-qa-order-number={order.order_number}
        data-qa-payment-id={payment?.id}
        data-qa-payment-status={payment?.status}
      >
        <p className="text-emerald-300 print:text-black">✓ Order placed</p>
        <h1 className="mt-2 text-3xl font-semibold text-white print:text-black">Thank you</h1>
        <p className="mt-3 text-slate-400 print:text-neutral-700">
          Order {order.order_number}
          {order.placed_at ? ` · ${new Date(order.placed_at).toLocaleDateString('en-GB')}` : ''}
        </p>
        <p id="payment-status" className="mt-2 text-sm text-amber-300 print:text-black" role="status">
          {paymentStatusLabel}
        </p>

        <div className="mt-8 grid gap-4 md:grid-cols-2">
          <section className="rounded-2xl border border-slate-800 p-5 text-sm text-slate-300">
            <h2 className="font-semibold text-white">Delivery</h2>
            <p className="mt-2">{order.shipping_method}</p>
            {order.shipping_address ? (
              <p className="mt-3 leading-relaxed">
                {order.shipping_address.full_name}
                <br />
                {order.shipping_address.address_line_1}
                <br />
                {order.shipping_address.city} {order.shipping_address.postal_code}
                <br />
                {order.shipping_address.country_code}
              </p>
            ) : null}
          </section>
          <PaymentSummary
            method={payment?.method_label}
            amountDue={payment?.amount_due ?? order.total_amount}
            currency={currency}
            status={payment?.status}
            statusLabel={paymentStatusLabel}
            reference={payment?.reference ?? order.order_number}
          />
        </div>

        <div className="mt-6">
          <OrderSummary
            items={order.items}
            totals={{
              subtotal: order.subtotal_amount,
              discount: order.discount_amount,
              shipping: order.shipping_amount,
              tax: order.tax_amount,
              grand_total: order.total_amount,
              currency,
            }}
          />
        </div>

        <div className="mt-6">
          <PaymentInstructions
            method={payment?.method}
            bank={paymentInstructions?.bank_transfer}
            crypto={paymentInstructions?.crypto}
            orderNumber={order.order_number}
          />
        </div>

        <PaymentProofForm orderId={order.id} payment={payment} paymentInstructions={paymentInstructions} />

        <div className="mt-8 flex flex-wrap gap-3 print:hidden">
          <Link href={`/account/orders/${order.id}`}>
            <Button type="button" variant="gold">
              View order
            </Button>
          </Link>
          <Link href="/shop">
            <Button type="button" variant="secondary">
              Continue shopping
            </Button>
          </Link>
          <Button type="button" variant="ghost" onClick={() => window.print()}>
            Print summary
          </Button>
        </div>
      </div>
    </StorefrontLayout>
  );
}
