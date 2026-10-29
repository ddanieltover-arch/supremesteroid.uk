import { Link, router } from '@inertiajs/react';
import { Eye, GitCompare } from 'lucide-react';
import { useEffect, useState, type MouseEvent } from 'react';
import { Badge } from '@/components/ui/Badge';
import { QuickView } from '@/components/catalogue/QuickView';
import { addCartItem } from '@/lib/cartApi';
import { formatMoney } from '@/lib/money';
import { readCompareIds, toggleCompareId } from '@/lib/compare';
import type { ProductCard as ProductCardType, ProductDetail, ProductVariant } from '@/types';

function visibleBadges(product: ProductCardType) {
  return (product.badges ?? []).filter((badge) => {
    if (badge.code === 'VERIFIED_DOCUMENTATION') {
      return false;
    }
    return ['SALE', 'FEATURED', 'NEW'].includes(badge.code);
  });
}

function LabTestedBadge() {
  return (
    <div
      className="flex h-14 w-14 flex-col items-center justify-center rounded-full bg-green-600 text-center text-[7px] font-extrabold uppercase leading-tight tracking-wide text-white shadow-md sm:h-16 sm:w-16 sm:text-[8px]"
      aria-label="Laboratory tested"
    >
      <svg className="mb-0.5 h-4 w-4 text-white sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path
          d="M10 3c.4 2.2 1.6 3.8 3.2 5.2C15 9.6 16.5 11 17 13.5c.3 1.4 0 2.8-.8 4-.6.9-1.5 1.5-2.5 1.8M14 3c-.4 2.2-1.6 3.8-3.2 5.2C9 9.6 7.5 11 7 13.5c-.3 1.4 0 2.8.8 4 .6.9 1.5 1.5 2.5 1.8"
          stroke="currentColor"
          strokeWidth="1.6"
          strokeLinecap="round"
        />
      </svg>
      Laboratory
      <br />
      Tested
    </div>
  );
}

function ImagePlaceholder({ label = 'Image unavailable' }: { label?: string }) {
  return (
    <div className="flex h-full w-full flex-col items-center justify-center gap-2 bg-slate-50 text-slate-400">
      <svg className="h-10 w-10 opacity-60" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" strokeWidth="1.5" />
        <circle cx="8.5" cy="10" r="1.5" fill="currentColor" />
        <path d="M4 16l4.5-4.5L12 15l3-3 5 5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
      </svg>
      <span className="text-xs font-medium">{label}</span>
    </div>
  );
}

function productInstantProps(product: ProductCardType) {
  return {
    component: 'Catalogue/Show',
    pageProps: (_current: Record<string, unknown>, shared: Record<string, unknown>) => ({
      ...shared,
      product: {
        id: product.id,
        name: product.name,
        slug: product.slug,
        image_url: product.image_url,
        image_alt: product.image_alt,
        pricing: product.pricing,
        stock: product.stock,
        is_purchasable: product.is_purchasable,
        badges: product.badges,
        short_description: product.short_description,
        images: product.image_url
          ? [{ id: 'preview', url: product.image_url, alt_text: product.image_alt || product.name, is_primary: true }]
          : [],
        variants: [],
        breadcrumbs: [
          { name: 'Catalogue', url: '/catalogue' },
          { name: product.name, url: `/product/${product.slug}` },
        ],
      },
      preview: false,
    }),
  };
}

function productShowLinkProps(product: ProductCardType) {
  return {
    href: `/product/${product.slug}`,
    // Hover-prefetch the Inertia payload; Catalogue/Show is eager-loaded in app.tsx
    // so Instant Visits can paint the product page on click without waiting on a chunk.
    prefetch: true,
    cacheFor: '60s',
    ...productInstantProps(product),
  };
}

