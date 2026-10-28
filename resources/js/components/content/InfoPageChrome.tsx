import { Link } from '@inertiajs/react';
import { ArrowLeft, ChevronRight, Mail, MessageCircle, ShieldCheck, type LucideIcon } from 'lucide-react';
import type { PropsWithChildren, ReactNode } from 'react';

export type InfoNavLink = {
  href: string;
  label: string;
  slug?: string;
};

const DEFAULT_LINKS: InfoNavLink[] = [
  { href: '/about-us', label: 'About Us', slug: 'about-us' },
  { href: '/contact-us', label: 'Contact Us', slug: 'contact-us' },
  { href: '/faq', label: 'FAQ', slug: 'faq' },
  { href: '/delivery-info', label: 'Shipping & Delivery', slug: 'delivery-info' },
  { href: '/returns-refunds', label: 'Returns & Refunds', slug: 'returns-refunds' },
  { href: '/payment-methods', label: 'Payment Methods', slug: 'payment-methods' },
  { href: '/lab-tests', label: 'Lab Tests', slug: 'lab-tests' },
  { href: '/bpc-157-uk', label: 'BPC 157 UK Guide', slug: 'bpc-157-uk' },
  { href: '/thebsl24', label: 'The BSL 24', slug: 'thebsl24' },
  { href: '/blog', label: 'Articles & Blog' },
  { href: '/terms-conditions', label: 'Terms & Conditions', slug: 'terms-conditions' },
  { href: '/privacy-policy', label: 'Privacy Policy', slug: 'privacy-policy' },
];

type InfoPageChromeProps = PropsWithChildren<{
  title: string;
  breadcrumbParent?: string;
  breadcrumbParentHref?: string;
  badgeLabel?: string;
  badgeIcon?: LucideIcon;
  extraBadges?: ReactNode;
  subtitle?: string;
  heroMeta?: ReactNode;
  activeSlug?: string | null;
  activeHref?: string | null;
  backHref?: string;
  backLabel?: string;
  sidebarExtra?: ReactNode;
  links?: InfoNavLink[];
}>;

export function InfoPageChrome({
  title,
  breadcrumbParent = 'Information',
  breadcrumbParentHref,
  badgeLabel = 'Verified Reference',
  badgeIcon: BadgeIcon = ShieldCheck,
  extraBadges,
  subtitle = 'Official store guidelines, verified product documentation, and policy standards for Supreme Steroids UK.',
  heroMeta,
  activeSlug = null,
  activeHref = null,
  backHref = '/shop',
  backLabel = 'Return to Shop',
  sidebarExtra,
  links = DEFAULT_LINKS,
  children,
}: InfoPageChromeProps) {
  return (
    <div className="mx-auto max-w-5xl">
      <nav className="mb-6 flex items-center gap-2 text-xs text-slate-500" aria-label="Breadcrumb">
        <Link href="/" className="hover:text-blue-600">
          Home
        </Link>
        <ChevronRight className="h-3 w-3 text-slate-400" aria-hidden />
        {breadcrumbParentHref ? (
          <Link href={breadcrumbParentHref} className="hover:text-blue-600">
            {breadcrumbParent}
          </Link>
        ) : (
          <span className="text-slate-400">{breadcrumbParent}</span>
        )}
        <ChevronRight className="h-3 w-3 text-slate-400" aria-hidden />
        <span className="line-clamp-1 font-medium text-slate-900">{title}</span>
      </nav>

      <div className="mb-8 overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 p-8 text-white shadow-xl sm:p-10">
        <div className="flex flex-wrap items-center gap-2">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-500/20 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-blue-300">
            <BadgeIcon className="h-3.5 w-3.5" aria-hidden />
            {badgeLabel}
          </span>
          {extraBadges}
        </div>
        <h1 className="mt-4 text-2xl font-extrabold tracking-tight text-white sm:text-4xl">{title}</h1>
        {subtitle ? <p className="mt-3 max-w-2xl text-sm leading-relaxed text-slate-300">{subtitle}</p> : null}
        {heroMeta}
      </div>

      <div className="grid gap-8 lg:grid-cols-4">
        <div className="lg:col-span-3">
          <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">{children}</div>

          <div className="mt-6 flex items-center justify-between">
            <Link href={backHref} className="inline-flex items-center gap-2 text-sm font-semibold text-blue-600 hover:text-blue-700">
              <ArrowLeft className="h-4 w-4" aria-hidden />
              {backLabel}
            </Link>
          </div>
        </div>

        <div className="space-y-6 lg:col-span-1">
          <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">Quick Links</h3>
            <ul className="mt-3 space-y-2 text-sm font-medium">
              {links.map((link) => {
                const active =
                  Boolean(activeHref && link.href === activeHref) ||
                  Boolean(link.slug && activeSlug && link.slug === activeSlug);
                return (
                  <li key={link.href}>
                    <Link
                      href={link.href}
                      className={`block rounded-lg px-3 py-2 transition-colors ${
                        active ? 'bg-blue-50 font-bold text-blue-700' : 'text-slate-700 hover:bg-slate-50'
                      }`}
                    >
                      {link.label}
                    </Link>
                  </li>
                );
              })}
            </ul>
          </div>

          {sidebarExtra}

          <div className="rounded-2xl border border-slate-200 bg-gradient-to-br from-blue-50 to-indigo-50 p-5 text-slate-800 shadow-sm">
            <h4 className="flex items-center gap-1.5 text-sm font-bold text-blue-950">
              <MessageCircle className="h-4 w-4 text-blue-600" aria-hidden />
              Need Order Support?
            </h4>
            <p className="mt-2 text-xs leading-relaxed text-slate-600">
              Our support team is available to assist with product information, tracked shipments, and payments.
            </p>
            <div className="mt-4">
              <a
                href="mailto:info@supremesteroid.uk"
                className="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50"
              >
                <Mail className="h-3.5 w-3.5 text-slate-500" aria-hidden />
                Email Support
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
