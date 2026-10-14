import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { lazy, Suspense, useState } from 'react';
import { BackToTop } from '@/components/BackToTop';
import { CartDrawer } from '@/components/CartDrawer';
import { Footer } from '@/components/Footer';
import { AnnouncementBar } from '@/components/home/AnnouncementBar';
import { Header } from '@/components/Header';
import SeoHead from '@/components/SeoHead';
import { ToastStack } from '@/components/ToastStack';
import type { CartPayload, SeoProps } from '@/types';

const CompareBar = lazy(() =>
  import('@/components/catalogue/CompareBar').then((module) => ({ default: module.CompareBar })),
);

export default function StorefrontLayout({
  children,
  seo,
  chrome = 'storefront',
}: PropsWithChildren<{ seo?: SeoProps; chrome?: 'storefront' | 'checkout' }>) {
  const page = usePage<{ cart?: CartPayload; wishlistCount?: number }>();
  const [cartOpen, setCartOpen] = useState(false);
  const cartCount = page.props.cart?.totals?.items_count ?? 0;
  const checkout = chrome === 'checkout';

  return (
    <div className="flex min-h-screen flex-col bg-[#020617] text-slate-100 antialiased">
      <SeoHead seo={seo} />
      <a href="#main-content" className="sr-only print:hidden focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-amber-400 focus:px-3 focus:py-2 focus:text-slate-950">
        Skip to content
      </a>
      {checkout ? null : <AnnouncementBar />}
      <div className="print:hidden">
        <Header cartCount={cartCount} wishlistCount={page.props.wishlistCount} onOpenCart={() => setCartOpen(true)} compact={checkout} />
      </div>
      <ToastStack />
      <main id="main-content" className="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 md:py-10 lg:px-8">
        {children}
      </main>
      {checkout ? null : <Footer />}
      {checkout ? null : (
        <Suspense fallback={null}>
          <CompareBar />
        </Suspense>
      )}
      <div className="print:hidden">
        <BackToTop />
        <CartDrawer isOpen={cartOpen} onClose={() => setCartOpen(false)} />
      </div>
    </div>
  );
}