export function ProductCard({ product }: { product: ProductCardType }) {
  const [adding, setAdding] = useState(false);
  const [added, setAdded] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [imageFailed, setImageFailed] = useState(false);
  const [quickViewOpen, setQuickViewOpen] = useState(false);
  const [variantPicker, setVariantPicker] = useState<ProductVariant[] | null>(null);
  const [comparing, setComparing] = useState(false);
  const currency = product.pricing?.currency ?? 'GBP';
  const showLabBadge = Boolean(product.lab_verification_reference)
    || (product.badges ?? []).some((badge) => badge.code === 'VERIFIED_DOCUMENTATION');

  useEffect(() => {
    setImageFailed(false);
  }, [product.id, product.image_url]);

  useEffect(() => {
    const sync = () => setComparing(readCompareIds().includes(product.id));
    sync();
    window.addEventListener('ss:compare', sync);
    return () => window.removeEventListener('ss:compare', sync);
  }, [product.id]);

  const postCart = async (variantId?: string) => {
    setAdding(true);
    setError(null);
    try {
      await addCartItem({
        product_id: product.id,
        product_variant_id: variantId || null,
        quantity: 1,
      });
      setAdded(true);
      window.setTimeout(() => setAdded(false), 1200);
      window.dispatchEvent(new CustomEvent('ss:open-cart'));
    } catch {
      setError('This item could not be added. Availability is confirmed on the server.');
    } finally {
      setAdding(false);
    }
  };

  const addToCart = async (event: MouseEvent) => {
    event.preventDefault();
    event.stopPropagation();
    if (!product.is_purchasable || adding) {
      return;
    }
    if (!product.has_variants) {
      void postCart();
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
        await postCart(purchasable[0].id);
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

  const openProduct = () => {
    router.visit(`/product/${product.slug}`, productInstantProps(product));
  };

  return (
    <article className="group flex h-full w-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white text-left shadow-sm transition-colors duration-200 hover:border-blue-200 hover:shadow-lg">
      <div className="relative shrink-0 overflow-hidden border-b border-slate-100 bg-white">
        <Link {...productShowLinkProps(product)} className="relative block aspect-square">
          {product.image_url && !imageFailed ? (
            <img
              src={product.image_url}
              alt={product.image_alt || product.name}
              className="h-full w-full object-contain"
              loading="lazy"
              decoding="async"
              onError={() => setImageFailed(true)}
            />
          ) : (
            <ImagePlaceholder label={product.image_url ? 'Image unavailable' : 'No image'} />
          )}
        </Link>
        <div className="absolute left-2 top-2 z-10 flex flex-col items-start gap-1">
          {showLabBadge ? <LabTestedBadge /> : null}
          {visibleBadges(product).map((badge) => (
            <Badge key={badge.code} variant={badge.code === 'SALE' ? 'gold' : badge.code === 'FEATURED' ? 'emerald' : 'amber'}>
              {badge.label}
            </Badge>
          ))}
        </div>
        <div className="absolute right-2 top-2 z-10 flex flex-col gap-2 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
          <button
            type="button"
            onClick={(event) => {
              event.preventDefault();
              event.stopPropagation();
              setQuickViewOpen(true);
            }}
            className="flex min-h-10 min-w-10 items-center justify-center rounded-full border border-slate-200 bg-white/95 text-slate-600 shadow-sm hover:text-blue-600"
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
            className={`flex min-h-10 min-w-10 items-center justify-center rounded-full border bg-white/95 shadow-sm ${comparing ? 'border-blue-500 text-blue-600' : 'border-slate-200 text-slate-600'}`}
            aria-label={comparing ? 'Remove from comparison' : 'Add to comparison'}
            aria-pressed={comparing}
          >
            <GitCompare className="h-4 w-4" />
          </button>
        </div>
      </div>

      <div
        className="flex flex-1 cursor-pointer flex-col p-3 sm:p-4"
        onClick={openProduct}
        onKeyDown={(event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            openProduct();
          }
        }}
        role="link"
        tabIndex={0}
        aria-label={`View ${product.name}`}
      >
        <h3 className="h-10 text-sm font-bold leading-5 text-slate-900 sm:h-12 sm:text-base sm:leading-6">
          <Link {...productShowLinkProps(product)} className="line-clamp-2 hover:text-blue-600" onClick={(event) => event.stopPropagation()}>
            {product.name}
          </Link>
        </h3>

        <div className="mt-2 flex h-10 items-start gap-2">
          {product.pricing ? (
            <>
              <p className="text-base font-semibold leading-6 text-blue-600">
                {formatMoney(product.pricing.effective_price, currency)}
                {product.pricing.is_on_sale ? (
                  <del className="ml-2 text-sm font-normal text-slate-400">{formatMoney(product.pricing.base_price, currency)}</del>
                ) : null}
              </p>
              {product.pricing.discount_percent ? (
                <span className="mt-0.5 shrink-0 text-xs font-semibold leading-5 text-blue-600">-{product.pricing.discount_percent}%</span>
              ) : null}
            </>
          ) : (
            <p className="text-sm leading-6 text-slate-500">Unavailable</p>
          )}
        </div>

        <p className={`mt-1 h-4 text-xs leading-4 ${product.is_purchasable ? 'text-emerald-600' : 'text-slate-500'}`}>
          {product.stock?.label ?? (product.is_purchasable ? 'In Stock' : 'Unavailable')}
        </p>
        {error ? <p className="mt-1 text-xs text-rose-600">{error}</p> : null}

        <div className="mt-auto pt-3">
          {product.is_purchasable ? (
            <button
              type="button"
              onClick={addToCart}
              disabled={adding}
              className="flex h-11 w-full items-center justify-center rounded-lg bg-brand px-3 text-sm font-semibold text-white transition-colors duration-200 hover:bg-brand-dark disabled:opacity-70"
            >
              {added ? 'Added' : adding ? 'Adding…' : product.has_variants ? 'Choose & add' : 'Add to cart'}
            </button>
          ) : (
            <p className="flex h-11 items-center justify-center rounded-lg border border-slate-200 px-3 text-center text-xs text-slate-500">Not available to purchase</p>
          )}
        </div>
      </div>

      {variantPicker ? (
        <div className="border-t border-slate-100 px-3 pb-3 sm:px-4">
          <p className="pt-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Choose option</p>
          <div className="mt-2 space-y-2">
            {variantPicker.map((variant) => (
              <button
                key={variant.id}
                type="button"
                className="flex min-h-11 w-full items-center justify-between rounded-lg border border-slate-200 px-3 text-left text-sm text-slate-800 hover:border-blue-500"
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
