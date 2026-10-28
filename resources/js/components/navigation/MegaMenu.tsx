import { Link } from '@inertiajs/react';
import { useEffect, useId, useRef } from 'react';
import type { CatalogueNav } from '@/types';

export function MegaMenu({
  open,
  onOpenChange,
  nav,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  nav?: CatalogueNav;
}) {
  const menuId = useId();
  const wrapRef = useRef<HTMLDivElement>(null);
  const buttonRef = useRef<HTMLButtonElement>(null);
  const categories = nav?.categories ?? [];
  const brands = (nav?.brands ?? []).slice(0, 8);
  const featured = nav?.featured ?? [];
  const arrivals = nav?.new_arrivals ?? [];

  useEffect(() => {
    if (!open) {
      return;
    }
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onOpenChange(false);
        buttonRef.current?.focus();
      }
    };
    const onPointer = (event: MouseEvent) => {
      if (wrapRef.current && !wrapRef.current.contains(event.target as Node)) {
        onOpenChange(false);
      }
    };
    document.addEventListener('keydown', onKey);
    document.addEventListener('mousedown', onPointer);
    return () => {
      document.removeEventListener('keydown', onKey);
      document.removeEventListener('mousedown', onPointer);
    };
  }, [open, onOpenChange]);

  return (
    <div className="relative" ref={wrapRef}>
      <button
        ref={buttonRef}
        type="button"
        className="rounded-lg px-3 py-2 text-sm font-semibold uppercase tracking-wide text-slate-800 hover:text-blue-600 focus-visible:ring-2 focus-visible:ring-blue-600"
        aria-expanded={open}
        aria-controls={menuId}
        aria-haspopup="true"
        onClick={() => onOpenChange(!open)}
        onKeyDown={(event) => {
          if (event.key === 'ArrowDown') {
            event.preventDefault();
            onOpenChange(true);
            window.requestAnimationFrame(() => {
              wrapRef.current?.querySelector<HTMLElement>('[role="menuitem"]')?.focus();
            });
          }
        }}
      >
        Shop
      </button>
      {open ? (
        <div id={menuId} className="absolute left-0 top-full z-50 mt-2 w-[46rem] max-w-[min(46rem,calc(100vw-2rem))] rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl" role="menu">
          <div className="grid grid-cols-2 gap-6 lg:grid-cols-4">
            <div>
              <p className="text-[11px] uppercase tracking-wider text-slate-500">Categories</p>
              <ul className="mt-2 space-y-1">
                {categories.map((category) => {
                  const children = Array.isArray(category.children) ? category.children : [];
                  return (
                  <li key={category.id}>
                    <Link href={`/category/${category.slug}`} className="block rounded px-2 py-1.5 text-slate-800 hover:text-blue-600 focus-visible:ring-2 focus-visible:ring-blue-600" role="menuitem" onClick={() => onOpenChange(false)}>
                      {category.name}
                    </Link>
                    {children.map((child) => (
                      <Link key={child.id} href={`/category/${child.slug}`} className="ml-3 block rounded px-2 py-1 text-xs text-slate-500 hover:text-blue-600" role="menuitem" onClick={() => onOpenChange(false)}>
                        {child.name}
                      </Link>
                    ))}
                  </li>
                  );
                })}
              </ul>
            </div>
            <div>
              <p className="text-[11px] uppercase tracking-wider text-slate-500">Brands</p>
              <ul className="mt-2 space-y-1">
                {brands.map((brand) => (
                  <li key={brand.id}>
                    <Link href={`/brand/${brand.slug}`} className="block rounded px-2 py-1.5 text-slate-800 hover:text-blue-600 focus-visible:ring-2 focus-visible:ring-blue-600" role="menuitem" onClick={() => onOpenChange(false)}>
                      {brand.name}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
            {featured.length > 0 ? (
              <div>
                <p className="text-[11px] uppercase tracking-wider text-slate-500">Featured</p>
                <ul className="mt-2 space-y-1">
                  {featured.map((item) => (
                    <li key={item.id}>
                      <Link href={`/product/${item.slug}`} className="block rounded px-2 py-1.5 text-slate-800 hover:text-blue-600" role="menuitem" onClick={() => onOpenChange(false)}>
                        {item.name}
                      </Link>
                    </li>
                  ))}
                </ul>
                <Link href="/shop?collection=featured" className="mt-3 block text-xs text-blue-600" onClick={() => onOpenChange(false)}>
                  View featured
                </Link>
              </div>
            ) : null}
            {arrivals.length > 0 ? (
              <div>
                <p className="text-[11px] uppercase tracking-wider text-slate-500">New</p>
                <ul className="mt-2 space-y-1">
                  {arrivals.map((item) => (
                    <li key={item.id}>
                      <Link href={`/product/${item.slug}`} className="block rounded px-2 py-1.5 text-slate-800 hover:text-blue-600" role="menuitem" onClick={() => onOpenChange(false)}>
                        {item.name}
                      </Link>
                    </li>
                  ))}
                </ul>
                <Link href="/shop?collection=new" className="mt-3 block text-xs text-blue-600" onClick={() => onOpenChange(false)}>
                  View new arrivals
                </Link>
              </div>
            ) : null}
          </div>
          <Link href="/shop" className="mt-5 inline-block text-sm text-slate-500 hover:text-slate-900" onClick={() => onOpenChange(false)}>
            View catalogue
          </Link>
        </div>
      ) : null}
    </div>
  );
}
