import StorefrontLayout from '@/Layouts/StorefrontLayout';
import type { PropsWithChildren } from 'react';
import type { SeoProps } from '@/types';

export function AuthCard({ title, seo, children }: PropsWithChildren<{ title: string; seo: SeoProps }>) {
  return (
    <StorefrontLayout seo={{ robots: 'noindex,nofollow', ...seo }}>
      <div className="mx-auto max-w-md rounded-2xl border border-slate-200 bg-white/70 p-6 sm:p-8">
        <h1 className="text-3xl font-semibold text-slate-900">{title}</h1>
        <div className="mt-8">{children}</div>
      </div>
    </StorefrontLayout>
  );
}
