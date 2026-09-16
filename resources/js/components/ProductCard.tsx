import { Link, router, usePage } from '@inertiajs/react';
import { Check, Heart, Plus, ShieldCheck } from 'lucide-react';
import { useState, type MouseEvent } from 'react';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { formatMoney } from '@/lib/money';
import type { ProductCard as ProductCardType } from '@/types';

export function ProductCard({ product }: { product: ProductCardType }) {
  const page = usePage<{ wishlistProductIds?: string[]; auth?: { user?: unknown } }>();
  const [adding, setAdding] = useState(false);
  const [added, setAdded] = useState(false);
  const wished = (page.props.wishlistProductIds ?? []).includes(product.id);
  const currency = product.pricing?.currency ?? 'GBP';

  const addToCart = (event: MouseEvent) => {
    event.preventDefault();
    event.stopPropagation();
    if (!product.is_purchasable) {
      return;
    }
    setAdding(true);
    router.post(
      '/cart/items',
      { product_id: product.id, quantity: 1 },
      {
        preserveScroll: true,
        onFinish: () => {
          setAdding(false);
          setAdded(true);
          window.setTimeout(() => setAdded(false), 1200);
        },
      },
    );
  };

  const toggleWishlist = (event: MouseEvent) => {
    event.preventDefault();
    event.stopPropagation();
    if (!page.props.auth?.user) {
      router.get('/login');
      return;
    }
    router.post('/wishlist', { product_id: product.id }, { preserveScroll: true });
  };

  const classification = product.classification === 'OTC_CONSUMER' ? 'emerald' : product.classification === 'RESEARCH_USE' ? 'amber' : 'neutral';

  return (
    <article className="group flex flex-col overflow-hidden rounded-xl border border-slate-800/80 bg-slate-900/60">
      <Link href={`/product/${product.slug}`} className="relative block aspect-square overflow-hidden bg-slate-950">
        {product.image_url ? (
          <img
            src={product.image_url}
            alt={product.image_alt || product.name}
            className="h-full w-full object-cover opacity-90 transition duration-300 group-hover:scale-105 group-hover:opacity-100"
            loading="lazy"
          />
        ) : (
          <div className="flex h-full items-center justify-center text-slate-600">No image</div>
        )}
        <div className="absolute left-2.5 top-2.5 flex flex-col items-start gap-1">
          {product.classification ? <Badge variant={classification}>{product.classification.replaceAll('_', ' ')}</Badge> : null}
          {product.is_featured ? <Badge variant="gold">Featured</Badge> : null}
        </div>
        {product.lab_verification_reference ? (
          <div className="absolute bottom-2.5 right-2.5 flex items-center gap-1.5 rounded-md border border-slate-800 bg-slate-950/85 px-2 py-1 font-mono text-[11px] text-emerald-400">
            <ShieldCheck className="h-3.5 w-3.5" aria-hidden="true" />
            <span>Lab verified</span>
          </div>
        ) : null}
      </Link>

      <div className="flex flex-1 flex-col justify-between p-4">
        <div>
          {product.brand ? <p className="mb-1 text-[11px] font-semibold uppercase tracking-wider text-amber-400/90">{product.brand}</p> : null}
          <h3 className="text-sm font-semibold leading-snug text-slate-100">
            <Link href={`/product/${product.slug}`} className="hover:text-amber-300">
              {product.name}
            </Link>
          </h3>
          {product.short_description ? <p className="mt-1.5 line-clamp-2 text-xs leading-relaxed text-slate-400">{product.short_description}</p> : null}
        </div>
        <div className="mt-4 flex items-center justify-between border-t border-slate-800/80 pt-3">
          <div>
            <p className="text-lg font-bold text-slate-100">
              {product.pricing ? formatMoney(product.pricing.effective_price, currency) : 'Unavailable'}
            </p>
            {product.pricing?.is_on_sale ? (
              <p className="text-xs text-slate-500 line-through">{formatMoney(product.pricing.base_price, currency)}</p>
            ) : null}
          </div>
          <div className="flex items-center gap-1.5">
            <button
              type="button"
              onClick={toggleWishlist}
              className="rounded-lg border border-slate-800 p-2 text-slate-400 hover:text-amber-300"
              aria-label={wished ? 'Remove from wishlist' : 'Add to wishlist'}
              aria-pressed={wished}
            >
              <Heart className={`h-4 w-4 ${wished ? 'fill-amber-400 text-amber-400' : ''}`} />
            </button>
            <Button size="sm" variant={added ? 'primary' : 'secondary'} onClick={addToCart} isLoading={adding} disabled={!product.is_purchasable}>
              {added ? (
                <>
                  <Check className="h-3.5 w-3.5 text-emerald-600" /> Added
                </>
              ) : (
                <>
                  <Plus className="h-3.5 w-3.5" /> Add
                </>
              )}
            </Button>
          </div>
        </div>
      </div>
    </article>
  );
}
