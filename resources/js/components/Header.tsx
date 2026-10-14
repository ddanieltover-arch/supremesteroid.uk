import { Link, usePage } from '@inertiajs/react';
import { Heart, Menu, Search, ShoppingBag } from 'lucide-react';
import { useState } from 'react';
import { SearchDialog } from '@/components/catalogue/SearchDialog';
import { AccountMenu } from '@/components/navigation/AccountMenu';
import { CountBadge } from '@/components/navigation/CountBadge';
import { HeaderSearch } from '@/components/navigation/HeaderSearch';
import { MegaMenu } from '@/components/navigation/MegaMenu';
import { MobileDrawer } from '@/components/navigation/MobileDrawer';
import type { CatalogueNav } from '@/types';

type HeaderProps = {
  cartCount: number;
  wishlistCount?: number;
  onOpenCart: () => void;
  compact?: boolean;
};

export function Header({ cartCount, wishlistCount = 0, onOpenCart, compact = false }: HeaderProps) {
  const page = usePage<{
    store?: { name?: string };
    catalogueNav?: CatalogueNav;
    auth?: { user?: { name: string; email?: string } | null };
  }>();
  const [mobileOpen, setMobileOpen] = useState(false);
  const [shopOpen, setShopOpen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const nav = page.props.catalogueNav;

  return (
    <header className="sticky top-0 z-40 h-16 overflow-x-clip border-b border-slate-800/80 bg-[#020617]/95">
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-2 px-3 sm:px-6 lg:px-8">
        <div className="flex min-w-0 items-center gap-3 lg:gap-6">
          <Link href="/" className="flex min-w-0 items-center gap-2 rounded-lg focus-visible:ring-2 focus-visible:ring-amber-400">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-amber-400 to-amber-600 text-lg font-extrabold text-slate-950">S</span>
            <span className="hidden min-w-0 sm:block">
              <span className="block truncate text-base font-extrabold uppercase tracking-tight text-slate-100">
                Supreme<span className="text-amber-400">Steroids</span>
              </span>
              <span className="hidden font-mono text-[10px] uppercase tracking-widest text-slate-400 md:block">supremesteroid.uk</span>
            </span>
          </Link>

          {compact ? (
            <p className="hidden text-xs text-slate-400 lg:block">Secure checkout</p>
          ) : (
            <nav className="hidden items-center gap-1 text-sm font-medium lg:flex" aria-label="Primary">
              <MegaMenu open={shopOpen} onOpenChange={setShopOpen} nav={nav} />
              <Link href="/#categories" className="rounded-lg px-3 py-2 text-slate-400 hover:text-slate-200">
                Categories
              </Link>
              <Link href="/#brands" className="rounded-lg px-3 py-2 text-slate-400 hover:text-slate-200">
                Brands
              </Link>
            </nav>
          )}
        </div>

        <div className="flex shrink-0 items-center gap-1.5 sm:gap-2">
          {compact ? (
            <Link href="/cart" className="rounded-lg px-3 py-2 text-sm text-slate-300 hover:text-amber-300">
              <span className="sm:hidden">Basket</span>
              <span className="hidden sm:inline">Return to cart</span>
            </Link>
          ) : (
            <>
              <HeaderSearch />
              <button type="button" className="rounded-lg border border-slate-800 p-2 lg:hidden" onClick={() => setSearchOpen(true)} aria-label="Search">
                <Search className="h-5 w-5" />
              </button>
              <AccountMenu user={page.props.auth?.user} />
              <Link
                href="/account/wishlist"
                className="relative hidden rounded-lg border border-slate-800 p-2 text-slate-200 hover:text-amber-300 lg:inline-flex"
                aria-label={`Wishlist, ${wishlistCount} items`}
              >
                <Heart className="h-5 w-5" />
                <CountBadge count={wishlistCount} label="Wishlist items" />
              </Link>
              <button
                type="button"
                onClick={onOpenCart}
                className="relative rounded-lg border border-slate-800 bg-slate-900 p-2 text-slate-200 hover:border-amber-500/50 hover:text-amber-400"
                aria-label={`Open cart, ${cartCount} items`}
              >
                <ShoppingBag className="h-5 w-5" />
                <CountBadge count={cartCount} label="Cart items" />
              </button>
              <button
                type="button"
                className="rounded-lg border border-slate-800 p-2 text-slate-200 lg:hidden"
                aria-expanded={mobileOpen}
                aria-label="Open menu"
                onClick={() => setMobileOpen(true)}
              >
                <Menu className="h-5 w-5" />
              </button>
            </>
          )}
        </div>
      </div>

      {compact ? null : <MobileDrawer open={mobileOpen} onClose={() => setMobileOpen(false)} nav={nav} />}
      <SearchDialog open={searchOpen} onClose={() => setSearchOpen(false)} />
    </header>
  );
}
