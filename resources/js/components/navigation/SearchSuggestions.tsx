import { Link } from '@inertiajs/react';
import { highlightMatch } from '@/lib/highlight';
import { formatMoney } from '@/lib/money';
import type { SuggestResponse } from '@/types';

type SuggestionItem = { id: string; kind: 'product' | 'brand' | 'category' | 'all'; url: string };

export function flattenSuggestions(payload: SuggestResponse | null): SuggestionItem[] {
  if (!payload) {
    return [];
  }
  return [
    ...(payload.products ?? []).map((item) => ({ id: `product-${item.id}`, kind: 'product' as const, url: item.url })),
    ...(payload.brands ?? []).map((item) => ({ id: `brand-${item.id}`, kind: 'brand' as const, url: item.url })),
    ...(payload.categories ?? []).map((item) => ({ id: `category-${item.id}`, kind: 'category' as const, url: item.url })),
    ...(payload.view_all ? [{ id: 'all', kind: 'all' as const, url: payload.view_all }] : []),
  ];
}

export function SearchSuggestions({
  suggestions,
  query,
  activeId,
  listId,
  onSelect,
}: {
  suggestions: SuggestResponse;
  query: string;
  activeId?: string | null;
  listId: string;
  onSelect: () => void;
}) {
  const productCount = suggestions.products?.length ?? 0;
  const brandCount = suggestions.brands?.length ?? 0;
  const categoryCount = suggestions.categories?.length ?? 0;
  const total = productCount + brandCount + categoryCount;

  return (
    <div id={listId} role="listbox" aria-label="Search suggestions" className="z-50 mt-1 w-full rounded-xl border border-slate-200 bg-white p-3 text-sm shadow-2xl">
      <p className="sr-only" aria-live="polite">
        {total} suggestions
      </p>
      {suggestions.products && suggestions.products.length > 0 ? <p className="text-[11px] uppercase tracking-wider text-slate-500">Products</p> : null}
      {suggestions.products?.map((item) => {
        const optionId = `product-${item.id}`;
        return (
          <Link
            key={item.id}
            id={optionId}
            href={item.url}
            role="option"
            aria-selected={activeId === optionId}
            className={`mt-1 flex min-h-11 items-center gap-2 rounded-lg px-2 py-1.5 ${activeId === optionId ? 'bg-blue-50 text-slate-900' : 'hover:bg-slate-50'}`}
            onClick={onSelect}
          >
            {item.image_url ? <img src={item.image_url} alt="" className="h-8 w-8 object-contain" /> : null}
            <span>
              {highlightMatch(item.name, query)}
              <span className="block text-xs text-slate-500">
                {item.brand}
                {item.pricing ? ` · ${formatMoney(item.pricing.effective_price, item.pricing.currency)}` : ''}
              </span>
            </span>
          </Link>
        );
      })}
      {suggestions.brands && suggestions.brands.length > 0 ? <p className="mt-2 text-[11px] uppercase tracking-wider text-slate-500">Brands</p> : null}
      {suggestions.brands?.map((item) => {
        const optionId = `brand-${item.id}`;
        return (
          <Link
            key={item.id}
            id={optionId}
            href={item.url}
            role="option"
            aria-selected={activeId === optionId}
            className={`mt-1 block min-h-10 rounded-lg px-2 py-2 ${activeId === optionId ? 'bg-blue-50 text-slate-900' : 'hover:bg-slate-50'}`}
            onClick={onSelect}
          >
            {highlightMatch(item.name, query)}
          </Link>
        );
      })}
      {suggestions.categories && suggestions.categories.length > 0 ? <p className="mt-2 text-[11px] uppercase tracking-wider text-slate-500">Categories</p> : null}
      {suggestions.categories?.map((item) => {
        const optionId = `category-${item.id}`;
        return (
          <Link
            key={item.id}
            id={optionId}
            href={item.url}
            role="option"
            aria-selected={activeId === optionId}
            className={`mt-1 block min-h-10 rounded-lg px-2 py-2 ${activeId === optionId ? 'bg-blue-50 text-slate-900' : 'hover:bg-slate-50'}`}
            onClick={onSelect}
          >
            {highlightMatch(item.name, query)}
          </Link>
        );
      })}
      {suggestions.view_all ? (
        <Link
          id="all"
          href={suggestions.view_all}
          role="option"
          aria-selected={activeId === 'all'}
          className={`mt-2 block min-h-10 text-blue-600 ${activeId === 'all' ? 'underline' : ''}`}
          onClick={onSelect}
        >
          View all results
        </Link>
      ) : null}
    </div>
  );
}
