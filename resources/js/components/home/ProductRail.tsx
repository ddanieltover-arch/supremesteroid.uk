import { ProductCard } from '@/components/ProductCard';
import type { ProductCard as ProductCardType } from '@/types';

export function ProductRail({
  id,
  eyebrow,
  title,
  products,
}: {
  id?: string;
  eyebrow?: string;
  title: string;
  products: ProductCardType[];
}) {
  if (products.length === 0) {
    return null;
  }

  return (
    <section id={id} aria-labelledby={id ? `${id}-heading` : undefined} className="mt-12 first:mt-0 sm:mt-14">
      {eyebrow ? <p className="text-xs font-semibold uppercase tracking-[0.2em] text-blue-600/80">{eyebrow}</p> : null}
      <h2 id={id ? `${id}-heading` : undefined} className="mt-2 text-2xl font-semibold text-slate-900">
        {title}
      </h2>
      <div className="mt-6 flex gap-4 overflow-x-auto pb-2 md:grid md:auto-rows-fr md:grid-cols-2 md:overflow-visible lg:grid-cols-4">
        {products.map((product) => (
          <div key={product.id} className="flex h-full min-w-[16rem] md:min-w-0">
            <ProductCard product={product} />
          </div>
        ))}
      </div>
    </section>
  );
}
