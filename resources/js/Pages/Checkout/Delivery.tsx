import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { CheckoutAlerts } from '@/components/checkout/CheckoutAlerts';
import { CheckoutProgress, type CheckoutStep } from '@/components/checkout/CheckoutProgress';
import { OrderSummary } from '@/components/checkout/OrderSummary';
import { Button } from '@/components/ui/Button';
import { formatMoney } from '@/lib/money';
import type { CartPayload, CheckoutPreview, ShippingMethod } from '@/types';

type Destination = {
  full_name?: string;
  address_line_1?: string;
  city?: string;
  postal_code?: string;
  country_code?: string;
};

export default function CheckoutDelivery({
  availableShippingMethods = [],
  selectedShippingMethod,
  checkoutPreview,
  checkout,
  cart,
  checkout_step = 'delivery',
  checkout_steps = [],
  warnings = [],
  affected_items = [],
  requires_acknowledgement = false,
}: {
  availableShippingMethods?: ShippingMethod[];
  selectedShippingMethod?: ShippingMethod;
  checkoutPreview?: CheckoutPreview;
  checkout?: {
    items?: Array<{ product_name?: string; variant_name?: string | null; quantity: number; unit_price?: string; line_total: string; image_url?: string | null }>;
    shipping_destination?: Destination;
  } | null;
  cart?: CartPayload;
  checkout_step?: string;
  checkout_steps?: CheckoutStep[];
  warnings?: string[];
  affected_items?: Array<{ name: string; quantity?: number }>;
  requires_acknowledgement?: boolean;
}) {
  const page = usePage<{ errors: Record<string, string> }>();
  const errors = page.props.errors ?? {};
  const [method, setMethod] = useState(selectedShippingMethod?.code ?? availableShippingMethods[0]?.code ?? '');
  const [refreshing, setRefreshing] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (method) {
      return;
    }
    const fallback = selectedShippingMethod?.code ?? availableShippingMethods[0]?.code ?? '';
    if (fallback) {
      setMethod(fallback);
    }
  }, [method, selectedShippingMethod?.code, availableShippingMethods]);

  const address = checkout?.shipping_destination;
  const items = checkout?.items ?? cart?.items ?? [];
  const totals = {
    subtotal: checkoutPreview?.items_subtotal,
    discount: checkoutPreview?.discount,
    shipping: checkoutPreview?.shipping,
    tax: checkoutPreview?.tax,
    grand_total: checkoutPreview?.grand_total ?? '0.00',
    currency: checkoutPreview?.currency ?? 'GBP',
  };

  const refreshMethod = (code: string) => {
    setMethod(code);
    setRefreshing(true);
    router.get('/checkout/delivery', { shipping_method_code: code }, { preserveScroll: true, onFinish: () => setRefreshing(false) });
  };

  return (
    <StorefrontLayout chrome="checkout" seo={{ title: 'Delivery', description: 'Choose delivery.', robots: 'noindex,nofollow' }}>
      <CheckoutProgress current={checkout_step} steps={checkout_steps} />
      <h1 className="text-3xl font-semibold text-slate-900">Delivery</h1>
      <CheckoutAlerts warnings={warnings} errors={errors} affectedItems={affected_items} requiresAcknowledgement={requires_acknowledgement} />

        <div className="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
        <div className="space-y-6">
          <section className="rounded-2xl border border-slate-200 p-5">
            <h2 className="text-lg font-semibold text-slate-900">Delivering to</h2>
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
            <Link href="/checkout" className="mt-3 inline-block text-sm text-blue-600">
              Change address
            </Link>
          </section>

          <fieldset>
            <legend className="text-lg font-semibold text-slate-900">Shipping method</legend>
            <div className="mt-4 space-y-3">
              {availableShippingMethods.map((option) => {
                const selected = method === option.code;
                return (
                  <label
                    key={option.code}
                    className={`block cursor-pointer rounded-2xl border p-4 ${selected ? 'border-blue-500 bg-blue-600/5' : 'border-slate-200'}`}
                  >
                    <div className="flex items-start justify-between gap-4">
                      <span className="flex items-start gap-3">
                        <input
                          type="radio"
                          name="shipping_method_code"
                          className="mt-1"
                          checked={selected}
                          onChange={() => refreshMethod(option.code)}
                        />
                        <span>
                          <span className="block font-semibold text-slate-900">{option.name}</span>
                          {option.description ? <span className="mt-1 block text-sm text-slate-500">{option.description}</span> : null}
                        </span>
                      </span>
                      <span className="shrink-0 font-mono text-blue-600">
                        {option.is_free ? 'Free' : formatMoney(option.rate_amount ?? String(option.rate ?? 0), option.currency)}
                      </span>
                    </div>
                  </label>
                );
              })}
            </div>
            {errors.shipping_method_code ? (
              <p className="mt-2 text-xs text-rose-400" role="alert">
                {errors.shipping_method_code}
              </p>
            ) : null}
          </fieldset>
        </div>

        <aside className="lg:sticky lg:top-6">
          <OrderSummary items={items} totals={totals} />
          <Button
            type="button"
            variant="gold"
            className="mt-6 w-full"
            isLoading={submitting}
            disabled={refreshing || submitting || !method}
            onClick={() => {
              if (submitting) {
                return;
              }
              setSubmitting(true);
              router.post('/checkout/delivery', { shipping_method_code: method }, { onFinish: () => setSubmitting(false) });
            }}
          >
            Continue to payment
          </Button>
        </aside>
      </div>
    </StorefrontLayout>
  );
}
