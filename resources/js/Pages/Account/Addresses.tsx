import { AccountLayout } from '@/Layouts/AccountLayout';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';

type Address = {
  id: string;
  type: string;
  full_name: string;
  address_line_1: string;
  address_line_2?: string | null;
  city: string;
  postal_code: string;
  country_code: string;
  phone?: string | null;
  is_default: boolean;
};

export default function Addresses({ addresses }: { addresses: Address[] }) {
  const [editingId, setEditingId] = useState<string | null>(null);
  const form = useForm({
    type: 'shipping',
    full_name: '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    postal_code: '',
    country_code: 'GB',
    phone: '',
    is_default: true,
  });

  return (
    <AccountLayout title="Addresses" seo={{ title: 'Addresses', description: 'Delivery addresses.', robots: 'noindex,nofollow' }}>
      <div className="space-y-3">
        {addresses.length === 0 ? <p className="text-sm text-slate-400">No saved addresses yet.</p> : null}
        {addresses.map((address) => (
          <article key={address.id} className="rounded-xl border border-slate-800 p-4 text-sm text-slate-400">
            <p className="text-white">{address.full_name}</p>
            <p>
              {address.address_line_1}, {address.city} {address.postal_code}, {address.country_code}
            </p>
            {address.is_default ? <p className="mt-1 text-xs text-amber-300">Default</p> : null}
            <div className="mt-2 flex gap-2">
              <Button
                size="sm"
                variant="ghost"
                onClick={() => {
                  setEditingId(address.id);
                  form.setData({
                    type: address.type,
                    full_name: address.full_name,
                    address_line_1: address.address_line_1,
                    address_line_2: address.address_line_2 ?? '',
                    city: address.city,
                    postal_code: address.postal_code,
                    country_code: address.country_code,
                    phone: address.phone ?? '',
                    is_default: address.is_default,
                  });
                }}
              >
                Edit
              </Button>
              <Button size="sm" variant="ghost" onClick={() => router.delete(`/account/addresses/${address.id}`)}>
                Remove
              </Button>
            </div>
          </article>
        ))}
      </div>
      <form
        className="mt-10 grid max-w-md gap-3"
        onSubmit={(event) => {
          event.preventDefault();
          if (editingId) {
            form.patch(`/account/addresses/${editingId}`, {
              onSuccess: () => setEditingId(null),
            });
          } else {
            form.post('/account/addresses');
          }
        }}
      >
        <h2 className="text-lg text-white">{editingId ? 'Edit address' : 'Add address'}</h2>
        <Input label="Name" value={form.data.full_name} error={form.errors.full_name} onChange={(event) => form.setData('full_name', event.target.value)} required />
        <Input label="Address line 1" value={form.data.address_line_1} error={form.errors.address_line_1} onChange={(event) => form.setData('address_line_1', event.target.value)} required />
        <Input label="City" value={form.data.city} error={form.errors.city} onChange={(event) => form.setData('city', event.target.value)} required />
        <Input label="Postcode" value={form.data.postal_code} error={form.errors.postal_code} onChange={(event) => form.setData('postal_code', event.target.value)} required />
        <Input label="Country" maxLength={2} value={form.data.country_code} error={form.errors.country_code} helperText="Two-letter ISO code, for example GB." onChange={(event) => form.setData('country_code', event.target.value.toUpperCase())} required />
        <Button type="submit" variant="gold" isLoading={form.processing}>
          Save address
        </Button>
      </form>
    </AccountLayout>
  );
}
