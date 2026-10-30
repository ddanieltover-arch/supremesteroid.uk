import { Link, usePage } from '@inertiajs/react';
import { Heart, Menu, Search, ShoppingBag } from 'lucide-react';
import { useState } from 'react';
import { SearchDialog } from '@/components/catalogue/SearchDialog';
import { AccountMenu } from '@/components/navigation/AccountMenu';
import { CountBadge } from '@/components/navigation/CountBadge';
import { HeaderSearch } from '@/components/navigation/HeaderSearch';
import { MobileDrawer } from '@/components/navigation/MobileDrawer';
import { jsonFetch } from '@/lib/http';
import type { CatalogueNav } from '@/types';

type HeaderProps = {
  cartCount: number;
  wishlistCount?: number;
  onOpenCart: () => void;
  compact?: boolean;
};

const navLinkClass =
  'rounded-lg px-2.5 py-2 text-slate-800 transition-colors duration-200 hover:bg-slate-100 hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand';

const iconButtonClass =
  'rounded-lg border border-slate-200 p-2 text-slate-800 transition-all duration-200 hover:border-brand/40 hover:bg-slate-50 hover:text-brand focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand';

let catalogueNavCache: CatalogueNav | null = null;

export function Header({ cartCount, wishlistCount = 0, onOpenCart, compact = false }: HeaderProps) {
  const page = usePage<{
    store?: { name?: string; logo_url?: string };
    auth?: { user?: { name: string; email?: string } | null };
  }>();
  const [mobileOpen, setMobileOpen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [nav, setNav] = useState<CatalogueNav | undefined>(() => catalogueNavCache ?? undefined);
  const logoUrl = page.props.store?.logo_url ?? '/assets/branding/supreme-steroids-logo.jpg';
  const storeName = page.props.store?.name ?? 'Supreme Steroids';

  const openMobileMenu = () => {
    setMobileOpen(true);
    if (catalogueNavCache) {
      setNav(catalogueNavCache);
      return;
    }
    void jsonFetch<CatalogueNav>('/catalogue/nav')
      .then((payload) => {
        catalogueNavCache = payload;
        setNav(payload);
      })
      .catch(() => {
        // Drawer still opens with Shop / Account links; categories load on retry.
      });
  };

  return (
    <header className="h-16 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-white/90">
      <div className="mx-auto flex h-16 max-w-7xl items-center gap-2 px-3 sm:px-6 lg:px-8">
        <div className="flex min-w-0 shrink-0 items-center gap-3 lg:gap-8">
          <Link href="/" className="flex min-w-0 items-center rounded-lg transition-opacity duration-200 hover:opacity-90 focus-visible:ring-2 focus-visible:ring-brand">
            <img src={logoUrl} alt={storeName} className="h-11 w-auto max-w-[140px] object-contain sm:max-w-[180px]" />
          </Link>

          {compact ? (
            <p className="hidden text-xs text-slate-500 lg:block">Secure checkout</p>
          ) : (
            <nav className="hidden items-center gap-0.5 text-xs font-bold uppercase tracking-wider lg:flex" aria-label="Primary">
              <Link href="/" className={navLinkClass}>
                Home
              </Link>
              <Link href="/shop" className={navLinkClass}>
                Shop
              </Link>
              <Link href="/blog" className={navLinkClass}>
                Blog
              </Link>
              <Link href="/faq" className={navLinkClass}>
                FAQ
              </Link>
              <Link href="/about-us" className={navLinkClass}>
                About Us
              </Link>
              <Link href="/contact-us" className={navLinkClass}>
                Contact Us
              </Link>
            </nav>
          )}
        </div>

        {compact ? (
          <div className="ml-auto flex shrink-0 items-center">
            <Link href="/cart" className="rounded-lg px-3 py-2 text-sm text-slate-700 transition-colors duration-200 hover:bg-slate-100 hover:text-brand">
              <span className="sm:hidden">Basket</span>
              <span className="hidden sm:inline">Return to cart</span>
            </Link>
          </div>
        ) : (
          <>
            <button
              type="button"
              className="flex min-w-0 flex-1 items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-left text-sm text-slate-500 transition-colors duration-200 hover:border-brand/40 hover:bg-white hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand lg:hidden"
              onClick={() => setSearchOpen(true)}
              aria-label="Search catalogue"
            >
              <Search className="h-4 w-4 shrink-0 text-slate-500" aria-hidden="true" />
              <span className="truncate">Search catalogue</span>
            </button>

            <div className="ml-auto hidden items-center gap-1.5 sm:gap-2 lg:flex">
              <HeaderSearch />
              <AccountMenu user={page.props.auth?.user} />
              <Link
                href="/account/wishlist"
                className={`relative inline-flex ${iconButtonClass}`}
                aria-label={`Wishlist, ${wishlistCount} items`}
              >
                <Heart className="h-5 w-5" />
                <CountBadge count={wishlistCount} label="Wishlist items" />
              </Link>
              <button
                type="button"
                onClick={onOpenCart}
                className={`relative inline-flex border-slate-200 bg-slate-50 ${iconButtonClass}`}
                aria-label={`Open cart, ${cartCount} items`}
              >
                <ShoppingBag className="h-5 w-5" />
                <CountBadge count={cartCount} label="Cart items" />
              </button>
            </div>

            <button
              type="button"
              className={`${iconButtonClass} shrink-0 lg:hidden`}
              aria-expanded={mobileOpen}
              aria-label="Open menu"
              onClick={openMobileMenu}
            >
              <Menu className="h-5 w-5" />
            </button>
          </>
        )}
      </div>

      {compact ? null : <MobileDrawer open={mobileOpen} onClose={() => setMobileOpen(false)} nav={nav} />}
      <SearchDialog open={searchOpen} onClose={() => setSearchOpen(false)} />
    </header>
  );
}
