import { Link, usePage } from '@inertiajs/react';
import clsx from 'clsx';
import type { HomepagePayload } from '@/types';

export function HeroSection({ hero }: { hero: HomepagePayload['hero'] }) {
  const page = usePage<{ store?: { name?: string; logo_url?: string } }>();
  const logoUrl = page.props.store?.logo_url ?? '/assets/branding/supreme-steroids-logo.jpg';
  const storeName = page.props.store?.name ?? 'Supreme Steroids';

  return (
    <section
      aria-labelledby="hero-heading"
      className="relative left-1/2 w-screen -translate-x-1/2 -mt-8 mb-4 overflow-hidden sm:-mt-10 sm:mb-8"
    >
      <div className="absolute inset-0 bg-gradient-to-br from-[#eef5ff] via-[#f7fafc] to-[#f9f9f9]" aria-hidden="true" />
      <div
        className="absolute inset-0 opacity-[0.45]"
        aria-hidden="true"
        style={{
          backgroundImage:
            'radial-gradient(ellipse 70% 50% at 15% 20%, rgb(30 111 217 / 0.14), transparent 55%), radial-gradient(ellipse 50% 40% at 90% 70%, rgb(30 111 217 / 0.08), transparent 50%)',
        }}
      />
      <div
        className="pointer-events-none absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-[#f9f9f9] to-transparent"
        aria-hidden="true"
      />

      <div className="relative mx-auto flex max-w-7xl flex-col items-center px-4 py-14 text-center sm:px-6 sm:py-20 lg:px-8 lg:py-24">
        <img
          src={logoUrl}
          alt={storeName}
          className="h-20 w-auto max-w-[220px] object-contain sm:h-24 sm:max-w-[280px]"
        />

        <p className="mt-6 text-xs font-semibold uppercase tracking-[0.22em] text-brand">
          {hero.eyebrow}
        </p>

        <h1
          id="hero-heading"
          className="mt-3 max-w-3xl text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl lg:text-5xl"
        >
          {hero.headline}
        </h1>

        <p className="mt-4 max-w-2xl text-base leading-relaxed text-slate-600 sm:text-lg">
          {hero.description}
        </p>

        <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
          <Link
            href={hero.primary_cta.url}
            className={clsx(
              'inline-flex min-h-12 items-center justify-center rounded-md bg-brand px-6 text-base font-semibold text-white',
              'transition-colors duration-200 hover:bg-brand-dark',
              'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2',
            )}
          >
            {hero.primary_cta.label}
          </Link>
          <Link
            href={hero.secondary_cta.url}
            className={clsx(
              'inline-flex min-h-12 items-center justify-center rounded-md border border-slate-300 bg-white px-6 text-base font-semibold text-slate-800',
              'transition-colors duration-200 hover:border-brand hover:text-brand',
              'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2',
            )}
          >
            {hero.secondary_cta.label}
          </Link>
        </div>
      </div>
    </section>
  );
}
