import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { Dialog } from '@/components/ui/Dialog';
import { Skeleton } from '@/components/ui/Skeleton';
import { PriceDisplay } from '@/components/catalogue/PriceDisplay';
import { addCartItem } from '@/lib/cartApi';
import { formatMoney } from '@/lib/money';
import type { ProductDetail } from '@/types';

export function QuickView({ slug, open, onClose }: { slug: string | null; open: boolean; onClose: () => void }) {
  const [product, setProduct] = useState<ProductDetail | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [variantId, setVariantId] = useState('');
  const [adding, setAdding] = useState(false);

  useEffect(() => {
    if (!open || !slug) {
      return;
    }
    setLoading(true);
    setError(null);
    setProduct(null);
    void fetch(`/product/${slug}/quick-view`)
      .then((response) => {
        if (!response.ok) {
          throw new Error('unavailable');
        }
        return response.json();
      })
      .then((payload: { product: ProductDetail }) => {
        setProduct(payload.product);
        setVariantId(payload.product.variants?.[0]?.id ?? '');
      })
      .catch(() => setError('This product is unavailable right now.'))
      .finally(() => setLoading(false));
  }, [open, slug]);

  const selectedVariant = product?.variants?.find((variant) => variant.id === variantId);
  const canBuy = selectedVariant ? selectedVariant.is_purchasable : Boolean(product?.is_purchasable);
  const pricing = selectedVariant
    ? {
        currency: product?.pricing?.currency ?? 'GBP',
        base_price: selectedVariant.compare_at_price ?? selectedVariant.effective_price,
        sale_price: selectedVariant.is_on_sale ? selectedVariant.effective_price : null,
        effective_price: selectedVariant.effective_price,
        is_on_sale: Boolean(selectedVariant.is_on_sale),
        discount_percent: product?.pricing?.discount_percent ?? null,
      }
    : product?.pricing;

  return (
    <Dialog open={open} onClose={onClose} title={product?.name ?? 'Quick view'}>
      {loading ? (
        <div className="grid gap-4 sm:grid-cols-2" aria-busy="true">
          <Skeleton className="aspect-square w-full" />
          <div className="space-y-3">
            <Skeleton className="h-4 w-1/3" />
            <Skeleton className="h-8 w-3/4" />
            <Skeleton className="h-6 w-1/2" />
            <Skeleton className="h-20 w-full" />
          </div>
        </div>
      ) : null}

      {error ? <p className="text-sm text-rose-600">{error}</p> : null}

      {product ? (
        <div className="grid gap-6 sm:grid-cols-2">
          <div className="aspect-square overflow-hidden rounded-xl border border-slate-200 bg-white">
            {product.images?.[0]?.url ? (
              <img
                src={product.images[0].url}
                alt={product.images[0].alt_text || product.name}
                className="h-full w-full object-contain"
              />
            ) : (
              <div className="flex h-full items-center justify-center text-sm text-slate-500">No image</div>
            )}
          </div>
          <div>
            {product.brand ? <p className="text-sm text-blue-600">{product.brand.name}</p> : null}
            <h3 className="mt-1 text-2xl font-semibold text-slate-900">{product.name}</h3>
            <div className="mt-4">
              <PriceDisplay pricing={pricing} />
            </div>
            <p className="mt-2 text-sm text-emerald-600">{selectedVariant?.stock?.label ?? product.stock?.label}</p>
            <p className="mt-4 text-sm text-slate-600">{product.short_description}</p>

            {product.variants?.length ? (
              <div className="mt-4">
                <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Choose option</p>
                <div className="flex flex-wrap gap-2">
                  {product.variants.map((variant) => (
                    <button
                      key={variant.id}
                      type="button"
                      className={`min-h-11 rounded-lg border px-3 py-2 text-left text-sm ${variantId === variant.id ? 'border-blue-500 text-blue-600' : 'border-slate-200 text-slate-800'}`}
                      onClick={() => setVariantId(variant.id)}
                    >
                      {variant.name}
                      <span className="mt-1 block text-xs text-slate-500">
                        {formatMoney(variant.effective_price, product.pricing?.currency)} · {variant.stock?.label}
                      </span>
                    </button>
                  ))}
                </div>
              </div>
            ) : null}

            <div className="mt-6 flex flex-wrap gap-2">
              <Button
                variant="gold"
                className="min-h-12"
                disabled={!canBuy}
                isLoading={adding}
                onClick={() => {
                  if (!canBuy || adding) {
                    return;
                  }
                  setAdding(true);
                  void addCartItem({
                    product_id: product.id,
                    product_variant_id: variantId || null,
                    quantity: 1,
                  })
                    .then(() => {
                      window.dispatchEvent(new CustomEvent('ss:open-cart'));
                      onClose();
                    })
                    .catch(() => {
                      setError('This item could not be added.');
                    })
                    .finally(() => setAdding(false));
                }}
              >
                {canBuy ? 'Add to cart' : 'Unavailable'}
              </Button>
              <Link href={`/product/${product.slug}`} className="inline-flex min-h-12 items-center text-sm text-blue-600">
                View full details
              </Link>
            </div>
          </div>
        </div>
      ) : null}
    </Dialog>
  );
}
