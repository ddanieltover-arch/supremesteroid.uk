import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { ProductCard } from '@/components/ProductCard';
import type { ProductCard as ProductCardType } from '@/types';

export default function Wishlist({ products }: { products: ProductCardType[] }) {
  return (
    <StorefrontLayout seo={{ title: 'Wishlist', description: 'Saved products.', robots: 'noindex,nofollow' }}>
      <h1 className="text-3xl font-semibold text-white">Wishlist</h1>
      {products.length === 0 ? (
        <p className="mt-6 text-slate-400">No saved products yet.</p>
      ) : (
        <div className="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {products.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      )}
    </StorefrontLayout>
  );
}
