import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { Link } from '@inertiajs/react';
import { Breadcrumbs } from '@/components/catalogue/Breadcrumbs';
import { PriceDisplay } from '@/components/catalogue/PriceDisplay';
import { formatMoney } from '@/lib/money';
import type { ProductDetail, SeoProps } from '@/types';

function specValue(product: ProductDetail, name: string): string {
  return product.specifications?.find((row) => row.name.toLowerCase() === name.toLowerCase())?.value ?? '—';
}

export default function Compare({ products, seo }: { products: ProductDetail[]; seo?: SeoProps }) {
  const rows = [
    { label: 'Brand', value: (product: ProductDetail) => product.brand?.name ?? '—' },
    { label: 'Format', value: (product: ProductDetail) => specValue(product, 'Format') },
    { label: 'Size', value: (product: ProductDetail) => specValue(product, 'Size') || specValue(product, 'PACK_SIZE') },
    { label: 'Availability', value: (product: ProductDetail) => product.stock?.label ?? '—' },
    { label: 'Price', value: (product: ProductDetail) => (product.pricing ? formatMoney(product.pricing.effective_price, product.pricing.currency) : '—') },
  ];

  return (
    <StorefrontLayout seo={seo}>
      <Breadcrumbs items={[{ name: 'Home', url: '/' }, { name: 'Compare', url: '/compare' }]} />
      <h1 className="text-3xl font-semibold text-slate-900">Compare products</h1>
      <p className="mt-2 max-w-2xl text-sm text-slate-500">
        Only approved, currently purchasable catalogue items are compared. Prices and availability come from the server.
      </p>

      {products.length === 0 ? (
        <div className="mt-8 rounded-2xl border border-slate-200 p-10 text-center">
          <p className="font-semibold text-slate-900">No comparable products</p>
          <p className="mt-2 text-sm text-slate-500">Select published products from the shop to compare their public specifications.</p>
          <Link href="/shop" className="mt-4 inline-flex text-blue-600">Return to shop</Link>
        </div>
      ) : (
        <div className="mt-8 overflow-x-auto">
          <table className="min-w-full border-collapse text-sm">
            <thead>
              <tr>
                <th className="w-40 p-3 text-left text-slate-500"> </th>
                {products.map((product) => (
                  <th key={product.id} className="min-w-48 p-3 text-left align-top">
                    {product.image_url ? <img src={product.image_url} alt={product.image_alt || product.name} className="mb-3 h-24 w-24 object-contain" /> : null}
                    <Link href={`/product/${product.slug}`} className="text-slate-900 hover:text-blue-600">{product.name}</Link>
                    <div className="mt-2">
                      <PriceDisplay pricing={product.pricing} size="sm" />
                    </div>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.label} className="border-t border-slate-200">
                  <th className="p-3 text-left text-slate-500">{row.label}</th>
                  {products.map((product) => (
                    <td key={`${product.id}-${row.label}`} className="p-3 text-slate-800">
                      {row.value(product)}
                    </td>
                  ))}
                </tr>
              ))}
              {(products[0]?.specifications ?? [])
                .filter((item) => !['Brand', 'Format', 'Size', 'PACK_SIZE'].includes(item.name))
                .map((item) => (
                  <tr key={item.name} className="border-t border-slate-200">
                    <th className="p-3 text-left text-slate-500">{item.name}</th>
                    {products.map((product) => (
                      <td key={`${product.id}-${item.name}`} className="p-3 text-slate-800">
                        {specValue(product, item.name)}
                      </td>
                    ))}
                  </tr>
                ))}
            </tbody>
          </table>
        </div>
      )}
    </StorefrontLayout>
  );
}
