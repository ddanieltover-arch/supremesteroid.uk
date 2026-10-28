import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Search, SlidersHorizontal, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ProductCard } from '@/components/ProductCard';
import { Pagination } from '@/components/Pagination';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import { Dialog } from '@/components/ui/Dialog';
import { Breadcrumbs } from '@/components/catalogue/Breadcrumbs';
import { CatalogueFilters } from '@/components/catalogue/CatalogueFilters';
import { RecentlyViewed } from '@/components/catalogue/RecentlyViewed';
import { TrustBlocks } from '@/components/catalogue/TrustBlocks';
import { ShopEditorialContent } from '@/components/catalogue/ShopEditorialContent';
import type { CatalogueFilters as FilterState, Paginated, ProductCard as ProductCardType, SeoProps } from '@/types';

const sortOptions = [
  { value: 'featured', label: 'Featured' },
  { value: 'newest', label: 'Newest' },
  { value: 'price_asc', label: 'Price: Low to High' },
  { value: 'price_desc', label: 'Price: High to Low' },
  { value: 'name_asc', label: 'Name: A–Z' },
  { value: 'name_desc', label: 'Name: Z–A' },
  { value: 'availability', label: 'Availability' },
];

const collections = [
  { value: null, label: 'All' },
  { value: 'featured', label: 'Featured' },
  { value: 'new', label: 'New' },
  { value: 'sale', label: 'Sale' },
] as const;

function activeFilterChips(filters: FilterState, categories: Array<{ slug: string; name: string }>, brands: Array<{ slug: string; name: string }>) {
  const chips: Array<{ key: string; label: string; clear: Partial<FilterState> }> = [];
  if (filters.collection) {
    chips.push({ key: 'collection', label: filters.collection.replaceAll('_', ' '), clear: { collection: null } });
  }
  if (filters.category) {
    const name = categories.find((item) => item.slug === filters.category)?.name ?? filters.category;
    chips.push({ key: 'category', label: name, clear: { category: null } });
  }
  if (filters.brand) {
    const name = brands.find((item) => item.slug === filters.brand)?.name ?? filters.brand;
    chips.push({ key: 'brand', label: name, clear: { brand: null } });
  }
  if (filters.availability === 'in_stock') {
    chips.push({ key: 'availability', label: 'In stock', clear: { availability: null } });
  }
  if (filters.min_price || filters.max_price) {
    const min = filters.min_price ? `£${filters.min_price}` : 'Any';
    const max = filters.max_price ? `£${filters.max_price}` : 'Any';
    chips.push({ key: 'price', label: `${min} – ${max}`, clear: { min_price: null, max_price: null } });
  }
  Object.entries(filters.attributes ?? {}).forEach(([name, value]) => {
    chips.push({
      key: `attr-${name}`,
      label: `${name.replaceAll('_', ' ')}: ${value}`,
      clear: {
        attributes: Object.fromEntries(Object.entries(filters.attributes ?? {}).filter(([key]) => key !== name)),
      },
    });
  });
  return chips;
}

