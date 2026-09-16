import { Link } from '@inertiajs/react';
import type { Paginated } from '@/types';

export function Pagination({ pager }: { pager: Paginated<unknown> }) {
  if (!pager.links || pager.links.length <= 3) {
    return null;
  }

  return (
    <nav className="mt-8 flex flex-wrap justify-center gap-2" aria-label="Pagination">
      {pager.links.map((link, index) => {
        const label = link.label.replace(/&laquo;|&raquo;/g, (token) => (token.includes('laquo') ? 'Previous' : 'Next'));
        if (!link.url) {
          return (
            <span key={`${label}-${index}`} className="rounded-lg border border-slate-800 px-3 py-1.5 text-xs text-slate-500">
              {label}
            </span>
          );
        }
        return (
          <Link
            key={`${label}-${index}`}
            href={link.url}
            preserveState
            preserveScroll
            className={`rounded-lg border px-3 py-1.5 text-xs ${
              link.active ? 'border-amber-500/40 bg-amber-500 text-slate-950' : 'border-slate-800 text-slate-300 hover:border-amber-500/40'
            }`}
            aria-current={link.active ? 'page' : undefined}
          >
            {label}
          </Link>
        );
      })}
    </nav>
  );
}
