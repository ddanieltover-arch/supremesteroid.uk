import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { Deferred, Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ProductCard } from '@/components/ProductCard';
import { Breadcrumbs } from '@/components/catalogue/Breadcrumbs';
import { PriceDisplay } from '@/components/catalogue/PriceDisplay';
import { ProductGallery } from '@/components/catalogue/ProductGallery';
import { ProductInfoPanels } from '@/components/catalogue/ProductInfoPanels';
import { RecentlyViewed } from '@/components/catalogue/RecentlyViewed';
import { TrustBlocks } from '@/components/catalogue/TrustBlocks';
import { useCart } from '@/hooks/useCart';
import { addCartItem, getCartOverride, optimisticAddToCart } from '@/lib/cartApi';
import { jsonFetch } from '@/lib/http';
import { rememberRecentlyViewed } from '@/lib/recentlyViewed';
import { formatMoney } from '@/lib/money';
import type { CartPayload, ProductCard as ProductCardType, ProductDetail, SeoProps } from '@/types';

function moneyTimes(amount: string | null | undefined, quantity: number): string {
  const pence = Math.round(Number(amount ?? 0) * 100) * Math.max(1, quantity);
  return (pence / 100).toFixed(2);
}

function buyNowInstantProps(product: ProductDetail, variantId: string, quantity: number) {
  const variant = product.variants?.find((item) => item.id === variantId);
  const currency = product.pricing?.currency ?? 'GBP';
  const unit = variant?.effective_price ?? product.pricing?.effective_price ?? '0.00';
  const line = moneyTimes(unit, quantity);
  const image = product.images?.find((item) => item.is_primary)?.url ?? product.image_url ?? null;
  const cart: CartPayload = {
    id: null,
    items: [
      {
        id: 'buy-now',
        product_id: product.id,
        product_name: product.name,
        product_slug: product.slug,
        product_sku: product.sku,
        variant_id: variant?.id ?? null,
        variant_name: variant?.name ?? null,
        unit_price: unit,
        quantity,
        subtotal: line,
        line_total: line,
        image_url: image,
        is_eligible: true,
        ineligibility_reasons: [],
      },
    ],
    totals: {
      subtotal_amount: line,
      discount_amount: '0.00',
      shipping_amount: '0.00',
      tax_amount: '0.00',
      grand_total_amount: line,
      items_count: quantity,
      currency,
    },
    shipping_eligibility: {
      free_shipping_threshold: '0.00',
      current_eligible_subtotal: line,
      remaining_to_threshold: '0.00',
      free_shipping_eligible: false,
      progress_percent: 0,
      currency,
    },
    is_checkout_ready: true,
  };

  return {
    component: 'Checkout/Index',
    pageProps: (_current: Record<string, unknown>, shared: Record<string, unknown>) => ({
      ...shared,
      cart,
      checkout: null,
      checkoutPreview: {
        country_code: 'GB',
        shipping_method_code: null,
        items_subtotal: line,
        discount: '0.00',
        shipping: '0.00',
        tax: '0.00',
        grand_total: line,
        currency,
      },
      addresses: [],
      countries: [
        { code: 'GB', name: 'United Kingdom' },
      ],
      checkout_step: 'details',
      checkout_steps: [
        { key: 'details', label: 'Details', state: 'current' },
        { key: 'delivery', label: 'Delivery', state: 'upcoming' },
        { key: 'payment', label: 'Payment', state: 'upcoming' },
        { key: 'confirmation', label: 'Order placed', state: 'upcoming' },
      ],
      warnings: [],
      affected_items: [],
      requires_acknowledgement: false,
      seo: {
        title: 'Checkout details',
        description: 'Secure checkout.',
        robots: 'noindex,nofollow',
      },
    }),
  };
}

type RelatedGroups = {
  related?: ProductCardType[];
  alternative?: ProductCardType[];
  complementary?: ProductCardType[];
};

