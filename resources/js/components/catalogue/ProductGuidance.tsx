import { Link } from '@inertiajs/react';
import type { ProductDetail } from '@/types';

type GuidanceBlock = {
  id: string;
  label: string;
  body: string;
};

function blocksFromProduct(product: ProductDetail): GuidanceBlock[] {
  const content = product.content ?? {};
  const candidates: Array<[string, string, string | null | undefined]> = [
    ['why', 'Why', content.why_choose],
    ['how', 'How', content.how_supplied],
    ['where', 'Where', content.where_it_fits],
    ['who', 'Who it is for', content.who_its_for],
    ['what', 'What you get', content.what_you_get],
  ];

  return candidates
    .filter(([, , body]) => typeof body === 'string' && body.trim() !== '')
    .map(([id, label, body]) => ({ id, label, body: (body as string).trim() }));
}

export function ProductGuidance({ product }: { product: ProductDetail }) {
  const blocks = blocksFromProduct(product);
  const primary = blocks.filter((block) => ['why', 'how', 'where'].includes(block.id));
  const secondary = blocks.filter((block) => ['who', 'what'].includes(block.id));
  const differences = (product.key_differences ?? []).filter((item) => item.trim() !== '');

  if (blocks.length === 0 && differences.length === 0) {
    return null;
  }

  return (
    <section className="mt-10 space-y-8" aria-label="Product guidance">
      {primary.length > 0 ? (
        <div className="grid gap-6 md:grid-cols-3">
          {primary.map((block) => (
            <div key={block.id}>
              <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-500">{block.label}</h2>
              <p className="mt-2 text-sm leading-relaxed text-slate-700">{block.body}</p>
            </div>
          ))}
        </div>
      ) : null}

      {secondary.length > 0 ? (
        <div className="grid gap-6 md:grid-cols-2">
          {secondary.map((block) => (
            <div key={block.id}>
              <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-500">{block.label}</h2>
              <p className="mt-2 text-sm leading-relaxed text-slate-700">{block.body}</p>
            </div>
          ))}
        </div>
      ) : null}

      {differences.length > 0 ? (
        <div>
          <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-500">Key differences</h2>
          <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-700">
            {differences.map((item) => (
              <li key={item}>{item}</li>
            ))}
          </ul>
        </div>
      ) : null}
    </section>
  );
}

export function ProductRelatedReading({ product }: { product: ProductDetail }) {
  const items = product.related_reading ?? [];
  if (items.length === 0) {
    return null;
  }

  return (
    <section className="mt-12" aria-label="Related reading">
      <h2 className="text-xl font-semibold text-slate-900">Related reading</h2>
      <p className="mt-1 text-sm text-slate-500">Educational guides live on the blog — not as dosing advice on this page.</p>
      <ul className="mt-4 space-y-2">
        {items.map((item) => (
          <li key={item.slug}>
            <Link href={item.url || `/blog/${item.slug}`} className="text-sm font-medium text-blue-600 hover:text-blue-700">
              {item.title}
            </Link>
          </li>
        ))}
      </ul>
    </section>
  );
}
