import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ProductCard } from '@/components/ProductCard';
import { formatMoney } from '@/lib/money';
import type { ProductDetail, SeoProps } from '@/types';

export default function CatalogueShow({ product, seo }: { product: ProductDetail; seo?: SeoProps }) {
  const [activeImage, setActiveImage] = useState(product.images?.[0]?.url ?? null);
  const form = useForm({
    product_id: product.id,
    product_variant_id: product.variants?.[0]?.id ?? '',
    quantity: 1,
  });
  const selectedVariant = product.variants?.find((variant) => variant.id === form.data.product_variant_id);
  const currency = product.pricing?.currency ?? 'GBP';
  const unitPrice = selectedVariant?.effective_price ?? product.pricing?.effective_price;
  const canBuy = selectedVariant ? selectedVariant.is_purchasable : product.is_purchasable;

  return (
    <StorefrontLayout seo={seo}>
      <div className="grid gap-8 md:grid-cols-2">
        <div>
          <div className="aspect-square overflow-hidden rounded-2xl border border-slate-800 bg-slate-950">
            {activeImage ? (
              <img src={activeImage} alt={product.name} className="h-full w-full object-contain p-6" />
            ) : (
              <div className="flex h-full items-center justify-center text-slate-600">No image</div>
            )}
          </div>
          {product.images?.length > 1 ? (
            <div className="mt-3 flex gap-2 overflow-x-auto">
              {product.images.map((image) => (
                <button
                  key={image.id}
                  type="button"
                  className="h-16 w-16 overflow-hidden rounded-lg border border-slate-800"
                  onClick={() => setActiveImage(image.url)}
                >
                  <img src={image.url} alt={image.alt_text || ''} className="h-full w-full object-cover" />
                </button>
              ))}
            </div>
          ) : null}
        </div>

        <div>
          {product.classification ? (
            <Badge variant={product.classification === 'OTC_CONSUMER' ? 'emerald' : 'amber'}>
              {product.classification.replaceAll('_', ' ')}
            </Badge>
          ) : null}
          <p className="mt-3 font-mono text-xs text-slate-500">SKU {product.sku}</p>
          <h1 className="mt-2 text-3xl font-semibold text-white">{product.name}</h1>
          <p className="mt-2 text-sm text-amber-400">
            {product.brand ? <Link href={`/brand/${product.brand.slug}`}>{product.brand.name}</Link> : null}
            {product.category ? (
              <>
                {' '}
                · <Link href={`/category/${product.category.slug}`}>{product.category.name}</Link>
              </>
            ) : null}
          </p>
          <p className="mt-6 text-3xl font-bold text-amber-300">{unitPrice ? formatMoney(unitPrice, currency) : 'Unavailable'}</p>
          {product.pricing?.is_on_sale ? (
            <p className="text-sm text-slate-500 line-through">{formatMoney(product.pricing.base_price, currency)}</p>
          ) : null}
          <p className="mt-4 text-slate-300">{product.short_description}</p>

          {product.variants?.length ? (
            <div className="mt-6">
              <label htmlFor="variant" className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-400">
                Variant
              </label>
              <select
                id="variant"
                className="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2"
                value={form.data.product_variant_id}
                onChange={(event) => form.setData('product_variant_id', event.target.value)}
              >
                {product.variants.map((variant) => (
                  <option key={variant.id} value={variant.id}>
                    {variant.name} — {formatMoney(variant.effective_price, currency)}
                  </option>
                ))}
              </select>
            </div>
          ) : null}

          <div className="mt-4 max-w-32">
            <label htmlFor="qty" className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-400">
              Quantity
            </label>
            <input
              id="qty"
              type="number"
              min={1}
              max={99}
              value={form.data.quantity}
              onChange={(event) => form.setData('quantity', Number(event.target.value))}
              className="w-full rounded-lg border border-slate-800 bg-slate-900 px-3 py-2"
            />
          </div>

          {canBuy ? (
            <Button
              variant="gold"
              className="mt-6"
              isLoading={form.processing}
              onClick={() => form.post('/cart/items')}
            >
              Add to cart
            </Button>
          ) : (
            <p className="mt-6 text-sm text-rose-300">This product is not currently available for purchase.</p>
          )}

          {product.lab_verification_reference ? (
            <p className="mt-4 font-mono text-xs text-emerald-400">Lab reference: {product.lab_verification_reference}</p>
          ) : null}
        </div>
      </div>

      {product.description ? (
        <section className="mt-12">
          <h2 className="text-xl font-semibold text-white">Description</h2>
          <p className="mt-3 max-w-3xl whitespace-pre-line text-slate-300">{product.description}</p>
        </section>
      ) : null}

      {product.specifications && product.specifications.length > 0 ? (
        <section className="mt-8">
          <h2 className="text-xl font-semibold text-white">Specifications</h2>
          <dl className="mt-3 grid max-w-2xl grid-cols-1 gap-2 sm:grid-cols-2">
            {product.specifications.map((spec) => (
              <div key={spec.name} className="rounded-lg border border-slate-800 p-3">
                <dt className="text-xs uppercase tracking-wider text-slate-500">{spec.name}</dt>
                <dd className="text-sm text-slate-200">{spec.value}</dd>
              </div>
            ))}
          </dl>
        </section>
      ) : null}

      {product.shipping_methods && product.shipping_methods.length > 0 ? (
        <section className="mt-8">
          <h2 className="text-xl font-semibold text-white">UK shipping options</h2>
          <ul className="mt-3 space-y-1 text-sm text-slate-300">
            {product.shipping_methods.map((method) => (
              <li key={method.code}>
                {method.name} — {formatMoney(method.rate_amount ?? String(method.rate ?? 0), method.currency)}
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      {product.related_products && product.related_products.length > 0 ? (
        <section className="mt-12">
          <h2 className="mb-6 text-xl font-semibold text-white">Related products</h2>
          <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {product.related_products.map((related) => (
              <ProductCard key={related.id} product={related} />
            ))}
          </div>
        </section>
      ) : null}
    </StorefrontLayout>
  );
}
