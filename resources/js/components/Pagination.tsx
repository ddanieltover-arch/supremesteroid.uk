import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { Paginated } from '@/types';

function pageLabel(label: string) {
  return label
    .replace(/&laquo;|&raquo;/g, '')
    .replace(/Previous/i, 'Previous')
    .replace(/Next/i, 'Next')
    .trim();
}

export function Pagination({ pager }: { pager: Paginated<unknown> }) {
  const current = pager.current_page ?? 1;
  const last = pager.last_page ?? 1;
  const links = Array.isArray(pager.links) ? pager.links : [];

  if (last <= 1 && links.length <= 3) {
    return null;
  }

  const numbered = links.filter((link) => {
    const label = pageLabel(link.label);
    return label !== 'Previous' && label !== 'Next' && label !== '';
  });

  return (
    <nav className="mt-10 flex flex-col items-center gap-4 border-t border-slate-200 pt-8" aria-label="Pagination">
      <p className="text-sm text-slate-500">
        Page <span className="font-semibold text-slate-900">{current}</span>
        {last > 1 ? (
          <>
            {' '}
            of <span className="font-semibold text-slate-900">{last}</span>
          </>
        ) : null}
        {typeof pager.total === 'number' ? (
          <>
            <span className="mx-2 text-slate-300">·</span>
            {pager.total.toLocaleString('en-GB')} products
          </>
        ) : null}
      </p>

      <div className="flex flex-wrap items-center justify-center gap-2">
        {pager.prev_page_url ? (
          <Link
            href={pager.prev_page_url}
            preserveState
            preserveScroll
            className="inline-flex min-h-10 items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 hover:border-blue-300 hover:text-blue-700"
          >
            <ChevronLeft className="h-4 w-4" aria-hidden="true" />
            Previous
          </Link>
        ) : (
          <span className="inline-flex min-h-10 items-center gap-1 rounded-lg border border-slate-100 px-3 text-sm text-slate-300">
            <ChevronLeft className="h-4 w-4" aria-hidden="true" />
            Previous
          </span>
        )}

        <div className="flex flex-wrap justify-center gap-1.5">
          {(numbered.length > 0 ? numbered : [{ url: null, label: String(current), active: true }]).map((link, index) => {
            const label = pageLabel(link.label);
            if (!link.url) {
              return (
                <span
                  key={`${label}-${index}`}
                  className={`inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border px-2 text-sm ${
                    link.active ? 'border-blue-600 bg-blue-600 font-semibold text-white' : 'border-slate-100 text-slate-400'
                  }`}
                >
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
                className={`inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border px-2 text-sm font-medium ${
                  link.active
                    ? 'border-blue-600 bg-blue-600 text-white'
                    : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300 hover:text-blue-700'
                }`}
                aria-current={link.active ? 'page' : undefined}
              >
                {label}
              </Link>
            );
          })}
        </div>

        {pager.next_page_url ? (
          <Link
            href={pager.next_page_url}
            preserveState
            preserveScroll
            className="inline-flex min-h-10 items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 hover:border-blue-300 hover:text-blue-700"
          >
            Next
            <ChevronRight className="h-4 w-4" aria-hidden="true" />
          </Link>
        ) : (
          <span className="inline-flex min-h-10 items-center gap-1 rounded-lg border border-slate-100 px-3 text-sm text-slate-300">
            Next
            <ChevronRight className="h-4 w-4" aria-hidden="true" />
          </span>
        )}
      </div>
    </nav>
  );
}
