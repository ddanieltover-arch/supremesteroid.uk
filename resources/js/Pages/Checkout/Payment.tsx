import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { CheckoutAlerts } from '@/components/checkout/CheckoutAlerts';
import { CheckoutProgress, type CheckoutStep } from '@/components/checkout/CheckoutProgress';
import { OrderSummary } from '@/components/checkout/OrderSummary';
import { PaymentInstructions } from '@/components/checkout/PaymentInstructions';
import { PaymentSummary } from '@/components/checkout/PaymentSummary';
import { Button } from '@/components/ui/Button';
import type { CartPayload, CheckoutPreview, ShippingMethod } from '@/types';

type PaymentMethod = { code: string; name: string; description?: string };

export default function CheckoutPayment({
  paymentMethods = [],
  payment_method_notice = null,
  paymentInstructions,
  selectedShippingMethod,
  checkoutPreview,
  checkout,
  cart,
  checkout_token,
  idempotency_key,
  checkout_step = 'payment',
  checkout_steps = [],
  warnings = [],
  affected_items = [],
  requires_acknowledgement = false,
}: {
  paymentMethods?: PaymentMethod[];
  payment_method_notice?: string | null;
  paymentInstructions?: {
    bank_transfer?: Record<string, string>;
    crypto?: { networks?: Array<{ code: string; label: string; address: string }>; reference_hint?: string };
  };
  selectedShippingMethod?: ShippingMethod;
  checkoutPreview?: CheckoutPreview;
  checkout?: {
    items?: Array<{ product_name?: string; variant_name?: string | null; quantity: number; unit_price?: string; line_total: string; image_url?: string | null }>;
    shipping_destination?: { full_name?: string; address_line_1?: string; city?: string; postal_code?: string; country_code?: string };
    payment_method?: string | null;
    shipping_method?: ShippingMethod;
  } | null;
  cart?: CartPayload;
  checkout_token?: string;
  idempotency_key?: string;
  checkout_step?: string;
  checkout_steps?: CheckoutStep[];
  warnings?: string[];
  affected_items?: Array<{ name: string; quantity?: number }>;
  requires_acknowledgement?: boolean;
}) {
  const page = usePage<{ errors: Record<string, string> }>();
  const errors = page.props.errors ?? {};
  const initialMethod = paymentMethods.some((item) => item.code === checkout?.payment_method)
    ? (checkout?.payment_method ?? '')
    : (paymentMethods[0]?.code ?? '');
  const [method, setMethod] = useState(initialMethod);
  const [submitting, setSubmitting] = useState(false);
  const idempotencyKey = useMemo(() => idempotency_key || crypto.randomUUID(), [idempotency_key]);
  const items = checkout?.items ?? cart?.items ?? [];
  const address = checkout?.shipping_destination;
  const shipping = checkout?.shipping_method ?? selectedShippingMethod;
  const selectedMethod = paymentMethods.find((item) => item.code === method);
  const totals = {
    subtotal: checkoutPreview?.items_subtotal,
    discount: checkoutPreview?.discount,
    shipping: checkoutPreview?.shipping,
    tax: checkoutPreview?.tax,
    grand_total: checkoutPreview?.grand_total ?? '0.00',
    currency: checkoutPreview?.currency ?? 'GBP',
  };

  return (
    <StorefrontLayout chrome="checkout" seo={{ title: 'Payment', description: 'Review and place your order.', robots: 'noindex,nofollow' }}>
      <CheckoutProgress current={checkout_step} steps={checkout_steps} />
      <h1 className="text-3xl font-semibold text-slate-900">Payment</h1>
      <CheckoutAlerts warnings={warnings} errors={errors} affectedItems={affected_items} requiresAcknowledgement={requires_acknowledgement} />

        <div className="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
        <div className="space-y-6">
          <section className="rounded-2xl border border-slate-200 p-5">
            <h2 className="text-lg font-semibold text-slate-900">Review</h2>
            <div className="mt-4 grid gap-4 text-sm text-slate-600 md:grid-cols-2">
              <div>
                <h3 className="font-semibold text-slate-900">Delivery</h3>
                <p className="mt-2">{shipping?.name}</p>
                {address ? (
                  <p className="mt-2 text-slate-500">
                    {address.full_name}, {address.address_line_1}, {address.city} {address.postal_code}
                  </p>
                ) : null}
                <Link href="/checkout/delivery" className="mt-2 inline-block text-blue-600">
                  Change delivery
                </Link>
              </div>
              <div>
                <h3 className="font-semibold text-slate-900">Payment method</h3>
                <p className="mt-2">{selectedMethod?.name ?? 'Choose a method'}</p>
              </div>
            </div>
          </section>

          <fieldset>
            <legend className="text-lg font-semibold text-slate-900">How would you like to pay?</legend>
            {payment_method_notice ? (
              <p className="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                {payment_method_notice}
              </p>
            ) : null}
            <div className="mt-4 space-y-3">
              {paymentMethods.map((option) => (
                <label
                  key={option.code}
                  className={`block cursor-pointer rounded-2xl border p-4 ${method === option.code ? 'border-blue-500 bg-blue-600/5' : 'border-slate-200'}`}
                >
                  <input
                    type="radio"
                    name="payment_method"
                    className="mr-3"
                    checked={method === option.code}
                    onChange={() => {
                      setMethod(option.code);
                      router.post('/checkout/payment', { payment_method: option.code }, { preserveScroll: true });
                    }}
                  />
                  <span className="font-semibold text-slate-900">{option.name}</span>
                  {option.description ? <span className="mt-1 block text-sm text-slate-500">{option.description}</span> : null}
                </label>
              ))}
            </div>
          </fieldset>

          <PaymentInstructions method={method} bank={paymentInstructions?.bank_transfer} crypto={paymentInstructions?.crypto} />
          <p className="text-sm text-slate-500">Use your order number as the payment reference unless the store provides another reference.</p>
        </div>

        <aside className="lg:sticky lg:top-6 space-y-4">
          <OrderSummary items={items} totals={totals} />
          <PaymentSummary
            method={selectedMethod?.name}
            amountDue={totals.grand_total}
            currency={totals.currency}
            status="PENDING"
            statusLabel="Payment pending verification"
          />
          <Button
            type="button"
            variant="gold"
            className="w-full"
            isLoading={submitting}
            disabled={submitting || requires_acknowledgement || !method}
            onClick={() => {
              if (submitting) {
                return;
              }
              setSubmitting(true);
              router.post(
                '/checkout',
                {
                  checkout_token,
                  payment_method: method,
                  idempotency_key: idempotencyKey,
                },
                { onFinish: () => setSubmitting(false) },
              );
            }}
          >
            Place order
          </Button>
        </aside>
      </div>
    </StorefrontLayout>
  );
}
