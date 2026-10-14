import { Link } from '@inertiajs/react';
import type { HomepagePayload } from '@/types';

export function EditorialFeature({ editorial }: { editorial: NonNullable<HomepagePayload['editorial']> }) {
  return (
    <section aria-labelledby="editorial-heading" className="mt-16 overflow-hidden rounded-3xl border border-slate-800 bg-slate-950/60">
      <div className="grid lg:grid-cols-2">
        <div className="p-6 sm:p-10">
          <p className="text-xs font-semibold uppercase tracking-[0.2em] text-amber-400/80">{editorial.label}</p>
          <h2 id="editorial-heading" className="mt-3 text-2xl font-semibold text-white sm:text-3xl">
            {editorial.heading}
          </h2>
          <p className="mt-4 max-w-xl text-sm leading-relaxed text-slate-400">{editorial.body}</p>
          <Link href={editorial.cta.url} className="mt-6 inline-flex min-h-12 items-center text-sm font-semibold text-amber-300">
            {editorial.cta.label}
          </Link>
        </div>
        <div className="min-h-56 bg-slate-900">
          {editorial.image_url ? (
            <img src={editorial.image_url} alt="" className="h-full w-full object-cover" loading="lazy" />
          ) : (
            <div className="flex h-full items-center justify-center border-t border-slate-800 text-xs uppercase tracking-[0.2em] text-slate-600 lg:border-l lg:border-t-0">
              Editorial
            </div>
          )}
        </div>
      </div>
    </section>
  );
}
