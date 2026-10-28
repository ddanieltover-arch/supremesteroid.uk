import { Link, usePage } from '@inertiajs/react';
import { Heart, Home, ShoppingBag, Store, UserRound } from 'lucide-react';
import clsx from 'clsx';
import { CountBadge } from '@/components/navigation/CountBadge';

type MobileBottomNavProps = {
  cartCount: number;
  wishlistCount?: number;
  onOpenCart: () => void;
};

function pathFromUrl(url: string): string {
  return url.split('?')[0]?.split('#')[0] || '/';
}

function isActivePath(current: string, href: string): boolean {
  if (href === '/') {
    return current === '/';
  }
  return current === href || current.startsWith(`${href}/`);
}

export function MobileBottomNav({ cartCount, wishlistCount = 0, onOpenCart }: MobileBottomNavProps) {
  const page = usePage<{ auth?: { user?: { name: string } | null } }>();
  const current = pathFromUrl(page.url);
  const accountHref = page.props.auth?.user ? '/account' : '/login';
  const accountActive = current.startsWith('/account') || current === '/login' || current === '/register';

  const itemClass = (active: boolean) =>
    clsx(
      'relative flex min-h-12 flex-1 flex-col items-center justify-center gap-0.5 px-1 text-[10px] font-semibold uppercase tracking-wide transition-colors duration-200',
      'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand',
      active ? 'text-brand' : 'text-slate-500 hover:text-slate-800',
    );

  return (
    <nav
      aria-label="Mobile primary"
      className="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_20px_rgba(15,23,42,0.06)] backdrop-blur supports-[backdrop-filter]:bg-white/90 lg:hidden print:hidden"
    >
      <div className="mx-auto flex h-14 max-w-7xl items-stretch">
        <Link href="/" className={itemClass(isActivePath(current, '/'))} aria-current={isActivePath(current, '/') ? 'page' : undefined}>
          <Home className="h-5 w-5" aria-hidden="true" />
          <span>Home</span>
        </Link>

        <Link href="/shop" className={itemClass(isActivePath(current, '/shop'))} aria-current={isActivePath(current, '/shop') ? 'page' : undefined}>
          <Store className="h-5 w-5" aria-hidden="true" />
          <span>Shop</span>
        </Link>

        <Link
          href="/account/wishlist"
          className={itemClass(isActivePath(current, '/account/wishlist'))}
          aria-label={`Wishlist, ${wishlistCount} items`}
          aria-current={isActivePath(current, '/account/wishlist') ? 'page' : undefined}
        >
          <span className="relative inline-flex">
            <Heart className="h-5 w-5" aria-hidden="true" />
            <CountBadge count={wishlistCount} label="Wishlist items" />
          </span>
          <span>Wishlist</span>
        </Link>

        <button
          type="button"
          onClick={onOpenCart}
          className={itemClass(isActivePath(current, '/cart'))}
          aria-label={`Open cart, ${cartCount} items`}
        >
          <span className="relative inline-flex">
            <ShoppingBag className="h-5 w-5" aria-hidden="true" />
            <CountBadge count={cartCount} label="Cart items" />
          </span>
          <span>Cart</span>
        </button>

        <Link href={accountHref} className={itemClass(accountActive)} aria-current={accountActive ? 'page' : undefined}>
          <UserRound className="h-5 w-5" aria-hidden="true" />
          <span>Account</span>
        </Link>
      </div>
    </nav>
  );
}
