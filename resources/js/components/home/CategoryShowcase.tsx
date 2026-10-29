import { Link } from '@inertiajs/react';
import { useState } from 'react';
import type { HomepagePayload } from '@/types';

function CategoryCard({ category }: { category: HomepagePayload['categories'][number] }) {
  const [imageFailed, setImageFailed] = useState(false);
  const showImage = Boolean(category.image_url) && !imageFailed;

  return (
    <Link
      href={category.url}
      className="group overflow-hidden rounded-2xl border border-slate-200 bg-white/70 transition-colors duration-200 hover:border-blue-200 hover:shadow-lg"
    >
      <div className="aspect-[16/10] bg-slate-50">
        {showImage ? (
          <img
            src={category.image_url!}
            alt=""
            className="h-full w-full object-contain p-4"
            loading="lazy"
            onError={() => setImageFailed(true)}
          />
        ) : (
          <div className="flex h-full items-center justify-center text-xs uppercase tracking-wider text-slate-600">Collection</div>
        )}
      </div>
      <div className="p-4">
        <h3 className="text-base font-semibold text-slate-900 transition-colors group-hover:text-brand">{category.name}</h3>
        {category.description ? <p className="mt-2 line-clamp-2 text-sm text-slate-500">{category.description}</p> : null}
        <p className="mt-3 text-xs text-slate-500">{category.product_count} published products</p>
        <span className="mt-3 inline-flex min-h-11 items-center text-sm text-brand">View collection</span>
      </div>
    </Link>
  );
}

export function CategoryShowcase({ categories }: { categories: HomepagePayload['categories'] }) {
  if (categories.length === 0) {
    return null;
  }

  return (
    <section id="categories" aria-labelledby="categories-heading" className="mt-16">
      <p className="text-xs font-semibold uppercase tracking-[0.2em] text-blue-600/80">Browse</p>
      <h2 id="categories-heading" className="mt-2 text-2xl font-semibold text-slate-900">
        Shop by category
      </h2>
      <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {categories.map((category) => (
          <CategoryCard key={category.id} category={category} />
        ))}
      </div>
    </section>
  );
}
