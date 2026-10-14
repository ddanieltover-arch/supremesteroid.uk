import { Link } from '@inertiajs/react';
import type { HomepagePayload } from '@/types';

export function BrandShowcase({ brands }: { brands: HomepagePayload['brands'] }) {
  if (brands.length === 0) {
    return null;
  }

  return (
    <section id="brands" aria-labelledby="brands-heading" className="mt-16">
      <p className="text-xs font-semibold uppercase tracking-[0.2em] text-amber-400/80">Houses</p>
      <h2 id="brands-heading" className="mt-2 text-2xl font-semibold text-white">
        Shop by brand
      </h2>
      <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {brands.map((brand) => (
          <Link key={brand.id} href={brand.url} className="rounded-2xl border border-slate-800 bg-slate-950/70 p-5">
            <div className="flex h-16 items-center">
              {brand.logo_url ? (
                <img src={brand.logo_url} alt={`${brand.name} logo`} className="max-h-14 object-contain" loading="lazy" />
              ) : (
                <span className="text-lg font-semibold text-white">{brand.name}</span>
              )}
            </div>
            {brand.logo_url ? <p className="mt-3 text-sm font-semibold text-white">{brand.name}</p> : null}
            {brand.description ? <p className="mt-2 line-clamp-3 text-sm text-slate-400">{brand.description}</p> : null}
            <span className="mt-4 inline-flex min-h-11 items-center text-sm text-amber-300">View collection</span>
          </Link>
        ))}
      </div>
    </section>
  );
}
