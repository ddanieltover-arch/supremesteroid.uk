import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { router, useForm } from '@inertiajs/react';
import { ProductCard } from '@/components/ProductCard';
import { Pagination } from '@/components/Pagination';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import type { Paginated, ProductCard as ProductCardType, SeoProps } from '@/types';

type Filters = {
  category?: string | null;
  brand?: string | null;
  search?: string | null;
  sort?: string;
  availability?: string | null;
  min_price?: string | null;
  max_price?: string | null;
};

export default function CatalogueIndex({
  heading = 'Shop',
  products,
  categories = [],
  brands = [],
  filters,
  seo,
}: {
  heading?: string;
  products: Paginated<ProductCardType>;
  categories?: Array<{ id: string; name: string; slug: string }>;
  brands?: Array<{ id: string; name: string; slug: string }>;
  filters: Filters;
  seo?: SeoProps;
}) {
  const form = useForm({
    search: filters.search ?? '',
    category: filters.category ?? '',
    brand: filters.brand ?? '',
    sort: filters.sort ?? 'featured',
    availability: filters.availability ?? '',
    min_price: filters.min_price ?? '',
    max_price: filters.max_price ?? '',
  });

  const apply = () => {
    form.get('/shop', { preserveState: true, preserveScroll: true });
  };

  return (
    <StorefrontLayout seo={seo}>
      <h1 className="text-3xl font-semibold text-white">{heading}</h1>
      <form
        className="mt-6 grid gap-3 md:grid-cols-2 lg:grid-cols-4"
        onSubmit={(event) => {
          event.preventDefault();
          apply();
        }}
      >
        <Input
          label="Search"
          value={form.data.search}
          onChange={(event) => form.setData('search', event.target.value)}
          placeholder="Name or SKU"
        />
        <Select
          label="Category"
          value={form.data.category}
          options={[{ value: '', label: 'All categories' }, ...categories.map((category) => ({ value: category.slug, label: category.name }))]}
          onChange={(event) => form.setData('category', event.target.value)}
        />
        <Select
          label="Brand"
          value={form.data.brand}
          options={[{ value: '', label: 'All brands' }, ...brands.map((brand) => ({ value: brand.slug, label: brand.name }))]}
          onChange={(event) => form.setData('brand', event.target.value)}
        />
        <Select
          label="Sort"
          value={form.data.sort}
          options={[
            { value: 'featured', label: 'Featured' },
            { value: 'newest', label: 'Newest' },
            { value: 'price_asc', label: 'Price: low to high' },
            { value: 'price_desc', label: 'Price: high to low' },
          ]}
          onChange={(event) => form.setData('sort', event.target.value)}
        />
        <Select
          label="Availability"
          value={form.data.availability}
          options={[
            { value: '', label: 'Any' },
            { value: 'in_stock', label: 'In stock' },
          ]}
          onChange={(event) => form.setData('availability', event.target.value)}
        />
        <Input label="Min price" inputMode="decimal" value={form.data.min_price} onChange={(event) => form.setData('min_price', event.target.value)} />
        <Input label="Max price" inputMode="decimal" value={form.data.max_price} onChange={(event) => form.setData('max_price', event.target.value)} />
        <div className="flex items-end gap-2">
          <Button type="submit" variant="gold" isLoading={form.processing} className="w-full">
            Apply filters
          </Button>
        </div>
      </form>

      <p className="mt-6 font-mono text-xs text-slate-400">
        Showing <strong className="text-slate-200">{products.total ?? products.data.length}</strong> products
      </p>

      {(products.data ?? []).length === 0 ? (
        <div className="mt-8 rounded-2xl border border-slate-800 bg-slate-900/40 p-12 text-center">
          <p className="text-sm font-semibold text-slate-200">No products match these filters.</p>
          <Button variant="secondary" className="mt-4" onClick={() => router.get('/shop')}>
            Reset
          </Button>
        </div>
      ) : (
        <div className="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {products.data.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      )}

      <Pagination pager={products} />
    </StorefrontLayout>
  );
}
