import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, ShoppingBag, Trash2, Truck, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Portal } from '@/components/ui/Portal';
import { useCart } from '@/hooks/useCart';
import { usePresence } from '@/hooks/usePresence';
import { clearCart, removeCartItem, updateCartItemQuantity } from '@/lib/cartApi';
import { formatMoney } from '@/lib/money';
import clsx from 'clsx';

type CartDrawerProps = {
  isOpen: boolean;
  onClose: () => void;
};

export function CartDrawer({ isOpen, onClose }: CartDrawerProps) {
  const page = usePage<{ auth?: { user?: unknown } }>();
  const cart = useCart();
  const [pendingId, setPendingId] = useState<string | null>(null);
  const panelRef = useRef<HTMLDivElement>(null);
  const previousFocus = useRef<HTMLElement | null>(null);
  const currency = cart?.totals?.currency ?? 'GBP';
  const eligibility = cart?.shipping_eligibility;
  const { mounted, visible } = usePresence(isOpen, 220);

  useEffect(() => {
    if (!isOpen || !visible) {
      return;
    }
    previousFocus.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const panel = panelRef.current;
    panel?.querySelector<HTMLElement>('button, [href], input, select, textarea')?.focus();

    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        onClose();
        return;
      }
      if (event.key !== 'Tab' || !panel) {
        return;
      }
      const items = Array.from(panel.querySelectorAll<HTMLElement>('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')).filter(
        (item) => !item.hasAttribute('disabled'),
      );
      if (items.length === 0) {
        return;
      }
      const first = items[0];
      const last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };
    window.addEventListener('keydown', onKey);
    const original = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      window.removeEventListener('keydown', onKey);
      document.body.style.overflow = original;
      previousFocus.current?.focus();
    };
  }, [isOpen, visible, onClose]);

  if (!mounted) {
    return null;
  }

  const updateQuantity = (id: string, quantity: number) => {
    setPendingId(id);
    void updateCartItemQuantity(id, quantity).finally(() => setPendingId(null));
  };

  return (
    <Portal>
    <div className="fixed inset-0 z-[60] overflow-hidden">
      <button
        type="button"
        className={clsx(
          'absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity duration-200',
          visible ? 'opacity-100' : 'opacity-0',
        )}
        aria-label="Close cart"
        onClick={onClose}
      />
      <div
        ref={panelRef}
        className={clsx(
          'fixed inset-y-0 right-0 flex max-w-full pl-4 transition-transform ease-out sm:pl-10',
          visible ? 'translate-x-0' : 'translate-x-full',
        )}
        style={{ transitionDuration: '220ms' }}
        role="dialog"
        aria-modal="true"
        aria-labelledby="cart-drawer-title"
      >
        <div className="flex w-screen max-w-md flex-col border-l border-slate-200 bg-slate-50 shadow-2xl">
          <div className="flex items-center justify-between border-b border-slate-200 p-5">
            <div className="flex items-center gap-2">
              <ShoppingBag className="h-5 w-5 text-brand" aria-hidden="true" />
              <h2 id="cart-drawer-title" className="text-base font-bold uppercase tracking-wide text-slate-900">
                Your basket ({cart?.totals?.items_count ?? 0})
              </h2>
            </div>
            <button type="button" onClick={onClose} className="rounded-lg p-1.5 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900">
              <X className="h-5 w-5" />
              <span className="sr-only">Close</span>
            </button>
          </div>

          {cart?.items?.some((item) => !item.is_eligible) ? (
            <Alert type="warning" className="m-4">
              Your basket has changed. We refreshed the latest availability and pricing.
            </Alert>
          ) : null}
          {eligibility ? (
            <div className="border-b border-slate-200 bg-white/60 p-4 text-xs">
              <div className="mb-1.5 flex items-center justify-between font-medium">
                <span className="flex items-center gap-1.5 text-slate-600">
                  <Truck className="h-4 w-4 text-brand" aria-hidden="true" />
                  {eligibility.free_shipping_eligible ? (
                    <span className="font-semibold text-emerald-600">Free UK Standard Delivery unlocked</span>
                  ) : (
                    <span>
                      Add <strong className="text-brand">{formatMoney(eligibility.remaining_to_threshold, eligibility.currency)}</strong> more for free UK delivery
                    </span>
                  )}
                </span>
                <span className="font-mono text-slate-500">{eligibility.progress_percent}%</span>
              </div>
              <div className="h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                <div
                  className="h-full rounded-full bg-gradient-to-r from-blue-500 to-emerald-400 transition-[width] duration-300"
                  style={{ width: `${eligibility.progress_percent}%` }}
                />
              </div>
            </div>
          ) : null}

          <div className="flex-1 space-y-4 overflow-y-auto p-5">
            {(cart?.items?.length ?? 0) === 0 ? (
              <div className="flex h-full flex-col items-center justify-center p-6 text-center text-slate-500">
                <ShoppingBag className="mb-3 h-12 w-12 text-slate-700" aria-hidden="true" />
                <p className="text-sm font-semibold text-slate-800">Your basket is empty</p>
              </div>
            ) : (
              cart.items.map((item) => (
                <div key={item.id} className="flex gap-3.5 rounded-xl border border-slate-200 bg-white/50 p-3">
                  {item.image_url ? (
                    <img src={item.image_url} alt="" className="h-16 w-16 shrink-0 rounded-lg object-cover bg-slate-50" />
                  ) : (
                    <div className="h-16 w-16 shrink-0 rounded-lg bg-slate-100" />
                  )}
                  <div className="min-w-0 flex-1">
                    <Link href={`/product/${item.product_slug}`} className="text-xs font-semibold text-slate-800 transition-colors hover:text-brand">
                      {item.product_name}
                    </Link>
                    {item.variant_name ? <p className="text-[10px] text-slate-500">{item.variant_name}</p> : null}
                    <p className="mt-1 text-xs font-bold text-slate-900">{formatMoney(item.line_total, currency)}</p>
                    <p className="text-[10px] text-slate-500">{formatMoney(item.unit_price, currency)} each</p>
                    {!item.is_eligible ? <p className="text-[10px] text-rose-600">Unavailable</p> : null}
                    <div className="mt-2 flex items-center justify-between">
                      <div className="flex items-center rounded border border-slate-200 bg-slate-50 text-xs">
                        <button
                          type="button"
                          className="px-2 py-0.5 text-slate-500"
                          disabled={pendingId === item.id}
                          onClick={() => updateQuantity(item.id, item.quantity - 1)}
                          aria-label="Decrease quantity"
                        >
                          −
                        </button>
                        <span className="px-2 py-0.5 font-mono text-slate-800">{item.quantity}</span>
                        <button
                          type="button"
                          className="px-2 py-0.5 text-slate-500"
                          disabled={pendingId === item.id}
                          onClick={() => updateQuantity(item.id, item.quantity + 1)}
                          aria-label="Increase quantity"
                        >
                          +
                        </button>
                      </div>
                      <button
                        type="button"
                        className="p-1 text-slate-500 transition-colors hover:text-rose-400"
                        disabled={pendingId === item.id}
                        onClick={() => {
                          setPendingId(item.id);
                          void removeCartItem(item.id).finally(() => setPendingId(null));
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
            <div className="space-y-3 border-t border-slate-200 bg-white/70 p-5">
              <div className="flex items-center justify-between text-sm">
                <span className="text-slate-500">Basket subtotal</span>
                <span className="text-lg font-extrabold text-slate-900">{formatMoney(cart.totals.subtotal_amount, currency)}</span>
              </div>
              <Link
                href="/checkout"
                className="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-blue-600 via-blue-500 to-blue-700 px-4 py-3 text-sm font-bold uppercase tracking-wide text-white transition-transform duration-200 active:scale-[0.98] motion-reduce:active:scale-100"
                onClick={onClose}
              >
                Proceed to secure checkout
                <ArrowRight className="h-4 w-4" aria-hidden="true" />
              </Link>
              <Link href="/shop" className="inline-flex w-full min-h-11 items-center justify-center text-sm text-slate-800 transition-colors hover:text-brand" onClick={onClose}>
                Continue shopping
              </Link>
              <Button variant="ghost" className="w-full" disabled={pendingId !== null} onClick={() => void clearCart()}>
                Clear basket
              </Button>
            </div>
          ) : null}
        </div>
      </div>
    </div>
    </Portal>
  );
}
