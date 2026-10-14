import { Link, router } from '@inertiajs/react';
import { Eye, GitCompare } from 'lucide-react';
import { useEffect, useState, type MouseEvent } from 'react';
import { Badge } from '@/components/ui/Badge';
import { QuickView } from '@/components/catalogue/QuickView';
import { formatMoney } from '@/lib/money';
import { readCompareIds, toggleCompareId } from '@/lib/compare';
import type { ProductCard as ProductCardType, ProductDetail, ProductVariant } from '@/types';

function visibleBadges(product: ProductCardType) {
  return (product.badges ?? []).filter((badge) => {
    if (badge.code === 'VERIFIED_DOCUMENTATION') {
      return Boolean(product.lab_verification_reference);
    }
    return ['SALE', 'FEATURED', 'NEW'].includes(badge.code);
  });
}

export function ProductCard({ product }: { product: ProductCardType }) {
  const [adding, setAdding] = useState(false);
  const [added, setAdded] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [quickViewOpen, setQuickViewOpen] = useState(false);
  const [variantPicker, setVariantPicker] = useState<ProductVariant[] | null>(null);
  const [comparing, setComparing] = useState(false);
  const currency = product.pricing?.currency ?? 'GBP';

  useEffect(() => {
    const sync = () => setComparing(readCompareIds().includes(product.id));
    sync();
    window.addEventListener('ss:compare', sync);
    return () => window.removeEventListener('ss:compare', sync);
  }, [product.id]);

  const postCart = (variantId?: string) => {
    setAdding(true);
    setError(null);
    router.post(
      '/cart/items',
      { product_id: product.id, product_variant_id: variantId || null, quantity: 1 },
      {
        preserveScroll: true,
        onError: () => setError('This item could not be added. Availability is confirmed on the server.'),
        onFinish: () => {
          setAdding(false);
          setAdded(true);
          window.setTimeout(() => setAdded(false), 1200);
        },
      },
    );
  };

  const addToCart = async (event: MouseEvent) => {
    event.preventDefault();
    event.stopPropagation();
    if (!product.is_purchasable || adding) {
      return;
    }
    if (!product.has_variants) {
      postCart();
      return;
    }

    setAdding(true);
    setError(null);
    try {
      const response = await fetch(`/product/${product.slug}/quick-view`);
      if (!response.ok) {
        throw new Error('unavailable');
      }
      const payload = (await response.json()) as { product: ProductDetail };
      const purchasable = (payload.product.variants ?? []).filter((variant) => variant.is_purchasable);
      if (purchasable.length === 1) {
        postCart(purchasable[0].id);
        return;
      }
      if (purchasable.length > 1) {
        setVariantPicker(purchasable);
        setAdding(false);
        return;
      }
      setError('No purchasable option is available.');
    } catch {
      setError('This item is unavailable.');
    } finally {
      setAdding(false);
    }
  };

  return (
    <article className="group flex h-full flex-col text-left">
      <div className="relative overflow-hidden rounded-2xl border border-slate-800 bg-slate-950">
        <Link href={`/product/${product.slug}`} className="relative block aspect-square">
          {product.image_url ? (
            <img
              src={product.image_url}
              alt={product.image_alt || product.name}
              className="h-full w-full object-contain transition-transform duration-200 group-hover:scale-[1.03]"
              loading="lazy"
              onError={(event) => {
                event.currentTarget.style.display = 'none';
              }}
            />
          ) : (
            <div className="flex h-full items-center justify-center text-sm text-slate-500">No image</div>
          )}
        </Link>
        <div className="absolute left-2 top-2 z-10 flex flex-col items-start gap-1">
          {visibleBadges(product).map((badge) => (
            <Badge key={badge.code} variant={badge.code === 'SALE' ? 'gold' : badge.code === 'FEATURED' ? 'emerald' : 'amber'}>
              {badge.label}
            </Badge>
          ))}
        </div>
        <div className="absolute right-2 top-2 z-10 flex flex-col gap-2">
          <button
            type="button"
            onClick={(event) => {
              event.preventDefault();
              event.stopPropagation();
              setQuickViewOpen(true);
            }}
            className="flex min-h-10 min-w-10 items-center justify-center rounded-full border border-slate-800 bg-slate-950/90 text-slate-300 hover:text-amber-300"
            aria-label={`Quick view ${product.name}`}
          >
            <Eye className="h-4 w-4" />
          </button>
          <button
            type="button"
            onClick={(event) => {
              event.preventDefault();
              event.stopPropagation();
              setComparing(toggleCompareId(product.id).includes(product.id));
            }}
            className={`flex min-h-10 min-w-10 items-center justify-center rounded-full border bg-slate-950/90 ${comparing ? 'border-amber-400 text-amber-300' : 'border-slate-800 text-slate-300'}`}
            aria-label={comparing ? 'Remove from comparison' : 'Add to comparison'}
            aria-pressed={comparing}
          >
            <GitCompare className="h-4 w-4" />
          </button>
        </div>
      </div>

      <div className="mt-3 flex flex-1 flex-col">
        {product.brand ? (
          <p className="text-[11px] font-semibold uppercase tracking-wider text-amber-400/90">{product.brand}</p>
        ) : null}
        <h3 className="mt-1 text-sm font-semibold leading-snug text-white sm:text-base">
          <Link href={`/product/${product.slug}`} className="hover:text-amber-300">
            {product.name}
          </Link>
        </h3>
        <p className="mt-1 text-xs text-slate-500">
          {[product.category, product.has_variants ? `${product.variant_count} options` : null].filter(Boolean).join(' · ')}
        </p>
        <div className="mt-2">
          {product.pricing ? (
            <p className="text-base font-semibold text-slate-100">
              {formatMoney(product.pricing.effective_price, currency)}
              {product.pricing.is_on_sale ? (
                <del className="ml-2 text-sm font-normal text-slate-500">{formatMoney(product.pricing.base_price, currency)}</del>
              ) : null}
            </p>
          ) : (
            <p className="text-sm text-slate-500">Unavailable</p>
          )}
          {product.pricing?.discount_percent ? <p className="text-xs font-semibold text-amber-400">-{product.pricing.discount_percent}%</p> : null}
        </div>
        <p className={`mt-1 text-xs ${product.is_purchasable ? 'text-emerald-400' : 'text-slate-500'}`}>
          {product.stock?.label ?? (product.is_purchasable ? 'In Stock' : 'Unavailable')}
        </p>
        {error ? <p className="mt-1 text-xs text-rose-300">{error}</p> : null}

        <div className="mt-3">
          {product.is_purchasable ? (
            <button
              type="button"
              onClick={addToCart}
              disabled={adding}
              className="min-h-11 w-full rounded-lg bg-amber-400 px-3 text-sm font-semibold text-slate-950 hover:bg-amber-300 disabled:opacity-70"
            >
              {added ? 'Added' : adding ? 'Adding…' : product.has_variants ? 'Choose & add' : 'Add to cart'}
            </button>
          ) : (
            <p className="min-h-11 rounded-lg border border-slate-800 px-3 py-2 text-center text-xs text-slate-500">Not available to purchase</p>
          )}
        </div>
      </div>

      {variantPicker ? (
        <div className="mt-3 rounded-xl border border-slate-800 p-3">
          <p className="text-xs font-semibold uppercase tracking-wider text-slate-400">Choose option</p>
          <div className="mt-2 space-y-2">
            {variantPicker.map((variant) => (
              <button
                key={variant.id}
                type="button"
                className="flex min-h-11 w-full items-center justify-between rounded-lg border border-slate-800 px-3 text-left text-sm text-slate-200 hover:border-amber-400"
                onClick={() => {
                  setVariantPicker(null);
                  postCart(variant.id);
                }}
              >
                <span>{variant.name}</span>
                <span className="text-xs text-slate-500">
                  {formatMoney(variant.effective_price, currency)} · {variant.stock?.label}
                </span>
              </button>
            ))}
          </div>
        </div>
      ) : null}

      <QuickView slug={product.slug} open={quickViewOpen} onClose={() => setQuickViewOpen(false)} />
    </article>
  );
}
