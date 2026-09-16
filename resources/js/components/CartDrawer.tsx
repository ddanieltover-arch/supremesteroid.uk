import { Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, ShoppingBag, Trash2, Truck, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { formatMoney } from '@/lib/money';
import type { CartPayload } from '@/types';

type CartDrawerProps = {
  isOpen: boolean;
  onClose: () => void;
};

export function CartDrawer({ isOpen, onClose }: CartDrawerProps) {
  const page = usePage<{ cart: CartPayload; auth?: { user?: unknown } }>();
  const cart = page.props.cart;
  const [pendingId, setPendingId] = useState<string | null>(null);
  const currency = cart?.totals?.currency ?? 'GBP';
  const eligibility = cart?.shipping_eligibility;

  useEffect(() => {
    if (!isOpen) {
      return;
    }
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onClose();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [isOpen, onClose]);

  if (!isOpen) {
    return null;
  }

  const updateQuantity = (id: string, quantity: number) => {
    setPendingId(id);
    router.patch(`/cart/items/${id}`, { quantity }, {
      preserveScroll: true,
      onFinish: () => setPendingId(null),
    });
  };

  return (
    <div className="fixed inset-0 z-50 overflow-hidden">
      <button type="button" className="absolute inset-0 bg-slate-950/75 backdrop-blur-sm" aria-label="Close cart" onClick={onClose} />
      <div
        className="fixed inset-y-0 right-0 flex max-w-full pl-10"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cart-drawer-title"
      >
        <div className="flex w-screen max-w-md flex-col border-l border-slate-800 bg-slate-900 shadow-2xl">
          <div className="flex items-center justify-between border-b border-slate-800 p-5">
            <div className="flex items-center gap-2">
              <ShoppingBag className="h-5 w-5 text-amber-400" aria-hidden="true" />
              <h2 id="cart-drawer-title" className="text-base font-bold uppercase tracking-wide text-slate-100">
                Your basket ({cart?.totals?.items_count ?? 0})
              </h2>
            </div>
            <button type="button" onClick={onClose} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-800 hover:text-slate-100">
              <X className="h-5 w-5" />
              <span className="sr-only">Close</span>
            </button>
          </div>

          {eligibility ? (
            <div className="border-b border-slate-800 bg-slate-950/60 p-4 text-xs">
              <div className="mb-1.5 flex items-center justify-between font-medium">
                <span className="flex items-center gap-1.5 text-slate-300">
                  <Truck className="h-4 w-4 text-amber-400" aria-hidden="true" />
                  {eligibility.free_shipping_eligible ? (
                    <span className="font-semibold text-emerald-400">Free UK Standard Delivery unlocked</span>
                  ) : (
                    <span>
                      Add <strong className="text-amber-400">{formatMoney(eligibility.remaining_to_threshold, eligibility.currency)}</strong> more for free UK delivery
                    </span>
                  )}
                </span>
                <span className="font-mono text-slate-400">{eligibility.progress_percent}%</span>
              </div>
              <div className="h-1.5 w-full overflow-hidden rounded-full bg-slate-800">
                <div
                  className="h-full rounded-full bg-gradient-to-r from-amber-500 to-emerald-400"
                  style={{ width: `${eligibility.progress_percent}%` }}
                />
              </div>
            </div>
          ) : null}

          <div className="flex-1 space-y-4 overflow-y-auto p-5">
            {(cart?.items?.length ?? 0) === 0 ? (
              <div className="flex h-full flex-col items-center justify-center p-6 text-center text-slate-400">
                <ShoppingBag className="mb-3 h-12 w-12 text-slate-700" aria-hidden="true" />
                <p className="text-sm font-semibold text-slate-200">Your basket is empty</p>
              </div>
            ) : (
              cart.items.map((item) => (
                <div key={item.id} className="flex gap-3.5 rounded-xl border border-slate-800/80 bg-slate-950/50 p-3">
                  {item.image_url ? (
                    <img src={item.image_url} alt="" className="h-16 w-16 shrink-0 rounded-lg object-cover bg-slate-900" />
                  ) : (
                    <div className="h-16 w-16 shrink-0 rounded-lg bg-slate-800" />
                  )}
                  <div className="min-w-0 flex-1">
                    <Link href={`/product/${item.product_slug}`} className="text-xs font-semibold text-slate-200 hover:text-amber-300">
                      {item.product_name}
                    </Link>
                    {item.variant_name ? <p className="text-[10px] text-slate-400">{item.variant_name}</p> : null}
                    <p className="mt-1 text-xs font-bold text-slate-100">{formatMoney(item.line_total, currency)}</p>
                    <p className="text-[10px] text-slate-500">{formatMoney(item.unit_price, currency)} each</p>
                    {!item.is_eligible ? <p className="text-[10px] text-rose-300">Unavailable</p> : null}
                    <div className="mt-2 flex items-center justify-between">
                      <div className="flex items-center rounded border border-slate-800 bg-slate-900 text-xs">
                        <button
                          type="button"
                          className="px-2 py-0.5 text-slate-400"
                          disabled={pendingId === item.id}
                          onClick={() => updateQuantity(item.id, item.quantity - 1)}
                          aria-label="Decrease quantity"
                        >
                          −
                        </button>
                        <span className="px-2 py-0.5 font-mono text-slate-200">{item.quantity}</span>
                        <button
                          type="button"
                          className="px-2 py-0.5 text-slate-400"
                          disabled={pendingId === item.id}
                          onClick={() => updateQuantity(item.id, item.quantity + 1)}
                          aria-label="Increase quantity"
                        >
                          +
                        </button>
                      </div>
                      <button
                        type="button"
                        className="p-1 text-slate-500 hover:text-rose-400"
                        disabled={pendingId === item.id}
                        onClick={() => {
                          setPendingId(item.id);
                          router.delete(`/cart/items/${item.id}`, { preserveScroll: true, onFinish: () => setPendingId(null) });
                        }}
                        aria-label={`Remove ${item.product_name}`}
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </div>
                  </div>
                </div>
              ))
            )}
          </div>

          {(cart?.items?.length ?? 0) > 0 ? (
            <div className="space-y-3 border-t border-slate-800 bg-slate-950/70 p-5">
              <div className="flex items-center justify-between text-sm">
                <span className="text-slate-400">Basket subtotal</span>
                <span className="text-lg font-extrabold text-slate-100">{formatMoney(cart.totals.subtotal_amount, currency)}</span>
              </div>
              <Link
                href={page.props.auth?.user ? '/checkout' : '/login'}
                className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-amber-500 via-amber-400 to-amber-600 px-4 py-3 text-sm font-bold uppercase tracking-wide text-slate-950"
                onClick={onClose}
              >
                Proceed to secure checkout
                <ArrowRight className="h-4 w-4" aria-hidden="true" />
              </Link>
              <Button variant="ghost" className="w-full" onClick={() => router.delete('/cart', { preserveScroll: true })}>
                Clear basket
              </Button>
            </div>
          ) : null}
        </div>
      </div>
    </div>
  );
}
