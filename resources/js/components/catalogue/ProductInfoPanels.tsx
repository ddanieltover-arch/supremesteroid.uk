import { useMemo, useState } from 'react';
import type { ProductDetail } from '@/types';

function PanelBody({ product, tab }: { product: ProductDetail; tab: string }) {
  if (tab === 'overview') {
    return (
      <div className="prose-invert space-y-3 text-sm leading-relaxed text-slate-300">
        <p>{product.description || product.short_description || 'No overview is published for this product yet.'}</p>
        {product.ingredients ? <p><strong className="text-white">Composition.</strong> {product.ingredients}</p> : null}
        {product.safety_guidelines ? <p><strong className="text-white">Safety.</strong> {product.safety_guidelines}</p> : null}
        {product.content?.overview ? <p>{product.content.overview}</p> : null}
        {product.content?.important_information ? <p>{product.content.important_information}</p> : null}
      </div>
    );
  }

  if (tab === 'specifications') {
    return (product.specifications ?? []).length > 0 ? (
      <dl className="grid gap-3 sm:grid-cols-2">
        {(product.specifications ?? []).map((row) => (
          <div key={`${row.name}-${row.value}`} className="rounded-lg border border-slate-800 px-3 py-2">
            <dt className="text-xs uppercase tracking-wider text-slate-500">{row.name}</dt>
            <dd className="mt-1 text-sm text-slate-100">{row.value}</dd>
          </div>
        ))}
      </dl>
    ) : (
      <p className="text-sm text-slate-500">No public specifications are available.</p>
    );
  }

  if (tab === 'documentation') {
    const docs = [product.content?.documentation, product.content?.manufacturer_information, product.content?.packaging_information, product.lab_verification_reference ? `Reference ${product.lab_verification_reference}` : null].filter(Boolean);
    return docs.length > 0 ? (
      <div className="space-y-3 text-sm text-slate-300">
        {docs.map((item) => (
          <p key={String(item)}>{item}</p>
        ))}
      </div>
    ) : (
      <p className="text-sm text-slate-500">No verified documentation is attached to this product.</p>
    );
  }

  if (tab === 'shipping') {
    return (
      <div className="space-y-3 text-sm text-slate-300">
        {product.content?.shipping_information ? <p>{product.content.shipping_information}</p> : <p>Shipping options and free-delivery eligibility are confirmed from the live basket.</p>}
        {(product.shipping_methods ?? []).map((method) => (
          <p key={method.code}>{method.name}{method.estimated_days ? ` · ${method.estimated_days}` : ''}</p>
        ))}
        {product.content?.storage_information ? <p>{product.content.storage_information}</p> : null}
      </div>
    );
  }

  if (tab === 'faqs') {
    return (product.faqs ?? []).length > 0 ? (
      <dl className="space-y-3">
        {(product.faqs ?? []).map((item) => (
          <div key={item.question} className="rounded-lg border border-slate-800 p-3">
            <dt className="font-semibold text-white">{item.question}</dt>
            <dd className="mt-1 text-sm text-slate-300">{item.answer}</dd>
          </div>
        ))}
      </dl>
    ) : (
      <p className="text-sm text-slate-500">No FAQs are configured for this product.</p>
    );
  }

  const items = product.reviews?.items ?? [];
  if (items.length === 0) {
    return <p className="text-sm text-slate-500">No approved reviews yet.</p>;
  }

  return (
    <ul className="space-y-4">
      {items.map((review) => (
        <li key={review.id} className="rounded-xl border border-slate-800 p-4">
          <p className="text-sm font-semibold text-white">{review.title || 'Customer review'}</p>
          <p className="mt-1 text-xs text-slate-500">
            {review.rating}/5 · {review.display_name || 'Customer'}
            {review.verified_purchase ? ' · Verified purchase' : ''}
          </p>
          {review.body ? <p className="mt-2 text-sm text-slate-300">{review.body}</p> : null}
        </li>
      ))}
    </ul>
  );
}

export function ProductInfoPanels({ product }: { product: ProductDetail }) {
  const tabs = useMemo(() => {
    const items = [
      { id: 'overview', label: 'Overview' },
      { id: 'specifications', label: 'Specifications' },
      { id: 'documentation', label: 'Documentation' },
      { id: 'shipping', label: 'Shipping' },
      { id: 'faqs', label: 'FAQs' },
      { id: 'reviews', label: 'Reviews' },
    ];
    return items.filter((tab) => {
      if (tab.id === 'faqs') return (product.faqs ?? []).length > 0;
      if (tab.id === 'documentation') return Boolean(product.content?.documentation || product.lab_verification_reference);
      return true;
    });
  }, [product]);

  const [active, setActive] = useState(tabs[0]?.id ?? 'overview');

  return (
    <section className="mt-12">
      <div className="hidden border-b border-slate-800 md:block">
        <div role="tablist" aria-label="Product information" className="flex flex-wrap gap-2">
          {tabs.map((tab) => (
            <button
              key={tab.id}
              type="button"
              role="tab"
              id={`tab-${tab.id}`}
              aria-selected={active === tab.id}
              aria-controls={`panel-${tab.id}`}
              className={`min-h-11 px-4 text-sm ${active === tab.id ? 'border-b-2 border-amber-400 text-white' : 'text-slate-400 hover:text-slate-200'}`}
              onClick={() => setActive(tab.id)}
            >
              {tab.label}
            </button>
          ))}
        </div>
        <div role="tabpanel" id={`panel-${active}`} aria-labelledby={`tab-${active}`} className="py-6">
          <PanelBody product={product} tab={active} />
        </div>
      </div>

      <div className="space-y-3 md:hidden">
        {tabs.map((tab) => (
          <details key={tab.id} open={tab.id === 'overview'} className="rounded-xl border border-slate-800">
            <summary className="cursor-pointer px-4 py-3 text-sm font-semibold text-white">{tab.label}</summary>
            <div className="border-t border-slate-800 px-4 py-4">
              <PanelBody product={product} tab={tab.id} />
            </div>
          </details>
        ))}
      </div>
    </section>
  );
}
