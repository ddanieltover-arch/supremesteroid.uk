import { Link } from '@inertiajs/react';

export function Breadcrumbs({ items }: { items?: Array<{ name: string; url: string }> }) {
  if (!items || items.length === 0) {
    return null;
  }

  return (
    <nav aria-label="Breadcrumb" className="mb-5 text-xs font-medium text-slate-500">
      <ol className="flex flex-wrap items-center gap-1.5">
        {items.map((crumb, index) => (
          <li key={`${crumb.url}-${crumb.name}`} className="flex items-center gap-1.5">
            {index > 0 ? <span className="text-slate-300" aria-hidden="true">/</span> : null}
            {index === items.length - 1 ? (
              <span className="text-slate-800" aria-current="page">
                {crumb.name}
              </span>
            ) : (
              <Link href={crumb.url} className="hover:text-blue-600 focus-visible:ring-2 focus-visible:ring-blue-600">
                {crumb.name}
              </Link>
            )}
          </li>
        ))}
      </ol>
    </nav>
  );
}
