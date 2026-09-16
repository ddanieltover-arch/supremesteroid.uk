import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import { CartDrawer } from '@/components/CartDrawer';
import { FlashBanner } from '@/components/FlashBanner';
import { Footer } from '@/components/Footer';
import { Header } from '@/components/Header';
import SeoHead from '@/components/SeoHead';
import type { CartPayload, SeoProps } from '@/types';

export default function StorefrontLayout({ children, seo }: PropsWithChildren<{ seo?: SeoProps }>) {
  const page = usePage<{ cart?: CartPayload }>();
  const [cartOpen, setCartOpen] = useState(false);
  const cartCount = page.props.cart?.totals?.items_count ?? 0;

  return (
    <div className="flex min-h-screen flex-col bg-[#020617] text-slate-100 antialiased">
      <SeoHead seo={seo} />
      <a href="#main-content" className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-amber-400 focus:px-3 focus:py-2 focus:text-slate-950">
        Skip to content
      </a>
      <Header cartCount={cartCount} onOpenCart={() => setCartOpen(true)} />
      <FlashBanner />
      <main id="main-content" className="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6 md:py-10 lg:px-8">
        {children}
      </main>
      <Footer />
      <CartDrawer isOpen={cartOpen} onClose={() => setCartOpen(false)} />
    </div>
  );
}
