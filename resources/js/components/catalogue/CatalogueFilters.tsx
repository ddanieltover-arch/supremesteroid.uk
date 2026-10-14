import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/Button';
import type { CatalogueFilters as FilterState } from '@/types';

type FilterQuery = {
  q?: string;
  search?: string;
  category?: string;
  brand?: string;
  sort?: string;
  availability?: string;
  min_price?: string;
  max_price?: string;
  collection?: string;
  attributes?: Record<string, string>;
};

function compactFilters(filters: FilterState): FilterQuery {
  const next: FilterQuery = {};
  if (filters.q) next.q = filters.q;
  if (filters.search && !filters.q) next.search = filters.search;
  if (filters.category) next.category = filters.category;
  if (filters.brand) next.brand = filters.brand;
  if (filters.sort && filters.sort !== 'featured') next.sort = filters.sort;
  if (filters.availability) next.availability = filters.availability;
  if (filters.min_price) next.min_price = filters.min_price;
  if (filters.max_price) next.max_price = filters.max_price;
  if (filters.collection) next.collection = filters.collection;
  if (filters.attributes && Object.keys(filters.attributes).length > 0) next.attributes = filters.attributes;
  return next;
}

export function CatalogueFilters({
  listingPath,
  filters,
  categories = [],
  brands = [],
  attributes = [],
}: {
  listingPath: string;
  filters: FilterState;
  categories?: Array<{ id: string; name: string; slug: string }>;
  brands?: Array<{ id: string; name: string; slug: string }>;
  attributes?: Array<{ name: string; values: string[] }>;
}) {
  const hideCategory = listingPath.startsWith('/category');
  const hideBrand = listingPath.startsWith('/brand');

  const apply = (patch: Partial<FilterState>) => {
    const next = compactFilters({
      ...filters,
      ...patch,
      attributes: patch.attributes ?? filters.attributes,
    });
    router.get(listingPath, next, { preserveState: true, preserveScroll: true, replace: true });
  };

  const clear = () => {
    const locked: FilterQuery = {};
    if (filters.q) locked.q = filters.q;
    router.get(listingPath, locked, { preserveState: true, preserveScroll: true, replace: true });
  };

  return (
    <form
      className="space-y-6"
      onSubmit={(event) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        apply({
          min_price: String(form.get('min_price') || '') || null,
          max_price: String(form.get('max_price') || '') || null,
        });
      }}
    >
      {!hideCategory && categories.length > 0 ? (
        <fieldset>
          <legend className="text-xs font-semibold uppercase tracking-wider text-slate-400">Category</legend>
          <ul className="mt-2 space-y-1">
            {categories.map((item) => (
              <li key={item.id}>
                <button
                  type="button"
                  className={`min-h-10 w-full rounded-lg px-3 text-left text-sm ${filters.category === item.slug ? 'bg-amber-400/10 text-amber-300' : 'text-slate-300 hover:bg-slate-900'}`}
                  onClick={() => apply({ category: filters.category === item.slug ? null : item.slug })}
                  aria-pressed={filters.category === item.slug}
                >
                  {item.name}
                </button>
              </li>
            ))}
          </ul>
        </fieldset>
      ) : null}

      {!hideBrand && brands.length > 0 ? (
        <fieldset>
          <legend className="text-xs font-semibold uppercase tracking-wider text-slate-400">Brand</legend>
          <ul className="mt-2 space-y-1">
            {brands.map((item) => (
              <li key={item.id}>
                <button
                  type="button"
                  className={`min-h-10 w-full rounded-lg px-3 text-left text-sm ${filters.brand === item.slug ? 'bg-amber-400/10 text-amber-300' : 'text-slate-300 hover:bg-slate-900'}`}
                  onClick={() => apply({ brand: filters.brand === item.slug ? null : item.slug })}
                  aria-pressed={filters.brand === item.slug}
                >
                  {item.name}
                </button>
              </li>
            ))}
          </ul>
        </fieldset>
      ) : null}

      <fieldset>
        <legend className="text-xs font-semibold uppercase tracking-wider text-slate-400">Price</legend>
        <div className="mt-2 grid grid-cols-2 gap-2">
          <label className="text-xs text-slate-400">
            Min
            <input name="min_price" type="number" min={0} step="0.01" defaultValue={filters.min_price ?? ''} className="field mt-1" />
          </label>
          <label className="text-xs text-slate-400">
            Max
            <input name="max_price" type="number" min={0} step="0.01" defaultValue={filters.max_price ?? ''} className="field mt-1" />
          </label>
        </div>
        <Button type="submit" variant="secondary" size="sm" className="mt-2 w-full">
          Apply price
        </Button>
      </fieldset>

      <fieldset>
        <legend className="text-xs font-semibold uppercase tracking-wider text-slate-400">Availability</legend>
        <button
          type="button"
          className={`mt-2 min-h-10 w-full rounded-lg px-3 text-left text-sm ${filters.availability === 'in_stock' ? 'bg-emerald-500/10 text-emerald-300' : 'text-slate-300 hover:bg-slate-900'}`}
          onClick={() => apply({ availability: filters.availability === 'in_stock' ? null : 'in_stock' })}
          aria-pressed={filters.availability === 'in_stock'}
        >
          In stock
        </button>
      </fieldset>

      {attributes.map((attribute) =>
        attribute.values.length > 0 ? (
          <fieldset key={attribute.name}>
            <legend className="text-xs font-semibold uppercase tracking-wider text-slate-400">{attribute.name.replaceAll('_', ' ')}</legend>
            <ul className="mt-2 space-y-1">
              {attribute.values.map((value) => {
                const selected = filters.attributes?.[attribute.name] === value;
                return (
                  <li key={value}>
                    <button
                      type="button"
                      className={`min-h-10 w-full rounded-lg px-3 text-left text-sm ${selected ? 'bg-amber-400/10 text-amber-300' : 'text-slate-300 hover:bg-slate-900'}`}
                      onClick={() => {
                        const nextAttributes = { ...(filters.attributes ?? {}) };
                        if (selected) {
                          delete nextAttributes[attribute.name];
                        } else {
                          nextAttributes[attribute.name] = value;
                        }
                        apply({ attributes: nextAttributes });
                      }}
                      aria-pressed={selected}
                    >
                      {value}
                    </button>
                  </li>
                );
              })}
            </ul>
          </fieldset>
        ) : null,
      )}

      <Button type="button" variant="ghost" className="w-full" onClick={clear}>
        Clear filters
      </Button>
    </form>
  );
}
