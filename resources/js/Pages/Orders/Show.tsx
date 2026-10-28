import { AccountLayout } from '@/Layouts/AccountLayout';
import { OrderSummary } from '@/components/checkout/OrderSummary';
import { PaymentInstructions } from '@/components/checkout/PaymentInstructions';
import { PaymentProofForm } from '@/components/checkout/PaymentProofForm';
import { PaymentSummary } from '@/components/checkout/PaymentSummary';

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

type TimelineItem = { key: string; label: string; reached: boolean; at?: string | null };

type Order = {
  id: string;
  order_number: string;
  placed_at?: string | null;
  status_label?: string;
  total_amount: string;
  subtotal_amount?: string;
  shipping_amount?: string;
  discount_amount?: string;
  tax_amount?: string;
  currency?: string;
  shipping_method?: string | null;
  shipping_address?: { full_name?: string; address_line_1?: string; city?: string; postal_code?: string; country_code?: string };
  items?: Array<{ product_name_snapshot: string; sku_snapshot: string; variant_name?: string | null; quantity: number; unit_price?: string; line_total: string; image_url?: string | null }>;
  payments?: Payment[];
  timeline?: TimelineItem[];
  tracking?: { available: boolean; carrier?: string | null; number?: string | null; url?: string | null; message?: string | null };
};

type Instructions = {
  bank_transfer?: Record<string, string>;
  crypto?: { networks?: Array<{ code: string; label: string; address: string }>; reference_hint?: string };
};

export default function OrdersShow({ order, paymentInstructions }: { order: Order; paymentInstructions?: Instructions }) {
  const payment = order.payments?.[0];
  const currency = order.currency ?? 'GBP';
  const address = order.shipping_address;

  return (
    <AccountLayout title={order.order_number} seo={{ title: order.order_number, description: 'Order details.', robots: 'noindex,nofollow' }}>
      <p className="text-slate-500">
        {order.placed_at ? new Date(order.placed_at).toLocaleDateString('en-GB') : ''}
        {order.status_label ? ` · ${order.status_label}` : ''}
      </p>

      {order.timeline && order.timeline.length > 0 ? (
        <ol className="mt-8 space-y-3 border-l border-slate-200 pl-4">
          {order.timeline.map((step) => (
            <li key={step.key}>
              <p className="text-sm font-semibold text-slate-900">{step.label}</p>
              {step.at ? <p className="text-xs text-slate-500">{new Date(step.at).toLocaleString('en-GB')}</p> : null}
            </li>
          ))}
        </ol>
      ) : null}

      <div className="mt-10 grid gap-6 lg:grid-cols-2">
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
        <div className="space-y-6">
          <PaymentSummary
            method={payment?.method_label}
            amountDue={payment?.amount_due ?? order.total_amount}
            currency={payment?.currency ?? currency}
            status={payment?.status}
            statusLabel={payment?.status_label}
            reference={payment?.reference ?? order.order_number}
          />
          <section className="rounded-xl border border-slate-200 p-4">
            <h2 className="text-sm font-semibold text-slate-900">Delivery</h2>
            <p className="mt-2 text-sm text-slate-500">{order.shipping_method || 'Shipping method confirmed at checkout'}</p>
            {address ? (
              <p className="mt-3 text-sm leading-relaxed text-slate-600">
                {address.full_name}
                <br />
                {address.address_line_1}
                <br />
                {address.city} {address.postal_code}
                <br />
                {address.country_code}
              </p>
            ) : null}
          </section>
          <section className="rounded-xl border border-slate-200 p-4">
            <h2 className="text-sm font-semibold text-slate-900">Tracking</h2>
            {order.tracking?.available ? (
              <p className="mt-2 text-sm text-slate-600">
                {order.tracking.carrier ? `${order.tracking.carrier}: ` : ''}
                {order.tracking.number}
                {order.tracking.url ? (
                  <a href={order.tracking.url} className="mt-2 block text-blue-600" rel="noreferrer">
                    Track shipment
                  </a>
                ) : null}
              </p>
            ) : (
              <p className="mt-2 text-sm text-slate-500">{order.tracking?.message}</p>
            )}
          </section>
        </div>
      </div>

      <div className="mt-8 max-w-lg">
        <PaymentInstructions
          method={payment?.method}
          bank={paymentInstructions?.bank_transfer}
          crypto={paymentInstructions?.crypto}
          orderNumber={order.order_number}
        />
        <PaymentProofForm orderId={order.id} payment={payment} paymentInstructions={paymentInstructions} />
      </div>
    </AccountLayout>
  );
}
