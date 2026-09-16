import StorefrontLayout from '../Layouts/StorefrontLayout';
import { Link } from '@inertiajs/react';
import { ShieldCheck, Sparkles, Truck } from 'lucide-react';
import { ProductCard } from '@/components/ProductCard';
import type { ProductCard as ProductCardType, SeoProps } from '@/types';

export default function Home({
  featuredProducts = [],
  seo,
}: {
  featuredProducts?: ProductCardType[];
  seo?: SeoProps;
}) {
  return (
    <StorefrontLayout seo={seo}>
      <section className="relative overflow-hidden rounded-2xl border border-slate-800 bg-gradient-to-b from-slate-900 via-slate-900/90 to-slate-950 p-8 sm:p-12">
        <div className="relative z-10 max-w-3xl">
          <p className="mb-4 inline-flex items-center gap-2 rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 font-mono text-xs font-semibold text-amber-300">
            <Sparkles className="h-3.5 w-3.5" aria-hidden="true" />
            UK storefront
          </p>
          <h1 className="text-3xl font-extrabold leading-tight tracking-tight text-slate-100 sm:text-5xl">
            Lawful sports nutrition & <span className="text-amber-400">certified research</span> formulations
          </h1>
          <p className="mt-4 max-w-2xl text-sm leading-relaxed text-slate-300 sm:text-base">
            Server-priced catalogue with compliance-gated products, reserved inventory, and audited checkout.
          </p>
          <div className="mt-8 flex flex-wrap items-center gap-4">
            <Link
              href="/shop"
              className="inline-flex rounded-full bg-amber-400 px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-300"
            >
              Shop the catalogue
            </Link>
            <span className="inline-flex items-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-1.5 font-mono text-xs text-emerald-400">
              <ShieldCheck className="h-4 w-4" aria-hidden="true" /> UK regulated formulations
            </span>
            <span className="inline-flex items-center gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-1.5 font-mono text-xs text-amber-400">
              <Truck className="h-4 w-4" aria-hidden="true" /> Free UK delivery when eligible
            </span>
          </div>
        </div>
      </section>

      <section className="mt-12">
        <h2 className="mb-6 text-xl font-semibold text-white">Featured products</h2>
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {featuredProducts.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      </section>
    </StorefrontLayout>
  );
}
