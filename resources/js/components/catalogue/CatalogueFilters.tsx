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

function FilterLegend({ children }: { children: string }) {
  return <legend className="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">{children}</legend>;
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
      className="space-y-7"
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
          <FilterLegend>Category</FilterLegend>
          <ul className="mt-3 max-h-64 space-y-0.5 overflow-y-auto pr-1 [scrollbar-width:thin]">
            {categories.map((item) => {
              const active = filters.category === item.slug;
              return (
                <li key={item.id}>
                  <button
                    type="button"
                    className={`flex min-h-10 w-full items-center rounded-lg px-3 text-left text-sm transition ${
                      active ? 'bg-blue-600 font-semibold text-white' : 'text-slate-700 hover:bg-slate-50'
                    }`}
                    onClick={() => apply({ category: active ? null : item.slug })}
                    aria-pressed={active}
                  >
                    {item.name}
                  </button>
                </li>
              );
            })}
          </ul>
        </fieldset>
      ) : null}

      {!hideBrand && brands.length > 0 ? (
        <fieldset>
          <FilterLegend>Brand</FilterLegend>
          <ul className="mt-3 max-h-48 space-y-0.5 overflow-y-auto pr-1 [scrollbar-width:thin]">
            {brands.map((item) => {
              const active = filters.brand === item.slug;
              return (
                <li key={item.id}>
                  <button
                    type="button"
                    className={`flex min-h-10 w-full items-center rounded-lg px-3 text-left text-sm transition ${
                      active ? 'bg-blue-600 font-semibold text-white' : 'text-slate-700 hover:bg-slate-50'
                    }`}
                    onClick={() => apply({ brand: active ? null : item.slug })}
                    aria-pressed={active}
                  >
                    {item.name}
                  </button>
                </li>
              );
            })}
          </ul>
        </fieldset>
      ) : null}

      <fieldset>
        <FilterLegend>Price</FilterLegend>
        <div className="mt-3 grid grid-cols-2 gap-2">
          <label className="text-xs font-medium text-slate-500">
            Min
            <input name="min_price" type="number" min={0} step="0.01" defaultValue={filters.min_price ?? ''} className="field mt-1.5" />
          </label>
          <label className="text-xs font-medium text-slate-500">
            Max
            <input name="max_price" type="number" min={0} step="0.01" defaultValue={filters.max_price ?? ''} className="field mt-1.5" />
          </label>
        </div>
        <Button type="submit" variant="secondary" size="sm" className="mt-3 w-full">
          Apply price
        </Button>
      </fieldset>

      <fieldset>
        <FilterLegend>Availability</FilterLegend>
        <button
          type="button"
          className={`mt-3 flex min-h-10 w-full items-center rounded-lg px-3 text-left text-sm transition ${
            filters.availability === 'in_stock' ? 'bg-emerald-600 font-semibold text-white' : 'text-slate-700 hover:bg-slate-50'
          }`}
          onClick={() => apply({ availability: filters.availability === 'in_stock' ? null : 'in_stock' })}
          aria-pressed={filters.availability === 'in_stock'}
        >
          In stock only
        </button>
      </fieldset>

      {attributes.map((attribute) =>
        attribute.values.length > 0 ? (
          <fieldset key={attribute.name}>
            <FilterLegend>{attribute.name.replaceAll('_', ' ')}</FilterLegend>
            <ul className="mt-3 space-y-0.5">
              {attribute.values.map((value) => {
                const selected = filters.attributes?.[attribute.name] === value;
                return (
                  <li key={value}>
                    <button
                      type="button"
                      className={`flex min-h-10 w-full items-center rounded-lg px-3 text-left text-sm transition ${
                        selected ? 'bg-blue-600 font-semibold text-white' : 'text-slate-700 hover:bg-slate-50'
                      }`}
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
