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
  const legalSlugs = ['terms-conditions', 'privacy-policy', 'cookies'];
  const legal = policies.filter((item) => legalSlugs.includes(item.slug)).length > 0
    ? policies.filter((item) => legalSlugs.includes(item.slug))
    : [
        { title: 'Terms & Conditions', slug: 'terms-conditions', url: '/terms-conditions' },
        { title: 'Privacy Policy', slug: 'privacy-policy', url: '/privacy-policy' },
        { title: 'Cookie Policy', slug: 'cookies', url: '/cookies' },
      ];

  return (
    <footer className="mt-20 border-t border-slate-200 bg-white text-sm text-slate-600">
      <div className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-5">
          <div>
            <h2 className="text-sm font-semibold text-slate-900">Contact</h2>
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
              <a href={`mailto:${page.props.store?.support_email ?? 'info@supremesteroid.uk'}`} className="hover:text-blue-600">
                {page.props.store?.support_email ?? 'info@supremesteroid.uk'}
              </a>
            </p>
            {page.props.store?.support_phone ? <p className="mt-2">{page.props.store.support_phone}</p> : null}
          </div>

          <div>
            <h2 className="text-sm font-semibold text-slate-900">Shop</h2>
            <ul className="mt-3 space-y-2">
              <li><Link href="/shop" className="hover:text-blue-600">Catalogue</Link></li>
              <li><Link href="/shop?collection=featured" className="hover:text-blue-600">Featured</Link></li>
              <li><Link href="/shop?collection=new" className="hover:text-blue-600">New arrivals</Link></li>
              <li><Link href="/about-us" className="hover:text-blue-600">About Us</Link></li>
            </ul>
          </div>

          <div>
            <h2 className="text-sm font-semibold text-slate-900">Customer Service</h2>
            <ul className="mt-3 space-y-2">
              <li><Link href="/contact-us" className="hover:text-blue-600">Contact Us</Link></li>
              <li><Link href="/delivery-info" className="hover:text-blue-600">Delivery &amp; Shipping</Link></li>
              <li><Link href="/returns-refunds" className="hover:text-blue-600">Returns &amp; Refunds</Link></li>
              <li><Link href="/faq" className="hover:text-blue-600">FAQ</Link></li>
              <li><Link href="/payment-methods" className="hover:text-blue-600">Payment Methods</Link></li>
              <li><Link href="/lab-tests" className="hover:text-blue-600">Lab Tests &amp; Authenticity</Link></li>
              <li><Link href="/reviews" className="hover:text-blue-600">Customer Reviews</Link></li>
            </ul>
          </div>

          <div>
            <h2 className="text-sm font-semibold text-slate-900">Guides & Articles</h2>
            <ul className="mt-3 space-y-2">
              <li><Link href="/blog" className="hover:text-blue-600">Articles & Research</Link></li>
              <li><Link href="/bpc-157-uk" className="hover:text-blue-600">BPC 157 UK Guide</Link></li>
              <li><Link href="/thebsl24" className="hover:text-blue-600">The BSL 24 Partnership</Link></li>
              <li><Link href="/xmas-updates" className="hover:text-blue-600">Holiday Updates</Link></li>
            </ul>
          </div>

          <div>
            <h2 className="text-sm font-semibold text-slate-900">Policies</h2>
            <ul className="mt-3 space-y-2">
              {legal.map((item) => (
                <li key={item.slug}>
                  <Link href={item.url} className="hover:text-blue-600">{item.title}</Link>
                </li>
              ))}
            </ul>
            {Object.keys(social).length > 0 ? (
              <ul className="mt-6 flex flex-wrap gap-3">
                {Object.entries(social).map(([network, url]) => (
                  <li key={network}>
                    <a href={url} className="capitalize hover:text-blue-600" rel="noreferrer">
                      {network}
                    </a>
                  </li>
                ))}
              </ul>
            ) : null}
          </div>
        </div>

        <div className="mt-12 border-t border-slate-200 pt-6 text-xs text-slate-500">
          <p>© {new Date().getFullYear()} {storeName}</p>
        </div>
      </div>
    </footer>
  );
}
