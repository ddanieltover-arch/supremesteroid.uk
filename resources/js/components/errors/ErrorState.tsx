import StorefrontLayout from '@/Layouts/StorefrontLayout';
import { Link, usePage } from '@inertiajs/react';
import type { SeoProps } from '@/types';

export function ErrorState({
  title,
  message,
  seoTitle,
  seo,
}: {
  title: string;
  message: string;
  seoTitle: string;
  seo?: SeoProps;
}) {
  const pageSeo = usePage<{ seo?: SeoProps }>().props.seo;

  return (
    <StorefrontLayout seo={seo ?? pageSeo ?? { title: seoTitle, description: message, robots: 'noindex,nofollow' }}>
      <div className="mx-auto max-w-xl py-12 text-center">
        <p className="text-xs uppercase tracking-[0.2em] text-blue-600/80">Store notice</p>
        <h1 className="mt-3 text-3xl font-semibold text-slate-900">{title}</h1>
        <p className="mt-4 text-slate-500">{message}</p>
        <div className="mt-8 flex justify-center gap-3">
          <Link href="/" className="inline-flex min-h-11 items-center rounded-full bg-blue-600 px-5 text-sm font-semibold text-white">
            Return home
          </Link>
          <Link href="/shop" className="inline-flex min-h-11 items-center rounded-full border border-slate-300 px-5 text-sm text-slate-900">
            Continue shopping
          </Link>
        </div>
      </div>
    </StorefrontLayout>
  );
}
