import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CheckoutAlerts } from '@/components/checkout/CheckoutAlerts';
import { CheckoutProgress, type CheckoutStep } from '@/components/checkout/CheckoutProgress';
import { OrderSummary } from '@/components/checkout/OrderSummary';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import type { CartPayload, CheckoutPreview, ShippingMethod } from '@/types';

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

type Country = { code: string; name: string };

export default function CheckoutIndex({
  cart,
  checkoutPreview,
  checkout,
  addresses = [],
  countries = [],
  checkout_step = 'details',
  checkout_steps = [],
  warnings = [],
  affected_items = [],
  requires_acknowledgement = false,
}: {
  cart?: CartPayload;
  checkoutPreview?: CheckoutPreview;
  checkout?: { items?: Array<{ product_name?: string; variant_name?: string | null; quantity: number; unit_price?: string; line_total: string; image_url?: string | null }>; totals?: Record<string, string> } | null;
  addresses?: Address[];
  countries?: Country[];
  checkout_step?: string;
  checkout_steps?: CheckoutStep[];
  warnings?: string[];
  affected_items?: Array<{ name: string; quantity?: number }>;
  requires_acknowledgement?: boolean;
}) {
  const page = usePage<{ errors: Record<string, string>; auth?: { user?: { name?: string; email?: string } | null } }>();
  const errors = page.props.errors ?? {};
  const [mode, setMode] = useState<'saved' | 'new'>(addresses.length > 0 ? 'saved' : 'new');
  const [submitting, setSubmitting] = useState(false);
  const selectedAddress = addresses[0];
  const destination = checkout && 'shipping_destination' in checkout ? (checkout as { shipping_destination?: Address }).shipping_destination : undefined;
  const form = useForm({
    email: page.props.auth?.user?.email ?? '',
    address_id: mode === 'saved' ? selectedAddress?.id ?? '' : '',
    save_address: false,
    shipping_destination: {
      full_name: destination?.full_name ?? selectedAddress?.full_name ?? page.props.auth?.user?.name ?? '',
      address_line_1: destination?.address_line_1 ?? selectedAddress?.address_line_1 ?? '',
      address_line_2: destination?.address_line_2 ?? selectedAddress?.address_line_2 ?? '',
      city: destination?.city ?? selectedAddress?.city ?? '',
      postal_code: destination?.postal_code ?? selectedAddress?.postal_code ?? '',
      country_code: destination?.country_code ?? checkoutPreview?.country_code ?? selectedAddress?.country_code ?? 'GB',
      phone: destination?.phone ?? selectedAddress?.phone ?? '',
    },
  });

  const applyAddress = (address: Address) => {
    form.setData({
      ...form.data,
      address_id: address.id,
      shipping_destination: {
        full_name: address.full_name,
        address_line_1: address.address_line_1,
        address_line_2: address.address_line_2 ?? '',
        city: address.city,
        postal_code: address.postal_code,
        country_code: address.country_code,
        phone: address.phone ?? '',
      },
    });
  };

  const items = checkout?.items ?? cart?.items ?? [];
  const totals = {
    subtotal: checkoutPreview?.items_subtotal ?? cart?.totals?.subtotal_amount,
    discount: checkoutPreview?.discount ?? cart?.totals?.discount_amount,
    shipping: checkoutPreview?.shipping,
    tax: checkoutPreview?.tax ?? cart?.totals?.tax_amount,
    grand_total: checkoutPreview?.grand_total ?? cart?.totals?.grand_total_amount ?? '0.00',
    currency: checkoutPreview?.currency ?? cart?.totals?.currency ?? 'GBP',
  };

  return (
    <StorefrontLayout chrome="checkout" seo={{ title: 'Checkout details', description: 'Secure checkout details.', robots: 'noindex,nofollow' }}>
      <CheckoutProgress current={checkout_step} steps={checkout_steps} />
      <h1 className="text-3xl font-semibold text-slate-900">Your details</h1>
      <CheckoutAlerts warnings={warnings} errors={errors} affectedItems={affected_items} requiresAcknowledgement={requires_acknowledgement} />

      <form
        className="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start"
        noValidate
        onSubmit={(event) => {
          event.preventDefault();
          if (submitting) {
            return;
          }
          setSubmitting(true);
          router.post(
            '/checkout/details',
            {
              email: form.data.email,
              address_id: mode === 'saved' ? form.data.address_id : null,
              save_address: false,
              shipping_destination: form.data.shipping_destination,
            },
            { onFinish: () => setSubmitting(false) },
          );
        }}
      >
        <div className="grid gap-3">
          <Input
            label="Full name"
            value={form.data.shipping_destination.full_name}
            error={errors['shipping_destination.full_name']}
            onChange={(event) => form.setData('shipping_destination', { ...form.data.shipping_destination, full_name: event.target.value })}
          />
          <Input
            label="Email"
            type="email"
            value={form.data.email}
            error={errors.email}
            readOnly={Boolean(page.props.auth?.user?.email)}
            helperText={page.props.auth?.user?.email ? 'Signed-in account email. Confirmation is sent here.' : 'Order confirmation and payment updates are sent here.'}
            onChange={(event) => {
              if (page.props.auth?.user?.email) {
                return;
              }
              form.setData('email', event.target.value);
            }}
          />

          {addresses.length > 0 ? (
            <fieldset className="rounded-xl border border-slate-200 p-4">
              <legend className="px-1 text-sm font-semibold text-slate-900">Delivery address</legend>
              <div className="mt-2 flex flex-wrap gap-4 text-sm">
                <label className="flex items-center gap-2">
                  <input
                    type="radio"
                    name="address_mode"
                    checked={mode === 'saved'}
                    onChange={() => {
                      setMode('saved');
                      if (addresses[0]) {
                        applyAddress(addresses[0]);
                      }
                    }}
                  />
                  Saved address
                </label>
                <label className="flex items-center gap-2">
                  <input
                    type="radio"
                    name="address_mode"
                    checked={mode === 'new'}
                    onChange={() => {
                      setMode('new');
                      form.setData('address_id', '');
                    }}
                  />
                  New address
                </label>
              </div>
              {mode === 'saved' ? (
                <div className="mt-3 space-y-2">
                  {addresses.map((address) => (
                    <label key={address.id} className="block cursor-pointer rounded-lg border border-slate-200 p-3 text-sm has-[:checked]:border-blue-500">
                      <input
                        type="radio"
                        name="saved_address"
                        className="mr-2"
                        checked={form.data.address_id === address.id}
                        onChange={() => applyAddress(address)}
                      />
                      {address.full_name}, {address.address_line_1}, {address.city} {address.postal_code}
                    </label>
                  ))}
                </div>
              ) : null}
            </fieldset>
          ) : null}

          {mode === 'new' || addresses.length === 0 ? (
            <>
              <Input
                label="Address line 1"
                value={form.data.shipping_destination.address_line_1}
                error={errors['shipping_destination.address_line_1']}
                onChange={(event) => form.setData('shipping_destination', { ...form.data.shipping_destination, address_line_1: event.target.value })}
              />
              <Input
                label="Address line 2"
                value={form.data.shipping_destination.address_line_2 ?? ''}
                onChange={(event) => form.setData('shipping_destination', { ...form.data.shipping_destination, address_line_2: event.target.value })}
              />
              <Input
                label="City"
                value={form.data.shipping_destination.city}
                error={errors['shipping_destination.city']}
                onChange={(event) => form.setData('shipping_destination', { ...form.data.shipping_destination, city: event.target.value })}
              />
              <Input
                label="Postcode"
                value={form.data.shipping_destination.postal_code}
                error={errors['shipping_destination.postal_code']}
                onChange={(event) => form.setData('shipping_destination', { ...form.data.shipping_destination, postal_code: event.target.value })}
              />
              <div>
                <label htmlFor="country-code" className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">
                  Country
                </label>
                <select
                  id="country-code"
                  className="w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900"
                  value={form.data.shipping_destination.country_code}
                  onChange={(event) => form.setData('shipping_destination', { ...form.data.shipping_destination, country_code: event.target.value })}
                >
                  {(countries.length > 0 ? countries : [{ code: 'GB', name: 'United Kingdom' }]).map((country) => (
                    <option key={country.code} value={country.code}>
                      {country.name}
                    </option>
                  ))}
                </select>
                {errors['shipping_destination.country_code'] ? (
                  <p className="mt-1.5 text-xs text-rose-400" role="alert">
                    {errors['shipping_destination.country_code']}
                  </p>
                ) : null}
              </div>
              <Input
                label="Phone"
                type="tel"
                autoComplete="tel"
                required
                value={form.data.shipping_destination.phone ?? ''}
                error={errors['shipping_destination.phone']}
                helperText="Required for delivery contact."
                onChange={(event) => form.setData('shipping_destination', { ...form.data.shipping_destination, phone: event.target.value })}
              />
            </>
          ) : null}
        </div>

        <aside className="lg:sticky lg:top-6">
          <OrderSummary
            items={items.map((item) => ({
              product_name: 'product_name' in item ? item.product_name : undefined,
              product_name_snapshot: 'product_name_snapshot' in item ? (item as { product_name_snapshot?: string }).product_name_snapshot : undefined,
              variant_name: item.variant_name,
              quantity: item.quantity,
              unit_price: item.unit_price,
              line_total: item.line_total,
              image_url: item.image_url,
            }))}
            totals={totals}
          />
          <Button type="submit" variant="gold" className="mt-6 w-full" isLoading={submitting} disabled={submitting || requires_acknowledgement}>
            Continue to delivery
          </Button>
        </aside>
      </form>
    </StorefrontLayout>
  );
}
