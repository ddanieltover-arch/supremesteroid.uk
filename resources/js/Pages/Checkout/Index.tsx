import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { formatMoney } from '@/lib/money';
import type { CartPayload, CheckoutPreview, ShippingMethod } from '@/types';

type PaymentMethod = { code: string; name: string; description?: string };
type Address = {
  id: string;
  full_name: string;
  address_line_1: string;
  address_line_2?: string | null;
  city: string;
  postal_code: string;
  country_code: string;
  phone?: string | null;
};

export default function CheckoutIndex({
  availableShippingMethods = [],
  paymentMethods = [],
  cart,
  checkoutPreview,
  selectedShippingMethod,
  addresses = [],
}: {
  availableShippingMethods?: ShippingMethod[];
  paymentMethods?: PaymentMethod[];
  cart?: CartPayload;
  checkoutPreview?: CheckoutPreview;
  selectedShippingMethod?: ShippingMethod;
  addresses?: Address[];
}) {
  const { errors } = usePage().props as { errors: Record<string, string> };
  const idempotencyKey = useMemo(() => crypto.randomUUID(), []);
  const [refreshing, setRefreshing] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const form = useForm({
    full_name: addresses[0]?.full_name ?? '',
    address_line_1: addresses[0]?.address_line_1 ?? '',
    address_line_2: addresses[0]?.address_line_2 ?? '',
    city: addresses[0]?.city ?? '',
    postal_code: addresses[0]?.postal_code ?? '',
    country_code: checkoutPreview?.country_code ?? 'GB',
    phone: addresses[0]?.phone ?? '',
    shipping_method_code: selectedShippingMethod?.code ?? availableShippingMethods[0]?.code ?? 'UK_STANDARD',
    payment_method: paymentMethods[0]?.code ?? 'BANK_TRANSFER',
    customer_notes: '',
    idempotency_key: idempotencyKey,
  });

  const refreshShipping = (country: string, method: string) => {
    setRefreshing(true);
    router.get(
      '/checkout',
      { country_code: country, shipping_method_code: method },
      { preserveState: true, preserveScroll: true, onFinish: () => setRefreshing(false) },
    );
  };

  const preview = checkoutPreview;
  const currency = preview?.currency ?? cart?.totals?.currency ?? 'GBP';

  return (
    <StorefrontLayout seo={{ title: 'Checkout', description: 'Secure checkout.', robots: 'noindex,nofollow' }}>
      <h1 className="text-3xl font-semibold text-white">Checkout</h1>
      {errors.checkout ? <Alert type="error" className="mt-4">{errors.checkout}</Alert> : null}

      <form
        className="mt-8 grid gap-8 lg:grid-cols-2"
        onSubmit={(event) => {
          event.preventDefault();
          setSubmitting(true);
          router.post(
            '/checkout',
            {
              shipping_method_code: form.data.shipping_method_code,
              payment_method: form.data.payment_method,
              idempotency_key: form.data.idempotency_key,
              customer_notes: form.data.customer_notes,
              shipping_destination: {
                country_code: form.data.country_code,
                full_name: form.data.full_name,
                address_line_1: form.data.address_line_1,
                address_line_2: form.data.address_line_2,
                city: form.data.city,
                postal_code: form.data.postal_code,
                phone: form.data.phone,
              },
            },
            {
              onFinish: () => setSubmitting(false),
            },
          );
        }}
      >
        <div className="grid gap-3">
          <Input label="Delivery name" value={form.data.full_name} error={errors['shipping_destination.full_name']} onChange={(event) => form.setData('full_name', event.target.value)} required />
          <Input label="Address line 1" value={form.data.address_line_1} error={errors['shipping_destination.address_line_1']} onChange={(event) => form.setData('address_line_1', event.target.value)} required />
          <Input label="Address line 2" value={form.data.address_line_2} onChange={(event) => form.setData('address_line_2', event.target.value)} />
          <Input label="City" value={form.data.city} error={errors['shipping_destination.city']} onChange={(event) => form.setData('city', event.target.value)} required />
          <Input label="Postcode" value={form.data.postal_code} error={errors['shipping_destination.postal_code']} onChange={(event) => form.setData('postal_code', event.target.value)} required />
          <Input
            label="Country (ISO)"
            maxLength={2}
            value={form.data.country_code}
            error={errors['shipping_destination.country_code']}
            onChange={(event) => {
              const value = event.target.value.toUpperCase();
              form.setData('country_code', value);
              if (value.length === 2) {
                refreshShipping(value, form.data.shipping_method_code);
              }
            }}
            required
          />
          <Input label="Phone" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />

          <fieldset className="rounded-xl border border-slate-800 p-4">
            <legend className="px-1 text-sm font-semibold text-slate-200">Shipping method</legend>
            <div className="mt-2 space-y-2">
              {availableShippingMethods.map((method) => (
                <label key={method.code} className="flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-slate-800 p-3">
                  <span>
                    <input
                      type="radio"
                      name="shipping_method_code"
                      className="mr-2"
                      checked={form.data.shipping_method_code === method.code}
                      onChange={() => {
                        form.setData('shipping_method_code', method.code);
                        refreshShipping(form.data.country_code, method.code);
                      }}
                    />
                    {method.name}
                    {method.is_free ? ' (Free)' : ''}
                  </span>
                  <span className="font-mono text-amber-300">{formatMoney(method.rate_amount ?? String(method.rate ?? 0), method.currency)}</span>
                </label>
              ))}
            </div>
          </fieldset>

          <fieldset className="rounded-xl border border-slate-800 p-4">
            <legend className="px-1 text-sm font-semibold text-slate-200">Payment method</legend>
            <div className="mt-2 space-y-2">
              {paymentMethods.map((method) => (
                <label key={method.code} className="block cursor-pointer rounded-lg border border-slate-800 p-3">
                  <input
                    type="radio"
                    name="payment_method"
                    className="mr-2"
                    checked={form.data.payment_method === method.code}
                    onChange={() => form.setData('payment_method', method.code)}
                  />
                  {method.name}
                  {method.description ? <span className="mt-1 block text-xs text-slate-500">{method.description}</span> : null}
                </label>
              ))}
            </div>
          </fieldset>
        </div>

        <aside className="h-fit rounded-2xl border border-slate-800 bg-slate-900/50 p-5">
          <h2 className="text-lg font-semibold text-white">Order summary</h2>
          <ul className="mt-4 space-y-2 text-sm text-slate-300">
            {(cart?.items ?? []).map((item) => (
              <li key={item.id} className="flex justify-between gap-3">
                <span>
                  {item.product_name} × {item.quantity}
                </span>
                <span>{formatMoney(item.line_total, currency)}</span>
              </li>
            ))}
          </ul>
          {preview ? (
            <dl className="mt-6 space-y-2 text-sm">
              <div className="flex justify-between text-slate-400">
                <dt>Items</dt>
                <dd>{formatMoney(preview.items_subtotal, currency)}</dd>
              </div>
              <div className="flex justify-between text-slate-400">
                <dt>Discount</dt>
                <dd>{formatMoney(preview.discount, currency)}</dd>
              </div>
              <div className="flex justify-between text-slate-400">
                <dt>Shipping</dt>
                <dd>{refreshing ? 'Updating…' : formatMoney(preview.shipping, currency)}</dd>
              </div>
              <div className="flex justify-between text-slate-400">
                <dt>Tax</dt>
                <dd>{formatMoney(preview.tax, currency)}</dd>
              </div>
              <div className="flex justify-between border-t border-slate-800 pt-3 text-lg font-bold text-white">
                <dt>Total</dt>
                <dd>{formatMoney(preview.grand_total, currency)}</dd>
              </div>
            </dl>
          ) : null}
          <Button type="submit" variant="gold" className="mt-6 w-full" isLoading={submitting} disabled={refreshing}>
            Place order
          </Button>
        </aside>
      </form>
    </StorefrontLayout>
  );
}
