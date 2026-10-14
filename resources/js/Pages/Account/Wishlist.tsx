import { AccountLayout } from '@/Layouts/AccountLayout';
import { Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/Button';
import { formatMoney } from '@/lib/money';
import type { ProductCard as ProductCardType } from '@/types';

export default function Wishlist({ products }: { products: ProductCardType[] }) {
  return (
    <AccountLayout title="Wishlist" seo={{ title: 'Wishlist', description: 'Saved products.', robots: 'noindex,nofollow' }}>
      {products.length === 0 ? (
        <div className="rounded-2xl border border-slate-800 p-8 text-center">
          <p className="text-white">No saved products yet.</p>
          <Link href="/shop" className="mt-4 inline-flex min-h-11 items-center text-sm text-amber-300">
            Continue shopping
          </Link>
        </div>
      ) : (
        <ul className="space-y-4">
          {products.map((product) => (
            <li key={product.id} className="flex flex-col gap-4 rounded-2xl border border-slate-800 p-4 sm:flex-row sm:items-center">
              {product.image_url ? (
                <img src={product.image_url} alt="" className="h-24 w-24 rounded-xl object-contain bg-slate-900" />
              ) : (
                <div className="h-24 w-24 rounded-xl bg-slate-900" />
              )}
              <div className="min-w-0 flex-1">
                {product.brand ? <p className="text-xs uppercase tracking-wider text-slate-500">{product.brand}</p> : null}
                <Link href={`/product/${product.slug}`} className="font-semibold text-white hover:text-amber-300">
                  {product.name}
                </Link>
                {product.pricing ? (
                  <p className="mt-1 text-sm text-slate-200">{formatMoney(product.pricing.effective_price, product.pricing.currency)}</p>
                ) : null}
                <p className="mt-1 text-sm text-slate-400">{product.stock?.label ?? (product.is_purchasable ? 'Available' : 'Unavailable')}</p>
              </div>
              <div className="flex flex-wrap gap-2">
                <Button
                  variant="gold"
                  disabled={!product.is_purchasable}
                  onClick={() => {
                    if (!product.is_purchasable) {
                      return;
                    }
                    router.post('/cart/items', { product_id: product.id, quantity: 1 }, { preserveScroll: true });
                  }}
                >
                  {product.is_purchasable ? 'Add to cart' : 'Unavailable'}
                </Button>
                <Button variant="ghost" onClick={() => router.post('/wishlist', { product_id: product.id }, { preserveScroll: true })}>
                  Remove
                </Button>
              </div>
            </li>
          ))}
        </ul>
      )}
    </AccountLayout>
  );
}
