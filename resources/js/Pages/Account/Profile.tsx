import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { Link, router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';

export default function Profile({
  profile,
}: {
  profile: { name: string; email: string; phone?: string | null; email_verified: boolean };
}) {
  const form = useForm({
    name: profile.name,
    phone: profile.phone ?? '',
  });

  return (
    <StorefrontLayout seo={{ title: 'Account', description: 'Manage your account.', robots: 'noindex,nofollow' }}>
      <h1 className="text-3xl font-semibold text-white">Account</h1>
      <nav className="mt-4 flex flex-wrap gap-3 text-sm">
        <Link href="/account" className="text-amber-400">
          Profile
        </Link>
        <Link href="/account/addresses" className="text-slate-300">
          Addresses
        </Link>
        <Link href="/account/orders" className="text-slate-300">
          Orders
        </Link>
        <Link href="/account/wishlist" className="text-slate-300">
          Wishlist
        </Link>
      </nav>
      <p className="mt-6 text-sm text-slate-400">{profile.email}</p>
      {!profile.email_verified ? (
        <p className="mt-2 text-sm text-amber-300">
          Email not verified. <Link href="/email/verify">Verify now</Link>
        </p>
      ) : null}
      <form
        className="mt-8 grid max-w-md gap-3"
        onSubmit={(event) => {
          event.preventDefault();
          form.patch('/account');
        }}
      >
        <Input label="Name" value={form.data.name} error={form.errors.name} onChange={(event) => form.setData('name', event.target.value)} />
        <Input label="Phone" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
        <Button type="submit" variant="gold" isLoading={form.processing}>
          Save
        </Button>
      </form>
      <Button className="mt-8" variant="secondary" onClick={() => router.post('/logout')}>
        Sign out
      </Button>
    </StorefrontLayout>
  );
}
