import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { Link, router, useForm } from '@inertiajs/react';
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
    <StorefrontLayout seo={{ title: 'Addresses', description: 'Delivery addresses.', robots: 'noindex,nofollow' }}>
      <h1 className="text-3xl font-semibold text-white">Addresses</h1>
      <Link href="/account" className="mt-3 inline-block text-sm text-amber-400">
        Back to account
      </Link>
      <div className="mt-8 space-y-3">
        {addresses.map((address) => (
          <article key={address.id} className="rounded-xl border border-slate-800 p-4 text-sm text-slate-300">
            <p className="text-white">{address.full_name}</p>
            <p>
              {address.address_line_1}, {address.city} {address.postal_code}, {address.country_code}
            </p>
            <Button size="sm" variant="ghost" className="mt-2" onClick={() => router.delete(`/account/addresses/${address.id}`)}>
              Remove
            </Button>
          </article>
        ))}
      </div>
      <form
        className="mt-10 grid max-w-md gap-3"
        onSubmit={(event) => {
          event.preventDefault();
          form.post('/account/addresses');
        }}
      >
        <h2 className="text-lg text-white">Add address</h2>
        <Input label="Name" value={form.data.full_name} error={form.errors.full_name} onChange={(event) => form.setData('full_name', event.target.value)} required />
        <Input label="Address line 1" value={form.data.address_line_1} error={form.errors.address_line_1} onChange={(event) => form.setData('address_line_1', event.target.value)} required />
        <Input label="City" value={form.data.city} error={form.errors.city} onChange={(event) => form.setData('city', event.target.value)} required />
        <Input label="Postcode" value={form.data.postal_code} error={form.errors.postal_code} onChange={(event) => form.setData('postal_code', event.target.value)} required />
        <Input label="Country" maxLength={2} value={form.data.country_code} error={form.errors.country_code} onChange={(event) => form.setData('country_code', event.target.value.toUpperCase())} required />
        <Button type="submit" variant="gold" isLoading={form.processing}>
          Save address
        </Button>
      </form>
    </StorefrontLayout>
  );
}
