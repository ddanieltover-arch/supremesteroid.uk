import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { lazy, Suspense, useEffect, useState } from 'react';
import { BackToTop } from '@/components/BackToTop';
import { CartDrawer } from '@/components/CartDrawer';
import { Footer } from '@/components/Footer';
import { AnnouncementBar } from '@/components/home/AnnouncementBar';
import { Header } from '@/components/Header';
import { MobileBottomNav } from '@/components/navigation/MobileBottomNav';
import SeoHead from '@/components/SeoHead';
import { ToastStack } from '@/components/ToastStack';
import { useCart } from '@/hooks/useCart';
import type { SeoProps } from '@/types';
import clsx from 'clsx';

const CompareBar = lazy(() =>
  import('@/components/catalogue/CompareBar').then((module) => ({ default: module.CompareBar })),
);

export default function StorefrontLayout({
  children,
  seo,
  chrome = 'storefront',
}: PropsWithChildren<{ seo?: SeoProps; chrome?: 'storefront' | 'checkout' }>) {
  const page = usePage<{ wishlistCount?: number }>();
  const cart = useCart();
  const [cartOpen, setCartOpen] = useState(false);
  const cartCount = cart.totals?.items_count ?? 0;
  const checkout = chrome === 'checkout';
  const openCart = () => setCartOpen(true);

  useEffect(() => {
    const onOpen = () => setCartOpen(true);
    window.addEventListener('ss:open-cart', onOpen);
    return () => window.removeEventListener('ss:open-cart', onOpen);
  }, []);

  return (
    <div
      className={clsx(
        'flex min-h-screen flex-col bg-[#f9f9f9] text-slate-900 antialiased',
        checkout ? null : 'pb-[calc(3.5rem+env(safe-area-inset-bottom))] lg:pb-0',
      )}
    >
      <SeoHead seo={seo} />
      <a href="#main-content" className="sr-only print:hidden focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-blue-600 focus:px-3 focus:py-2 focus:text-white">
        Skip to content
      </a>
      <div className="sticky top-0 z-40 print:hidden">
        {checkout ? null : <AnnouncementBar />}
        <Header cartCount={cartCount} wishlistCount={page.props.wishlistCount ?? 0} onOpenCart={openCart} compact={checkout} />
      </div>
      <ToastStack />
      <div className="flex min-h-0 flex-1 flex-col overflow-x-clip">
        <main id="main-content" className="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 md:py-10 lg:px-8">
          {children}
        </main>
        {checkout ? null : <Footer />}
        {checkout ? null : (
          <Suspense fallback={null}>
            <CompareBar />
          </Suspense>
        )}
      </div>
      <div className="print:hidden">
        <BackToTop />
        {checkout ? null : (
          <MobileBottomNav
            cartCount={cartCount}
            wishlistCount={page.props.wishlistCount}
            onOpenCart={openCart}
          />
        )}
        <CartDrawer isOpen={cartOpen} onClose={() => setCartOpen(false)} />
      </div>
    </div>
  );
}