export default function CatalogueShow({
  product,
  seo,
  preview = false,
  related_groups,
}: {
  product?: ProductDetail | null;
  seo?: SeoProps;
  preview?: boolean;
  related_groups?: RelatedGroups | null;
}) {
  const page = usePage<{ auth?: { user?: unknown }; wishlistProductIds?: string[]; cart?: CartPayload }>();
  const cart = useCart();
  const saved = product ? (page.props.wishlistProductIds ?? []).includes(product.id) : false;
  const isPreview = Boolean(preview || product?.is_preview);
  const [variantId, setVariantId] = useState(product?.variants?.[0]?.id ?? '');
  const [quantity, setQuantity] = useState(1);
  const [cartError, setCartError] = useState<string | null>(null);
  const notify = useForm({
    email: '',
    product_variant_id: product?.variants?.[0]?.id ?? '',
  });
  const review = useForm({
    rating: 5,
    title: '',
    body: '',
  });

  useEffect(() => {
    setVariantId(product?.variants?.[0]?.id ?? '');
    setQuantity(1);
    setCartError(null);
  }, [product?.id]);

  useEffect(() => {
    if (product?.slug && ! isPreview) {
      rememberRecentlyViewed(product.slug);
    }
  }, [product?.slug, isPreview]);

  const submitCart = (mode: 'add' | 'buy') => {
    if (!product?.id) {
      return;
    }
    setCartError(null);

    if (mode === 'buy') {
      // Establish buy-now session first, then Instant Visit checkout (avoids empty-cart bounce).
      void jsonFetch<{ redirect?: string }>('/checkout/buy-now', {
        method: 'POST',
        body: JSON.stringify({
          product_id: product.id,
          product_variant_id: variantId || null,
          quantity,
        }),
      })
        .then(() => {
          const params = new URLSearchParams({
            buy_now: '1',
            product_id: product.id,
            quantity: String(quantity),
          });
          if (variantId) {
            params.set('product_variant_id', variantId);
          }
          router.visit(`/checkout?${params.toString()}`, buyNowInstantProps(product, variantId, quantity));
        })
        .catch(() => {
          setCartError('This item could not be checked out right now.');
        });
      return;
    }

    const selected = product.variants?.find((variant) => variant.id === variantId);
    const unit = selected?.effective_price ?? product.pricing?.effective_price ?? '0.00';
    const image = product.images?.find((item) => item.is_primary)?.url ?? product.image_url ?? null;

    // Optimistic: update badge + open drawer on the same click, sync server in background.
    optimisticAddToCart(getCartOverride() ?? cart, {
      product_id: product.id,
      product_name: product.name,
      product_slug: product.slug,
      product_sku: product.sku,
      variant_id: selected?.id ?? null,
      variant_name: selected?.name ?? null,
      unit_price: unit,
      quantity,
      image_url: image,
    });
    window.dispatchEvent(new CustomEvent('ss:open-cart'));

    void addCartItem({
      product_id: product.id,
      product_variant_id: variantId || null,
      quantity,
    }).catch(() => {
      setCartError('This item could not be added. Availability is confirmed on the server.');
    });
  };

  if (! product?.id) {
    return (
      <StorefrontLayout seo={seo}>
        <div className="grid animate-pulse gap-8 lg:grid-cols-2" aria-busy="true" aria-label="Loading product">
          <div className="aspect-square rounded-2xl bg-slate-100" />
          <div className="space-y-4 pt-2">
            <div className="h-8 w-3/4 rounded-lg bg-slate-100" />
            <div className="h-5 w-1/3 rounded-lg bg-slate-100" />
            <div className="h-24 w-full rounded-lg bg-slate-100" />
            <div className="h-12 w-full rounded-lg bg-slate-100" />
          </div>
        </div>
      </StorefrontLayout>
    );
  }

  const selectedVariant = product.variants?.find((variant) => variant.id === variantId);
  const stock = selectedVariant?.stock ?? product.stock;
  const canBuy = ! isPreview && (selectedVariant ? selectedVariant.is_purchasable : product.is_purchasable);
  const useButtons = (product.variants?.length ?? 0) > 0 && (product.variants?.length ?? 0) <= 6;
  const selectedPricing = selectedVariant
    ? {
        currency: product.pricing?.currency ?? 'GBP',
        base_price: selectedVariant.compare_at_price ?? selectedVariant.effective_price,
        sale_price: selectedVariant.is_on_sale ? selectedVariant.effective_price : null,
        effective_price: selectedVariant.effective_price,
        is_on_sale: Boolean(selectedVariant.is_on_sale),
        discount_percent: product.pricing?.discount_percent ?? null,
      }
    : product.pricing;

  return (
    <StorefrontLayout seo={seo}>
      <Head>
        {(product.structured_data ?? []).map((schema, index) => (
          <script key={index} type="application/ld+json">
            {JSON.stringify(schema)}
          </script>
        ))}
      </Head>

      {isPreview ? (
        <div className="mb-6 border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
          Draft / unpublished admin preview — cart, wishlist, and inventory are disabled. Source import provenance is never shown publicly.
        </div>
      ) : null}

      <Breadcrumbs items={product.breadcrumbs} />

      <div className="grid gap-8 lg:grid-cols-2">
        <ProductGallery images={product.images ?? []} variantId={selectedVariant?.id} />

        <div>
          <div className="flex flex-wrap gap-2">
            {(product.badges ?? [])
              .filter((badge) => badge.code !== 'VERIFIED_DOCUMENTATION' || Boolean(product.lab_verification_reference))
              .map((badge) => (
                <Badge key={badge.code} variant={badge.code === 'SALE' ? 'gold' : 'neutral'}>
                  {badge.label}
                </Badge>
              ))}
          </div>
          {product.brand ? (
            <p className="mt-3 text-sm text-blue-600">
              <Link href={`/brand/${product.brand.slug}`}>{product.brand.name}</Link>
            </p>
          ) : null}
          <h1 className="mt-2 text-3xl font-semibold text-slate-900">{product.name}</h1>
          <p className="mt-2 font-mono text-xs text-slate-500">SKU {selectedVariant?.sku ?? product.sku}</p>
          {product.reviews?.count ? (
            <p className="mt-2 text-sm text-slate-500">
              {product.reviews.average?.toFixed(1)} / 5 from {product.reviews.count} approved reviews
            </p>
          ) : (
            <p className="mt-2 text-sm text-slate-500">No approved reviews yet</p>
          )}
          <div className="mt-6">
            <PriceDisplay pricing={selectedPricing} size="lg" />
          </div>
          <p className="mt-2 text-sm text-emerald-600">{stock?.label ?? 'Availability confirmed at checkout'}</p>
          <p className="mt-4 text-slate-600">{product.short_description}</p>

          {product.variants?.length ? (
            <div className="mt-6">
              <p className="mb-1.5 text-xs font-semibold uppercase tracking-wider text-slate-500">Choose option</p>
              {useButtons ? (
                <div className="flex flex-wrap gap-2">
                  {product.variants.map((variant) => (
                    <button
                      key={variant.id}
                      type="button"
                      className={`min-h-11 rounded-lg border px-3 py-2 text-sm ${variantId === variant.id ? 'border-blue-500 text-blue-600' : 'border-slate-200 text-slate-800'}`}
                      onClick={() => {
                        setVariantId(variant.id);
                        notify.setData('product_variant_id', variant.id);
                      }}
                    >
                      {variant.name}
                      <span className="mt-1 block text-xs text-slate-500">
                        {formatMoney(variant.effective_price, product.pricing?.currency)} · {variant.stock?.label}
                      </span>
                    </button>
                  ))}
                </div>
              ) : (
                <select
                  className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"
                  value={variantId}
                  onChange={(event) => {
                    setVariantId(event.target.value);
                    notify.setData('product_variant_id', event.target.value);
                  }}
                >
                  {product.variants.map((variant) => (
                    <option key={variant.id} value={variant.id}>
                      {variant.name} — {formatMoney(variant.effective_price, product.pricing?.currency)}
                    </option>
                  ))}
                </select>
              )}
            </div>
          ) : null}

          {canBuy ? (
            <div className="mt-6 space-y-3">
              <label htmlFor="qty" className="block text-xs font-semibold uppercase tracking-wider text-slate-500">
                Quantity
              </label>
              <input
                id="qty"
                type="number"
                min={1}
                max={99}
                value={quantity}
                onChange={(event) => setQuantity(Math.max(1, Number(event.target.value) || 1))}
                className="w-32 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"
              />
              <div className="flex flex-wrap gap-2">
                <Button variant="secondary" className="min-h-12" onClick={() => submitCart('add')}>
                  Add to cart
                </Button>
                <Button variant="primary" className="min-h-12" onClick={() => submitCart('buy')}>
                  Buy now
                </Button>
                {page.props.auth?.user ? (
                  <Button
                    variant="secondary"
                    className="min-h-12"
                    onClick={() => router.post('/wishlist', { product_id: product.id }, { preserveScroll: true })}
                  >
                    {saved ? 'Remove from wishlist' : 'Add to wishlist'}
                  </Button>
                ) : null}
              </div>
              {cartError ? <p className="text-sm text-rose-600">{cartError}</p> : null}
            </div>
          ) : (
            <div className="mt-6">
              {isPreview ? (
                <p className="text-sm text-blue-700">Preview only — purchasing is disabled.</p>
              ) : (
                <>
                  <p className="text-sm text-rose-600">Out of Stock</p>
                  {product.allow_stock_notification ? (
                    <form
                      className="mt-3 flex flex-col gap-2 sm:flex-row"
                      onSubmit={(event) => {
                        event.preventDefault();
                        notify.post(`/product/${product.slug}/notify`);
                      }}
                    >
                      <input
                        type="email"
                        required
                        placeholder="Email for restock notice"
                        value={notify.data.email}
                        onChange={(event) => notify.setData('email', event.target.value)}
                        className="flex-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"
                      />
                      <Button type="submit" variant="secondary" isLoading={notify.processing}>
                        Notify me
                      </Button>
                    </form>
                  ) : null}
                </>
              )}
            </div>
          )}

          {product.highlights && product.highlights.length > 0 ? (
            <ul className="mt-8 list-disc space-y-1 pl-5 text-slate-600">
              {product.highlights.map((item) => (
                <li key={item}>{item}</li>
              ))}
            </ul>
          ) : null}
        </div>
      </div>

      <ProductInfoPanels product={product} />

      {page.props.auth?.user ? (
        <section className="mt-8 max-w-xl">
          <h2 className="text-xl font-semibold text-slate-900">Write a review</h2>
          <form
            className="mt-3 space-y-3"
            onSubmit={(event) => {
              event.preventDefault();
              review.post(`/product/${product.slug}/reviews`);
            }}
          >
            <input type="number" min={1} max={5} value={review.data.rating} onChange={(event) => review.setData('rating', Number(event.target.value))} className="w-24 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2" />
            <input value={review.data.title} onChange={(event) => review.setData('title', event.target.value)} placeholder="Title" className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2" />
            <textarea value={review.data.body} onChange={(event) => review.setData('body', event.target.value)} placeholder="Your review" className="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2" />
            <Button type="submit" variant="secondary" isLoading={review.processing}>
              Submit for moderation
            </Button>
          </form>
        </section>
      ) : null}

      <Deferred data="related_groups" fallback={<></>}>
        {() => {
          const groups = related_groups ?? product.related_groups;
          const rails = [
            { title: 'Related products', items: groups?.related ?? [] },
            { title: 'Alternative products', items: groups?.alternative ?? [] },
            { title: 'Complementary products', items: groups?.complementary ?? [] },
          ];

          return (
            <>
              {rails.map((group) =>
                group.items.length > 0 ? (
                  <section key={group.title} className="mt-12">
                    <h2 className="mb-6 text-xl font-semibold text-slate-900">{group.title}</h2>
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                      {group.items.map((related) => (
                        <ProductCard key={related.id} product={related} />
                      ))}
                    </div>
                  </section>
                ) : null,
              )}

              {(!groups || Object.values(groups).every((group) => !group || group.length === 0)) &&
              product.related_products &&
              product.related_products.length > 0 ? (
                <section className="mt-12">
                  <h2 className="mb-6 text-xl font-semibold text-slate-900">Related products</h2>
                  <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    {product.related_products.map((related) => (
                      <ProductCard key={related.id} product={related} />
                    ))}
                  </div>
                </section>
              ) : null}
            </>
          );
        }}
      </Deferred>

      <RecentlyViewed excludeSlug={product.slug} />
      <TrustBlocks />
    </StorefrontLayout>
  );
}
