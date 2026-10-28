import { ArrowUp } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import clsx from 'clsx';
import { readCompareIds } from '@/lib/compare';

const SHOW_AFTER_PX = 320;

export function BackToTop() {
  const { url } = usePage();
  const [visible, setVisible] = useState(false);
  const [compareOpen, setCompareOpen] = useState(false);

  useEffect(() => {
    const updateVisibility = () => {
      const shouldShow = window.scrollY > SHOW_AFTER_PX;
      setVisible((current) => (current === shouldShow ? current : shouldShow));
    };

    updateVisibility();
    window.addEventListener('scroll', updateVisibility, { passive: true });
    return () => window.removeEventListener('scroll', updateVisibility);
  }, [url]);

  useEffect(() => {
    const sync = (event?: Event) => {
      const detail = event instanceof CustomEvent ? event.detail : null;
      const ids = Array.isArray(detail) ? detail : readCompareIds();
      setCompareOpen(ids.length > 0);
    };

    sync();
    window.addEventListener('ss:compare', sync);
    return () => window.removeEventListener('ss:compare', sync);
  }, []);

  const scrollToTop = () => {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({
      top: 0,
      behavior: prefersReducedMotion ? 'auto' : 'smooth',
    });

    const main = document.getElementById('main-content');
    if (main instanceof HTMLElement) {
      if (!main.hasAttribute('tabindex')) {
        main.setAttribute('tabindex', '-1');
      }
      main.focus({ preventScroll: true });
    }
  };

  if (!visible) {
    return null;
  }

  return (
    <button
      type="button"
      onClick={scrollToTop}
      aria-label="Back to top"
      className={clsx(
        'fixed z-40 flex h-12 w-12 items-center justify-center rounded-full bg-blue-600 text-white shadow-lg shadow-blue-500/25 transition duration-200',
        'right-[max(1rem,env(safe-area-inset-right))] hover:bg-blue-500',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 focus-visible:ring-offset-white',
        // Sit above the mobile bottom nav (and compare bar when open); desktop stays near the corner.
        compareOpen
          ? 'bottom-[calc(6.5rem+env(safe-area-inset-bottom))] lg:bottom-24'
          : 'bottom-[calc(4.5rem+env(safe-area-inset-bottom))] lg:bottom-[max(1.25rem,env(safe-area-inset-bottom))]',
      )}
    >
      <ArrowUp className="h-5 w-5" aria-hidden="true" />
    </button>
  );
}
