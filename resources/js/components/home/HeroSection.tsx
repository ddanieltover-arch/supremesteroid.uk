import { Link, usePage } from '@inertiajs/react';
import clsx from 'clsx';
import type { MouseEvent } from 'react';
import type { HomepagePayload } from '@/types';

const ctaBaseClass =
  'inline-flex min-h-12 items-center justify-center rounded-md px-6 text-base font-semibold transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2';

function inPageHash(url: string): string | null {
  if (url.startsWith('#')) {
    return url;
  }

  // Same-document anchors like "/#categories" must not go through Inertia —
  // Link would XHR-reload "/" and often never scroll to the target.
  if (url.startsWith('/#')) {
    return url.slice(1);
  }

  try {
    const parsed = new URL(url, typeof window !== 'undefined' ? window.location.origin : 'http://localhost');
    if (typeof window !== 'undefined' && parsed.pathname === window.location.pathname && parsed.hash) {
      return parsed.hash;
    }
  } catch {
    // Fall through
  }

  return null;
}

function scrollToHash(hash: string) {
  const id = decodeURIComponent(hash.replace(/^#/, ''));
  if (!id) {
    return;
  }

  const target = document.getElementById(id);
  if (!target) {
    return;
  }

  target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  if (window.location.hash !== hash) {
    history.pushState(null, '', hash);
  }
}

export function HeroSection({ hero }: { hero: HomepagePayload['hero'] }) {
  const page = usePage<{ store?: { name?: string; logo_url?: string } }>();
  const logoUrl = page.props.store?.logo_url ?? '/assets/branding/supreme-steroids-logo.jpg';
  const storeName = page.props.store?.name ?? 'Supreme Steroids';
  const secondaryHash = inPageHash(hero.secondary_cta.url);

  const onSecondaryClick = (event: MouseEvent<HTMLAnchorElement>) => {
    if (!secondaryHash) {
      return;
    }

    event.preventDefault();
    scrollToHash(secondaryHash);
  };

  return (
    <section
      aria-labelledby="hero-heading"
      className="relative left-1/2 w-screen -translate-x-1/2 -mt-8 mb-4 overflow-hidden sm:-mt-10 sm:mb-8"
    >
      <div
        className="pointer-events-none absolute inset-0 bg-gradient-to-br from-[#eef5ff] via-[#f7fafc] to-[#f9f9f9]"
        aria-hidden="true"
      />
      <div
        className="pointer-events-none absolute inset-0 opacity-[0.45]"
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

      <div className="relative z-10 mx-auto flex max-w-7xl flex-col items-center px-4 py-14 text-center sm:px-6 sm:py-20 lg:px-8 lg:py-24">
        <img
          src={logoUrl}
          alt={storeName}
          className="h-20 w-auto max-w-[220px] object-contain sm:h-24 sm:max-w-[280px]"
          fetchPriority="high"
          loading="eager"
          decoding="async"
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
            prefetch="mount"
            cacheFor="2m"
            className={clsx(
              ctaBaseClass,
              'bg-brand text-white hover:bg-brand-dark',
            )}
          >
            {hero.primary_cta.label}
          </Link>
          {secondaryHash ? (
            <a
              href={secondaryHash}
              onClick={onSecondaryClick}
              className={clsx(
                ctaBaseClass,
                'border border-slate-300 bg-white text-slate-800 hover:border-brand hover:text-brand',
              )}
            >
              {hero.secondary_cta.label}
            </a>
          ) : (
            <Link
              href={hero.secondary_cta.url}
              prefetch="hover"
              cacheFor="2m"
              className={clsx(
                ctaBaseClass,
                'border border-slate-300 bg-white text-slate-800 hover:border-brand hover:text-brand',
              )}
            >
              {hero.secondary_cta.label}
            </Link>
          )}
        </div>
      </div>
    </section>
  );
}
