import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ProductCard } from '@/components/ProductCard';
import { Pagination } from '@/components/Pagination';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import { Dialog } from '@/components/ui/Dialog';
import { Breadcrumbs } from '@/components/catalogue/Breadcrumbs';
import { CatalogueFilters } from '@/components/catalogue/CatalogueFilters';
import { RecentlyViewed } from '@/components/catalogue/RecentlyViewed';
import { TrustBlocks } from '@/components/catalogue/TrustBlocks';
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

  const changeSort = (sort: string) => {
    router.get(listing_path || '/shop', { ...filters, sort }, { preserveState: true, preserveScroll: true, replace: true });
  };

  const description = listing_kind === 'search'
    ? 'Search uses the store catalogue index. Results stay limited to published, compliance-approved products.'
    : listing_kind === 'brand'
      ? 'Only approved products from this brand are listed.'
      : 'Browse published products with server-side filters, sorting, and pagination.';

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
        <div className="mb-6 aspect-[21/7] overflow-hidden rounded-2xl border border-slate-800 bg-slate-950">
          {context.type === 'brand' ? (
            <img src={context.image_url} alt={`${context.name} logo`} className="h-full w-full object-contain p-8" />
          ) : (
            <img src={context.image_url} alt="" className="h-full w-full object-cover" />
          )}
        </div>
      ) : null}

      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-3xl font-semibold text-white">{heading}</h1>
          <p className="mt-3 max-w-3xl text-sm text-slate-400">{context?.description || description}</p>
          {listing_kind === 'search' ? <p className="mt-2 text-sm text-slate-500">{total} results</p> : null}
          {listing_kind === 'brand' ? <p className="mt-2 text-sm text-slate-500">{total} approved products</p> : null}
        </div>
      </div>

      {listing_kind === 'shop' ? (
        <div className="mt-6 hidden flex-wrap gap-2 md:flex" aria-label="Merchandising">
          {[
            { value: null, label: 'All' },
            { value: 'featured', label: 'Featured' },
            { value: 'new', label: 'New' },
            { value: 'sale', label: 'Sale' },
          ].map((item) => (
            <Link
              key={item.label}
              href={item.value ? `${listing_path}?collection=${item.value}` : listing_path}
              className={`rounded-full border px-3 py-1.5 text-sm ${filters.collection === item.value || (!item.value && !filters.collection) ? 'border-amber-400 text-amber-300' : 'border-slate-800 text-slate-300 hover:border-amber-400/50'}`}
            >
              {item.label}
            </Link>
          ))}
        </div>
      ) : null}

      {subcategories.length > 0 ? (
        <nav aria-label="Subcategories" className="mt-6 flex flex-wrap gap-2">
          {subcategories.map((item) => (
            <Link key={item.id} href={`/category/${item.slug}`} className="rounded-full border border-slate-800 px-3 py-1.5 text-sm text-slate-300 hover:border-amber-400 hover:text-amber-300">
              {item.name}
            </Link>
          ))}
        </nav>
      ) : null}

      {listing_kind === 'shop' && categories.length > 0 ? (
        <nav aria-label="Categories" className="mt-6 hidden gap-2 overflow-x-auto md:flex">
          {categories.map((item) => (
            <Link key={item.id} href={`/category/${item.slug}`} className="rounded-full border border-slate-800 px-3 py-1.5 text-sm text-slate-300 hover:border-amber-400 hover:text-amber-300">
              {item.name}
            </Link>
          ))}
        </nav>
      ) : null}

      <form
        className="mt-6 md:hidden"
        onSubmit={(event) => {
          event.preventDefault();
          searchForm.get('/search');
        }}
      >
        <label className="sr-only" htmlFor="shop-mobile-search">Search products</label>
        <input
          id="shop-mobile-search"
          value={searchForm.data.q}
          onChange={(event) => searchForm.setData('q', event.target.value)}
          placeholder="Search the catalogue"
          className="field"
        />
      </form>

      <div className="sticky top-[7.5rem] z-20 mt-4 flex gap-2 bg-[#020617]/95 py-3 backdrop-blur md:hidden">
        <Button variant="secondary" className="flex-1" onClick={() => setFiltersOpen(true)}>
          Filters
        </Button>
        <Button variant="secondary" className="flex-1" onClick={() => setSortOpen(true)}>
          Sort
        </Button>
      </div>

      <div className="mt-6 grid gap-8 lg:grid-cols-[16rem_minmax(0,1fr)]">
        <aside className="hidden lg:block" aria-label="Filters">
          <CatalogueFilters listingPath={listing_path} filters={filters} categories={categories} brands={brands} attributes={attribute_facets} />
        </aside>

        <div>
          <div className="mb-4 hidden items-center justify-between md:flex">
            <p className="text-xs text-slate-500">
              Showing <strong className="text-slate-200">{total}</strong> products
            </p>
            <div className="w-64">
              <Select
                label="Sort"
                value={filters.sort ?? 'featured'}
                options={sortOptions}
                onChange={(event) => changeSort(event.target.value)}
              />
            </div>
          </div>

          {(products?.data ?? []).length === 0 ? (
            <div className="rounded-2xl border border-slate-800 bg-slate-950/60 p-12 text-center">
              <p className="text-sm font-semibold text-white">No products found</p>
              <p className="mt-2 text-sm text-slate-400">Try a new search or clear the current filters.</p>
              {related_searches.length > 0 ? (
                <ul className="mt-3 text-sm text-slate-300">
                  {related_searches.map((item) =>
                    typeof item === 'string' ? (
                      <li key={item}>{item}</li>
                    ) : (
                      <li key={item.url}>
                        <Link href={item.url} className="text-amber-300 hover:text-amber-200">
                          {item.name}
                        </Link>
                      </li>
                    ),
                  )}
                </ul>
              ) : null}
              <div className="mt-4 flex flex-wrap justify-center gap-2">
                <Button variant="secondary" onClick={() => router.get(listing_path || '/shop')}>
                  Clear filters
                </Button>
                <Button variant="ghost" onClick={() => router.get('/search')}>
                  New search
                </Button>
              </div>
            </div>
          ) : (
            <div className="grid grid-cols-2 gap-4 sm:gap-6 xl:grid-cols-3">
              {(products?.data ?? []).map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          )}

          <Pagination pager={products} />
        </div>
      </div>

      {context?.description ? (
        <section className="mt-12 max-w-3xl text-sm text-slate-400">
          <h2 className="text-lg font-semibold text-white">About this collection</h2>
          <p className="mt-3">{context.description}</p>
        </section>
      ) : null}

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
