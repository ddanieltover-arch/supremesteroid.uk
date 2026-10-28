import { useEffect, useState } from 'react';
import { ProductCard } from '@/components/ProductCard';
import { ProductGridSkeleton } from '@/components/ui/Skeleton';
import { readRecentlyViewed } from '@/lib/recentlyViewed';
import type { ProductCard as ProductCardType } from '@/types';

export function RecentlyViewed({ excludeSlug }: { excludeSlug?: string }) {
  const [products, setProducts] = useState<ProductCardType[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const load = () => {
      const slugs = readRecentlyViewed().filter((slug) => slug !== excludeSlug);
      if (slugs.length === 0) {
        setProducts([]);
        return;
      }
      setLoading(true);
      void fetch(`/catalogue/cards?slugs=${encodeURIComponent(slugs.join(','))}`)
        .then((response) => (response.ok ? response.json() : { products: [] }))
        .then((payload: { products?: ProductCardType[] }) => setProducts(payload.products ?? []))
        .catch(() => setProducts([]))
        .finally(() => setLoading(false));
    };

    load();
    window.addEventListener('ss:recently-viewed', load);
    return () => window.removeEventListener('ss:recently-viewed', load);
  }, [excludeSlug]);

  if (!loading && products.length === 0) {
    return null;
  }

  return (
    <section className="mt-12" aria-label="Recently viewed">
      <h2 className="mb-6 text-xl font-semibold text-slate-900">Recently viewed</h2>
      {loading ? <ProductGridSkeleton count={4} /> : (
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
          {products.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      )}
    </section>
  );
}
