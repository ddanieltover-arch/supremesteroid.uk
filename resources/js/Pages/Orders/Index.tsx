import { AccountLayout } from '@/Layouts/AccountLayout';
import { Link } from '@inertiajs/react';
import { Pagination } from '@/components/Pagination';
import { formatMoney } from '@/lib/money';
import type { Paginated } from '@/types';

type Order = {
  id: string;
  order_number: string;
  placed_at?: string | null;
  status_label?: string;
  total_amount: string;
  currency?: string;
  payment?: { status_label?: string | null } | null;
};

export default function OrdersIndex({ orders }: { orders: Paginated<Order> }) {
  const items = orders.data ?? [];

  return (
    <AccountLayout title="Orders" seo={{ title: 'Orders', description: 'Your orders.', robots: 'noindex,nofollow' }}>
      {items.length === 0 ? (
        <div className="rounded-2xl border border-slate-200 p-8 text-center">
          <p className="text-slate-900">No orders yet.</p>
          <Link href="/shop" className="mt-4 inline-flex min-h-11 items-center rounded-full bg-blue-600 px-5 text-sm font-semibold text-white">
            Continue shopping
          </Link>
        </div>
      ) : (
        <div className="space-y-3">
          {items.map((order) => (
            <article key={order.id} className="flex flex-col gap-3 rounded-xl border border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p className="font-semibold text-slate-900">{order.order_number}</p>
                <p className="mt-1 text-sm text-slate-500">
                  {order.placed_at ? new Date(order.placed_at).toLocaleDateString('en-GB') : ''}
                  {order.status_label ? ` · ${order.status_label}` : ''}
                  {order.payment?.status_label ? ` · ${order.payment.status_label}` : ''}
                </p>
                <p className="mt-1 text-sm text-slate-800">{formatMoney(order.total_amount, order.currency ?? 'GBP')}</p>
              </div>
              <Link href={`/account/orders/${order.id}`} className="inline-flex min-h-11 items-center text-sm text-blue-600">
                View order
              </Link>
            </article>
          ))}
          <Pagination pager={orders} />
        </div>
      )}
    </AccountLayout>
  );
}
