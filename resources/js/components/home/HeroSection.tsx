import { Link } from '@inertiajs/react';
import type { HomepagePayload } from '@/types';
import { formatMoney } from '@/lib/money';

export function HeroSection({ hero }: { hero: HomepagePayload['hero'] }) {
  const featured = hero.products[0];
  const supporting = hero.products.slice(1, 3);

  return (
    <section className="relative overflow-hidden rounded-3xl border border-slate-800 bg-gradient-to-br from-slate-950 via-[#07111f] to-[#020617] px-6 py-10 sm:px-10 sm:py-14">
      <div className="pointer-events-none absolute -right-16 top-0 h-64 w-64 rounded-full bg-amber-400/5 blur-3xl" aria-hidden="true" />
      <div className="pointer-events-none absolute bottom-0 left-10 h-40 w-40 rotate-12 border border-slate-800/80" aria-hidden="true" />

      <div className="relative grid items-center gap-10 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.24em] text-amber-400/90">{hero.eyebrow}</p>
          <h1 className="mt-4 max-w-xl text-4xl font-semibold leading-tight text-white sm:text-5xl">{hero.headline}</h1>
          <p className="mt-5 max-w-xl text-sm leading-relaxed text-slate-400 sm:text-base">{hero.description}</p>
          <div className="mt-8 flex flex-wrap gap-3">
            <Link href={hero.primary_cta.url} className="inline-flex min-h-12 items-center rounded-full bg-amber-400 px-6 text-sm font-semibold text-slate-950 hover:bg-amber-300">
              {hero.primary_cta.label}
            </Link>
            <Link href={hero.secondary_cta.url} className="inline-flex min-h-12 items-center rounded-full border border-slate-700 px-6 text-sm font-semibold text-slate-100 hover:border-amber-400 hover:text-amber-300">
              {hero.secondary_cta.label}
            </Link>
          </div>
        </div>

        <div className="relative min-h-72">
          {featured ? (
            <Link href={`/product/${featured.slug}`} className="block overflow-hidden rounded-3xl border border-slate-800 bg-slate-950/80 p-6">
              <div className="aspect-square overflow-hidden rounded-2xl bg-slate-900/80">
                {featured.image_url ? (
                  <img src={featured.image_url} alt={featured.image_alt || featured.name} className="h-full w-full object-contain" />
                ) : (
                  <div className="flex h-full items-center justify-center text-sm text-slate-500">No image</div>
                )}
              </div>
              <p className="mt-4 text-xs uppercase tracking-wider text-amber-400">{featured.brand}</p>
              <p className="mt-1 text-lg font-semibold text-white">{featured.name}</p>
              {featured.pricing ? (
                <p className="mt-2 text-sm text-slate-300">{formatMoney(featured.pricing.effective_price, featured.pricing.currency)}</p>
              ) : null}
            </Link>
          ) : null}

          {supporting.map((product, index) => (
            <Link
              key={product.id}
              href={`/product/${product.slug}`}
              className={`absolute hidden w-44 rounded-2xl border border-slate-800 bg-slate-950/90 p-3 shadow-2xl lg:block ${index === 0 ? '-left-8 top-8' : '-right-4 bottom-6'}`}
            >
              <div className="aspect-square overflow-hidden rounded-xl bg-slate-900">
                {product.image_url ? <img src={product.image_url} alt={product.image_alt || product.name} className="h-full w-full object-contain" /> : null}
              </div>
              <p className="mt-2 truncate text-xs text-white">{product.name}</p>
            </Link>
          ))}
        </div>
      </div>
    </section>
  );
}
