import { Link, usePage } from '@inertiajs/react';

type Policy = { title: string; slug: string; url: string };

export function Footer() {
  const page = usePage<{
    store?: {
      name?: string;
      support_email?: string;
      support_phone?: string | null;
      address?: {
        street?: string;
        locality?: string;
        city?: string;
        postal_code?: string;
        country?: string;
      };
    };
    chrome?: {
      policies?: Policy[];
      social?: Record<string, string>;
    };
  }>();
  const storeName = page.props.store?.name ?? 'Supreme Steroids';
  const address = page.props.store?.address;
  const policies = page.props.chrome?.policies ?? [];
  const social = Object.fromEntries(Object.entries(page.props.chrome?.social ?? {}).filter(([, url]) => Boolean(url)));
  const company = policies.filter((item) => ['about', 'contact'].includes(item.slug));
  const legal = policies.filter((item) => !['about', 'contact'].includes(item.slug));

  return (
    <footer className="mt-20 border-t border-slate-900 bg-slate-950 text-sm text-slate-400">
      <div className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-5">
          <div>
            <h2 className="text-sm font-semibold text-white">Contact</h2>
            {address ? (
              <p className="mt-3 leading-relaxed">
                {address.street}
                <br />
                {address.locality}
                <br />
                {address.city} {address.postal_code}
                <br />
                {address.country}
              </p>
            ) : null}
            <p className="mt-4">
              <a href={`mailto:${page.props.store?.support_email ?? 'info@supremesteroid.uk'}`} className="hover:text-amber-300">
                {page.props.store?.support_email ?? 'info@supremesteroid.uk'}
              </a>
            </p>
            {page.props.store?.support_phone ? <p className="mt-2">{page.props.store.support_phone}</p> : null}
          </div>

          <div>
            <h2 className="text-sm font-semibold text-white">Shop</h2>
            <ul className="mt-3 space-y-2">
              <li><Link href="/shop" className="hover:text-amber-300">Catalogue</Link></li>
              <li><Link href="/shop?collection=featured" className="hover:text-amber-300">Featured</Link></li>
              <li><Link href="/shop?collection=new" className="hover:text-amber-300">New arrivals</Link></li>
            </ul>
          </div>

          <div>
            <h2 className="text-sm font-semibold text-white">Customer service</h2>
            <ul className="mt-3 space-y-2">
              <li><Link href="/contact" className="hover:text-amber-300">Contact</Link></li>
              <li><Link href="/shipping" className="hover:text-amber-300">Shipping</Link></li>
              <li><Link href="/returns" className="hover:text-amber-300">Returns</Link></li>
              <li><Link href="/faq" className="hover:text-amber-300">FAQ</Link></li>
            </ul>
          </div>

          <div>
            <h2 className="text-sm font-semibold text-white">Company</h2>
            <ul className="mt-3 space-y-2">
              {company.map((item) => (
                <li key={item.slug}>
                  <Link href={item.url} className="hover:text-amber-300">{item.title}</Link>
                </li>
              ))}
            </ul>
          </div>

          <div>
            <h2 className="text-sm font-semibold text-white">Policies</h2>
            <ul className="mt-3 space-y-2">
              {legal.map((item) => (
                <li key={item.slug}>
                  <Link href={item.url} className="hover:text-amber-300">{item.title}</Link>
                </li>
              ))}
            </ul>
            {Object.keys(social).length > 0 ? (
              <ul className="mt-6 flex flex-wrap gap-3">
                {Object.entries(social).map(([network, url]) => (
                  <li key={network}>
                    <a href={url} className="capitalize hover:text-amber-300" rel="noreferrer">
                      {network}
                    </a>
                  </li>
                ))}
              </ul>
            ) : null}
          </div>
        </div>

        <div className="mt-12 border-t border-slate-900 pt-6 text-xs text-slate-500">
          <p>© {new Date().getFullYear()} {storeName}</p>
        </div>
      </div>
    </footer>
  );
}
