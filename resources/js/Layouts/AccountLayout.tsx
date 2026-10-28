import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import type { SeoProps } from '@/types';

const links = [
  { href: '/account', label: 'Overview' },
  { href: '/account/orders', label: 'Orders' },
  { href: '/account/wishlist', label: 'Wishlist' },
  { href: '/account/addresses', label: 'Addresses' },
  { href: '/account#profile', label: 'Profile' },
  { href: '/account#security', label: 'Security' },
];

export function AccountLayout({
  children,
  title,
  seo,
}: PropsWithChildren<{ title: string; seo: SeoProps }>) {
  const { url } = usePage();

  return (
    <StorefrontLayout seo={seo}>
      <div className="lg:grid lg:grid-cols-[14rem_minmax(0,1fr)] lg:gap-10">
        <aside className="mb-8 lg:mb-0">
          <p className="text-xs uppercase tracking-[0.2em] text-blue-600/80">Account</p>
          <h1 className="mt-2 text-3xl font-semibold text-slate-900">{title}</h1>
          <nav aria-label="Account" className="mt-6 flex gap-2 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:thin] lg:block lg:space-y-1">
            {links.map((link) => {
              const active = link.href === '/account' ? url === '/account' || url.startsWith('/account#') : url.startsWith(link.href);
              return (
                <Link
                  key={link.href}
                  href={link.href}
                  className={`inline-flex min-h-11 shrink-0 items-center rounded-lg px-3 py-2 text-sm ${active ? 'bg-slate-50 text-blue-600' : 'text-slate-500 hover:text-slate-900'}`}
                >
                  {link.label}
                </Link>
              );
            })}
          </nav>
        </aside>
        <div>{children}</div>
      </div>
    </StorefrontLayout>
  );
}
