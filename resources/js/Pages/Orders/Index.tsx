import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { Link } from '@inertiajs/react';
import { formatMoney } from '@/lib/money';

type Order = {
  id: string;
  order_number: string;
  status_label?: string;
  total_amount: string;
  currency?: string;
};

export default function OrdersIndex({ orders }: { orders: { data: Order[] } }) {
  return (
    <StorefrontLayout seo={{ title: 'Orders', description: 'Your orders.', robots: 'noindex,nofollow' }}>
      <h1 className="text-3xl font-semibold text-white">Your orders</h1>
      <div className="mt-8 space-y-3">
        {(orders.data ?? []).map((order) => (
          <Link key={order.id} href={`/orders/${order.id}`} className="block rounded-xl border border-slate-800 p-4 hover:border-amber-500/40">
            <p className="text-white">{order.order_number}</p>
            <p className="text-sm text-slate-400">
              {order.status_label} · {formatMoney(order.total_amount, order.currency ?? 'GBP')}
            </p>
          </Link>
        ))}
      </div>
    </StorefrontLayout>
  );
}