export default function CatalogueIndex({
  heading = 'Shop',
  listing_kind = 'shop',
  listing_path = '/shop',
  result_count,
  breadcrumbs,
  structured_data,
  context,
  subcategories = [],
  products,
  categories = [],
  brands = [],
  attribute_facets = [],
  related_searches = [],
  filters,
  seo,
}: {
  heading?: string;
  listing_kind?: string;
  listing_path?: string;
  result_count?: number;
  breadcrumbs?: Array<{ name: string; url: string }>;
  structured_data?: Array<Record<string, unknown>>;
  context?: { type?: string; name?: string; description?: string | null; image_url?: string | null; product_count?: number } | null;
  subcategories?: Array<{ id: string; name: string; slug: string }>;
  products: Paginated<ProductCardType>;
  categories?: Array<{ id: string; name: string; slug: string }>;
  brands?: Array<{ id: string; name: string; slug: string }>;
  attribute_facets?: Array<{ name: string; values: string[] }>;
  related_searches?: Array<{ name: string; url: string } | string>;
  filters: FilterState;
  seo?: SeoProps;
}) {
  const [filtersOpen, setFiltersOpen] = useState(false);
  const [sortOpen, setSortOpen] = useState(false);
  const searchForm = useForm({ q: filters.q ?? filters.search ?? '' });
  const total = result_count ?? products?.total ?? products?.data?.length ?? 0;
  const chips = useMemo(() => activeFilterChips(filters, categories, brands), [filters, categories, brands]);

  const changeSort = (sort: string) => {
    router.get(listing_path || '/shop', { ...filters, sort }, { preserveState: true, preserveScroll: true, replace: true });
  };

  const compactQuery = (next: FilterState) => {
    const query: Record<string, string | Record<string, string>> = {};
    if (next.q) query.q = next.q;
    if (next.search && !next.q) query.search = next.search;
    if (next.category) query.category = next.category;
    if (next.brand) query.brand = next.brand;
    if (next.sort && next.sort !== 'featured') query.sort = next.sort;
    if (next.availability) query.availability = next.availability;
    if (next.min_price) query.min_price = next.min_price;
    if (next.max_price) query.max_price = next.max_price;
    if (next.collection) query.collection = next.collection;
    if (next.attributes && Object.keys(next.attributes).length > 0) query.attributes = next.attributes;
    return query;
  };

  const applyChipClear = (patch: Partial<FilterState>) => {
    router.get(
      listing_path || '/shop',
      compactQuery({
        ...filters,
        ...patch,
        attributes: patch.attributes ?? filters.attributes,
      }),
      { preserveState: true, preserveScroll: true, replace: true },
    );
  };

  const clearAllFilters = () => {
    const locked: Record<string, string> = {};
    if (filters.q) locked.q = filters.q;
    router.get(listing_path || '/shop', locked, { preserveState: true, preserveScroll: true, replace: true });
  };

  const description =
    listing_kind === 'search'
      ? 'Results are limited to published, compliance-approved products.'
      : listing_kind === 'brand'
        ? 'Approved products from this brand.'
        : context?.description || 'Lab-tested products with clear pricing and availability.';

  return (
    <StorefrontLayout seo={seo}>
      <Head>
        {(structured_data ?? []).map((schema, index) => (
          <script key={index} type="application/ld+json">
            {JSON.stringify(schema)}
          </script>
        ))}
      </Head>

      <Breadcrumbs items={breadcrumbs} />

      {context?.image_url ? (
        <div className="mb-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <div className="aspect-[21/6] max-h-48">
            {context.type === 'brand' ? (
              <img src={context.image_url} alt={`${context.name} logo`} className="h-full w-full object-contain p-8" />
            ) : (
              <img src={context.image_url} alt="" className="h-full w-full object-cover" />
            )}
          </div>
        </div>
      ) : null}

      <header className="border-b border-slate-200 pb-6">
        <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
          <div className="min-w-0">
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-blue-600">Catalogue</p>
            <h1 className="mt-2 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">{heading}</h1>
            <p className="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">{description}</p>
          </div>
          <p className="shrink-0 text-sm text-slate-500">
            <span className="text-2xl font-bold tabular-nums text-slate-900">{total.toLocaleString('en-GB')}</span>
            <span className="ml-2">products</span>
          </p>
        </div>

        {listing_kind === 'shop' ? (
          <div className="mt-6 inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm" role="tablist" aria-label="Collections">
            {collections.map((item) => {
              const active = item.value ? filters.collection === item.value : !filters.collection;
              return (
                <Link
                  key={item.label}
                  href={item.value ? `${listing_path}?collection=${item.value}` : listing_path}
                  role="tab"
                  aria-selected={active}
                  className={`rounded-lg px-4 py-2 text-sm font-semibold transition ${
                    active ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  }`}
                >
                  {item.label}
                </Link>
              );
            })}
          </div>
        ) : null}
      </header>

      {subcategories.length > 0 ? (
        <nav aria-label="Subcategories" className="mt-5 flex flex-wrap gap-2">
          {subcategories.map((item) => (
            <Link
              key={item.id}
              href={`/category/${item.slug}`}
              className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm hover:border-blue-300 hover:text-blue-600"
            >
              {item.name}
            </Link>
          ))}
        </nav>
      ) : null}

      <form
        className="mt-5 md:hidden"
        onSubmit={(event) => {
          event.preventDefault();
          searchForm.get('/search');
        }}
      >
        <label className="sr-only" htmlFor="shop-mobile-search">
          Search products
        </label>
        <div className="relative">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
          <input
            id="shop-mobile-search"
            value={searchForm.data.q}
            onChange={(event) => searchForm.setData('q', event.target.value)}
            placeholder="Search the catalogue"
            className="field pl-10"
          />
        </div>
      </form>

      <div className="sticky top-[4rem] z-20 -mx-4 mt-4 flex gap-2 border-y border-slate-200 bg-[#f9f9f9]/95 px-4 py-3 backdrop-blur md:hidden">
        <Button variant="secondary" className="flex-1 gap-2" onClick={() => setFiltersOpen(true)}>
          <SlidersHorizontal className="h-4 w-4" aria-hidden="true" />
          Filters
          {chips.length > 0 ? <span className="rounded-full bg-blue-600 px-1.5 text-[11px] text-white">{chips.length}</span> : null}
        </Button>
        <Button variant="secondary" className="flex-1" onClick={() => setSortOpen(true)}>
          Sort
        </Button>
      </div>

      <div className="mt-8 grid gap-8 lg:grid-cols-[17.5rem_minmax(0,1fr)]">
        <aside className="hidden lg:block" aria-label="Filters">
          <div className="sticky top-24 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="mb-5 flex items-center justify-between border-b border-slate-100 pb-4">
              <h2 className="text-sm font-bold uppercase tracking-wider text-slate-900">Filters</h2>
              {chips.length > 0 ? (
                <button type="button" className="text-xs font-semibold text-blue-600 hover:text-blue-700" onClick={clearAllFilters}>
                  Clear all
                </button>
              ) : null}
            </div>
            <CatalogueFilters listingPath={listing_path} filters={filters} categories={categories} brands={brands} attributes={attribute_facets} />
          </div>
        </aside>

        <div>
          <div className="mb-5 hidden items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm md:flex">
            <div className="min-w-0">
              <p className="text-sm text-slate-600">
                Showing <strong className="font-semibold text-slate-900">{total.toLocaleString('en-GB')}</strong> products
              </p>
              {chips.length > 0 ? (
                <div className="mt-2 flex flex-wrap gap-2">
                  {chips.map((chip) => (
                    <button
                      key={chip.key}
                      type="button"
                      onClick={() => applyChipClear(chip.clear)}
                      className="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium capitalize text-slate-700 hover:border-blue-300 hover:text-blue-700"
                    >
                      {chip.label}
                      <X className="h-3 w-3" aria-hidden="true" />
                      <span className="sr-only">Remove {chip.label} filter</span>
                    </button>
                  ))}
                </div>
              ) : null}
            </div>
            <div className="w-56 shrink-0">
              <Select
                label="Sort"
                value={filters.sort ?? 'featured'}
                options={sortOptions}
                onChange={(event) => changeSort(event.target.value)}
              />
            </div>
          </div>

          {chips.length > 0 ? (
            <div className="mb-4 flex flex-wrap gap-2 md:hidden">
              {chips.map((chip) => (
                <button
                  key={chip.key}
                  type="button"
                  onClick={() => applyChipClear(chip.clear)}
                  className="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium capitalize text-slate-700"
                >
                  {chip.label}
                  <X className="h-3 w-3" aria-hidden="true" />
                </button>
              ))}
            </div>
          ) : null}

          {(products?.data ?? []).length === 0 ? (
            <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm">
              <p className="text-base font-semibold text-slate-900">No products match these filters</p>
              <p className="mx-auto mt-2 max-w-md text-sm text-slate-500">Try clearing filters or starting a new search across the catalogue.</p>
              {related_searches.length > 0 ? (
                <ul className="mt-4 flex flex-wrap justify-center gap-2 text-sm">
                  {related_searches.map((item) =>
                    typeof item === 'string' ? (
                      <li key={item} className="rounded-full bg-slate-100 px-3 py-1 text-slate-600">
                        {item}
                      </li>
                    ) : (
                      <li key={item.url}>
                        <Link href={item.url} className="rounded-full bg-blue-50 px-3 py-1 font-medium text-blue-700 hover:bg-blue-100">
                          {item.name}
                        </Link>
                      </li>
                    ),
                  )}
                </ul>
              ) : null}
              <div className="mt-6 flex flex-wrap justify-center gap-2">
                <Button variant="primary" onClick={clearAllFilters}>
                  Clear filters
                </Button>
                <Button variant="secondary" onClick={() => router.get('/search')}>
                  New search
                </Button>
              </div>
            </div>
          ) : (
            <div className="grid auto-rows-fr grid-cols-2 gap-4 sm:gap-5 xl:grid-cols-3">
              {(products?.data ?? []).map((product) => (
                <div key={product.id} className="h-full min-h-0">
                  <ProductCard product={product} />
                </div>
              ))}
            </div>
          )}

          <Pagination pager={products} />
        </div>
      </div>

      {context?.description && listing_kind !== 'shop' ? (
        <section className="mt-14 max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <h2 className="text-lg font-semibold text-slate-900">About this collection</h2>
          <p className="mt-3 text-sm leading-relaxed text-slate-600">{context.description}</p>
        </section>
      ) : null}

      {listing_kind === 'shop' ? <ShopEditorialContent /> : null}

      <RecentlyViewed />
      <TrustBlocks />

      <Dialog open={filtersOpen} onClose={() => setFiltersOpen(false)} title="Filters">
        <CatalogueFilters listingPath={listing_path} filters={filters} categories={categories} brands={brands} attributes={attribute_facets} />
      </Dialog>
      <Dialog open={sortOpen} onClose={() => setSortOpen(false)} title="Sort" size="md">
        <Select
          label="Sort"
          value={filters.sort ?? 'featured'}
          options={sortOptions}
          onChange={(event) => {
            changeSort(event.target.value);
            setSortOpen(false);
          }}
        />
      </Dialog>
    </StorefrontLayout>
  );
}
