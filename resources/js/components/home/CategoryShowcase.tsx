import { Link } from '@inertiajs/react';
import type { HomepagePayload } from '@/types';

export function CategoryShowcase({ categories }: { categories: HomepagePayload['categories'] }) {
  if (categories.length === 0) {
    return null;
  }

  return (
    <section id="categories" aria-labelledby="categories-heading" className="mt-16">
      <p className="text-xs font-semibold uppercase tracking-[0.2em] text-amber-400/80">Browse</p>
      <h2 id="categories-heading" className="mt-2 text-2xl font-semibold text-white">
        Shop by category
      </h2>
      <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {categories.map((category) => (
          <Link key={category.id} href={category.url} className="group overflow-hidden rounded-2xl border border-slate-800 bg-slate-950/70">
            <div className="aspect-[16/10] bg-slate-900">
              {category.image_url ? (
                <img src={category.image_url} alt="" className="h-full w-full object-cover transition duration-200 group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100" loading="lazy" />
              ) : (
                <div className="flex h-full items-center justify-center text-xs uppercase tracking-wider text-slate-600">Collection</div>
              )}
            </div>
            <div className="p-4">
              <h3 className="text-base font-semibold text-white">{category.name}</h3>
              {category.description ? <p className="mt-2 line-clamp-2 text-sm text-slate-400">{category.description}</p> : null}
              <p className="mt-3 text-xs text-slate-400">{category.product_count} published products</p>
              <span className="mt-3 inline-flex min-h-11 items-center text-sm text-amber-300">View collection</span>
            </div>
          </Link>
        ))}
      </div>
    </section>
  );
}
