import { AccountLayout } from '@/Layouts/AccountLayout';
import { Link, router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { formatMoney } from '@/lib/money';

type RecentOrder = {
  id: string;
  order_number: string;
  placed_at?: string | null;
  status_label?: string | null;
  payment_status_label?: string | null;
  total_amount: string;
  currency?: string;
};

export default function Profile({
  profile,
  dashboard,
}: {
  profile: { name: string; email: string; phone?: string | null; email_verified: boolean };
  dashboard?: {
    wishlist_count?: number;
    address_count?: number;
    order_count?: number;
    recent_orders?: RecentOrder[];
  };
}) {
  const form = useForm({
    name: profile.name,
    phone: profile.phone ?? '',
  });
  const orders = dashboard?.recent_orders ?? [];

  return (
    <AccountLayout title="Account overview" seo={{ title: 'Account', description: 'Manage your account.', robots: 'noindex,nofollow' }}>
      <p className="text-slate-600">Welcome back, {profile.name}.</p>
      <p className="mt-1 text-sm text-slate-500">{profile.email}</p>

      <div className="mt-8 grid gap-4 sm:grid-cols-3">
        <article className="rounded-2xl border border-slate-200 bg-white/60 p-4">
          <p className="text-xs uppercase tracking-wider text-slate-500">Orders</p>
          <p className="mt-2 text-2xl font-semibold text-slate-900">{dashboard?.order_count ?? 0}</p>
        </article>
        <article className="rounded-2xl border border-slate-200 bg-white/60 p-4">
          <p className="text-xs uppercase tracking-wider text-slate-500">Wishlist</p>
          <p className="mt-2 text-2xl font-semibold text-slate-900">{dashboard?.wishlist_count ?? 0}</p>
        </article>
        <article className="rounded-2xl border border-slate-200 bg-white/60 p-4">
          <p className="text-xs uppercase tracking-wider text-slate-500">Saved addresses</p>
          <p className="mt-2 text-2xl font-semibold text-slate-900">{dashboard?.address_count ?? 0}</p>
        </article>
      </div>

      <section className="mt-10">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold text-slate-900">Recent orders</h2>
          <Link href="/account/orders" className="text-sm text-blue-600">
            View all
          </Link>
        </div>
        {orders.length === 0 ? (
          <p className="mt-4 text-sm text-slate-500">No orders yet.</p>
        ) : (
          <ul className="mt-4 space-y-3">
            {orders.map((order) => (
              <li key={order.id} className="rounded-xl border border-slate-200 p-4">
                <Link href={`/account/orders/${order.id}`} className="text-slate-900 hover:text-blue-600">
                  {order.order_number}
                </Link>
                <p className="mt-1 text-sm text-slate-500">
                  {order.status_label}
                  {order.payment_status_label ? ` · ${order.payment_status_label}` : ''}
                  {' · '}
                  {formatMoney(order.total_amount, order.currency ?? 'GBP')}
                </p>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section id="profile" className="mt-12">
        <h2 className="text-lg font-semibold text-slate-900">Profile</h2>
        <form
          className="mt-4 grid max-w-md gap-3"
          onSubmit={(event) => {
            event.preventDefault();
            form.patch('/account');
          }}
        >
          <Input label="Name" value={form.data.name} error={form.errors.name} onChange={(event) => form.setData('name', event.target.value)} />
          <Input label="Phone" value={form.data.phone} helperText="Optional" onChange={(event) => form.setData('phone', event.target.value)} />
          <Button type="submit" variant="gold" isLoading={form.processing}>
            Save profile
          </Button>
        </form>
      </section>

      <section id="security" className="mt-12 max-w-md">
        <h2 className="text-lg font-semibold text-slate-900">Security</h2>
        <p className="mt-2 text-sm text-slate-500">
          {profile.email_verified ? 'Your email address is verified.' : 'Your email address is not verified yet.'}
        </p>
        {!profile.email_verified ? (
          <Link href="/email/verify" className="mt-3 inline-flex min-h-11 items-center text-sm text-blue-600">
            Verify email
          </Link>
        ) : null}
        <Button className="mt-6" variant="secondary" onClick={() => router.post('/logout')}>
          Sign out
        </Button>
      </section>
    </AccountLayout>
  );
}
