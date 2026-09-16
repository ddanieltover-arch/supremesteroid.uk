import { Link, useForm, usePage } from '@inertiajs/react';
import { Layers, Menu, Search, ShoppingBag, Truck, X } from 'lucide-react';
import { useState } from 'react';
import type { CartPayload } from '@/types';
import { formatMoney } from '@/lib/money';

type HeaderProps = {
  cartCount: number;
  onOpenCart: () => void;
};

export function Header({ cartCount, onOpenCart }: HeaderProps) {
  const page = usePage<{
    auth?: { user?: { name: string } | null };
    store?: { name?: string };
    shippingOverview?: { free_shipping_threshold: string; currency: string };
    cart?: CartPayload;
  }>();
  const [mobileOpen, setMobileOpen] = useState(false);
  const searchForm = useForm({ search: '' });
  const threshold = page.props.shippingOverview?.free_shipping_threshold ?? '300.00';
  const currency = page.props.shippingOverview?.currency ?? 'GBP';
  const user = page.props.auth?.user;
  const current = page.url;

  const nav = [
    { href: '/shop', label: 'Shop' },
    { href: '/design-system', label: 'Design System', icon: true },
  ];

  return (
    <header className="sticky top-0 z-40 border-b border-slate-800/80 bg-slate-950/90 backdrop-blur-md">
      <div className="flex items-center justify-center gap-3 bg-gradient-to-r from-amber-600 via-amber-500 to-amber-700 px-4 py-1.5 text-center text-xs font-semibold text-slate-950">
        <span className="inline-flex items-center gap-1.5">
          <Truck className="h-3.5 w-3.5" aria-hidden="true" />
          Free UK Delivery on orders over {formatMoney(threshold, currency)}
        </span>
      </div>

      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <div className="flex items-center gap-6">
          <Link href="/" className="flex items-center gap-2.5 rounded-lg focus-visible:ring-2 focus-visible:ring-amber-400">
            <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-amber-400 to-amber-600 text-lg font-extrabold text-slate-950">
              S
            </span>
            <span>
              <span className="block text-base font-extrabold uppercase tracking-tight text-slate-100">
                Supreme<span className="text-amber-400">Steroids</span>
              </span>
              <span className="block font-mono text-[10px] uppercase tracking-widest text-slate-400">supremesteroid.uk</span>
            </span>
          </Link>

          <nav className="hidden items-center gap-1 text-sm font-medium md:flex" aria-label="Primary">
            {nav.map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className={`rounded-lg px-3 py-1.5 ${
                  current.startsWith(item.href)
                    ? 'border border-amber-500/30 bg-slate-900 font-semibold text-amber-400'
                    : 'text-slate-400 hover:text-slate-200'
                }`}
              >
                {item.icon ? (
                  <span className="inline-flex items-center gap-1.5">
                    <Layers className="h-3.5 w-3.5" aria-hidden="true" />
                    {item.label}
                  </span>
                ) : (
                  item.label
                )}
              </Link>
            ))}
          </nav>
        </div>

        <div className="flex items-center gap-2 sm:gap-3">
          <form
            className="relative hidden lg:block"
            onSubmit={(event) => {
              event.preventDefault();
              searchForm.get('/search', { preserveState: true });
            }}
          >
            <label className="sr-only" htmlFor="header-search">
              Search products
            </label>
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" aria-hidden="true" />
            <input
              id="header-search"
              value={searchForm.data.search}
              onChange={(event) => searchForm.setData('search', event.target.value)}
              placeholder="Search catalogue"
              className="w-56 rounded-lg border border-slate-800 bg-slate-900 py-2 pl-9 pr-3 text-sm text-slate-100"
            />
          </form>

          {user ? (
            <Link href="/account" className="hidden text-sm text-slate-300 hover:text-amber-300 sm:inline">
              {user.name}
            </Link>
          ) : (
            <Link href="/login" className="hidden text-sm text-slate-300 hover:text-amber-300 sm:inline">
              Account
            </Link>
          )}

          <button
            type="button"
            onClick={onOpenCart}
            className="relative rounded-lg border border-slate-800 bg-slate-900 p-2 text-slate-200 hover:border-amber-500/50 hover:text-amber-400"
            aria-label={`Open cart, ${cartCount} items`}
          >
            <ShoppingBag className="h-5 w-5" aria-hidden="true" />
            {cartCount > 0 ? (
              <span className="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-amber-500 text-xs font-bold text-slate-950">
                {cartCount}
              </span>
            ) : null}
          </button>

          <button
            type="button"
            className="rounded-lg border border-slate-800 p-2 text-slate-200 md:hidden"
            aria-expanded={mobileOpen}
            aria-controls="mobile-nav"
            onClick={() => setMobileOpen((open) => !open)}
          >
            {mobileOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
            <span className="sr-only">Menu</span>
          </button>
        </div>
      </div>

      {mobileOpen ? (
        <nav id="mobile-nav" className="space-y-2 border-t border-slate-800 px-4 py-4 md:hidden" aria-label="Mobile">
          <Link href="/shop" className="block rounded-lg px-3 py-2 text-slate-200" onClick={() => setMobileOpen(false)}>
            Shop
          </Link>
          <Link href="/search" className="block rounded-lg px-3 py-2 text-slate-200" onClick={() => setMobileOpen(false)}>
            Search
          </Link>
          <Link href={user ? '/account' : '/login'} className="block rounded-lg px-3 py-2 text-slate-200" onClick={() => setMobileOpen(false)}>
            Account
          </Link>
          <button
            type="button"
            className="block w-full rounded-lg px-3 py-2 text-left text-slate-200"
            onClick={() => {
              setMobileOpen(false);
              onOpenCart();
            }}
          >
            Cart
          </button>
        </nav>
      ) : null}
    </header>
  );
}
