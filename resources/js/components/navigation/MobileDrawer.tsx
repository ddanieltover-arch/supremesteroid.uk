import { Link, usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect, useId, useRef } from 'react';
import { Portal } from '@/components/ui/Portal';
import type { CatalogueNav } from '@/types';

type Policy = { title: string; slug: string; url: string };

export function MobileDrawer({
  open,
  onClose,
  nav,
}: {
  open: boolean;
  onClose: () => void;
  nav?: CatalogueNav;
}) {
  const titleId = useId();
  const panelRef = useRef<HTMLDivElement>(null);
  const previousFocus = useRef<HTMLElement | null>(null);
  const policies = usePage<{ chrome?: { policies?: Policy[] } }>().props.chrome?.policies ?? [];

  useEffect(() => {
    if (!open) {
      return;
    }
    previousFocus.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const panel = panelRef.current;
    panel?.querySelector<HTMLElement>('button, [href], summary')?.focus();

    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        onClose();
        return;
      }
      if (event.key !== 'Tab' || !panel) {
        return;
      }
      const items = Array.from(panel.querySelectorAll<HTMLElement>('button, [href], input, select, textarea, summary, [tabindex]:not([tabindex="-1"])')).filter(
        (item) => !item.hasAttribute('disabled'),
      );
      if (items.length === 0) {
        return;
      }
      const first = items[0];
      const last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };

    document.addEventListener('keydown', onKey);
    const original = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = original;
      previousFocus.current?.focus();
    };
  }, [open, onClose]);

  if (!open) {
    return null;
  }

  return (
    <Portal>
      <div className="fixed inset-0 z-[60] lg:hidden">
        <button
          type="button"
          className="absolute inset-0 bg-slate-900/40"
          aria-label="Close menu"
          onClick={onClose}
        />
        <div
          ref={panelRef}
          role="dialog"
          aria-modal="true"
          aria-labelledby={titleId}
          className="absolute inset-y-0 right-0 flex w-[min(100%,22rem)] flex-col border-l border-slate-200 bg-white shadow-2xl"
        >
          <div className="flex items-center justify-between border-b border-slate-200 px-4 py-4">
            <h2 id={titleId} className="text-sm font-semibold text-slate-900">
              Menu
            </h2>
            <button
              type="button"
              className="rounded-lg border border-slate-200 p-2 text-slate-800 transition-colors hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-brand"
              onClick={onClose}
              aria-label="Close menu"
            >
              <X className="h-5 w-5" />
            </button>
          </div>
          <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Mobile">
            <Link href="/shop" className="block min-h-11 rounded-lg px-3 py-2 text-slate-900 transition-colors hover:bg-slate-50" onClick={onClose}>
              Shop
            </Link>
            {(nav?.categories ?? []).length > 0 ? (
              <details>
                <summary className="cursor-pointer rounded-lg px-3 py-2 text-slate-800">Categories</summary>
                <div className="ml-2 space-y-1">
                  {(nav?.categories ?? []).map((category) => (
                    <details key={category.id}>
                      <summary className="cursor-pointer rounded-lg px-3 py-2 text-slate-600">{category.name}</summary>
                      <div className="ml-2">
                        <Link href={`/category/${category.slug}`} className="block min-h-11 rounded-lg px-3 py-2 text-sm text-slate-500" onClick={onClose}>
                          View {category.name}
                        </Link>
                        {(Array.isArray(category.children) ? category.children : []).map((child) => (
                          <Link key={child.id} href={`/category/${child.slug}`} className="block min-h-11 rounded-lg px-3 py-2 text-sm text-slate-500" onClick={onClose}>
                            {child.name}
                          </Link>
                        ))}
                      </div>
                    </details>
                  ))}
                </div>
              </details>
            ) : null}
            {(nav?.brands ?? []).length > 0 ? (
              <details>
                <summary className="cursor-pointer rounded-lg px-3 py-2 text-slate-800">Brands</summary>
                <div className="ml-2">
                  {(nav?.brands ?? []).map((brand) => (
                    <Link key={brand.id} href={`/brand/${brand.slug}`} className="block min-h-11 rounded-lg px-3 py-2 text-sm text-slate-500" onClick={onClose}>
                      {brand.name}
                    </Link>
                  ))}
                </div>
              </details>
            ) : null}
            <Link href="/account" className="block min-h-11 rounded-lg px-3 py-2 text-slate-900 transition-colors hover:bg-slate-50" onClick={onClose}>
              Account
            </Link>
            <Link href="/account/wishlist" className="block min-h-11 rounded-lg px-3 py-2 text-slate-900 transition-colors hover:bg-slate-50" onClick={onClose}>
              Wishlist
            </Link>
            <Link href="/contact" className="block min-h-11 rounded-lg px-3 py-2 text-slate-900 transition-colors hover:bg-slate-50" onClick={onClose}>
              Contact
            </Link>
            {policies.length > 0 ? (
              <details>
                <summary className="cursor-pointer rounded-lg px-3 py-2 text-slate-800">Policies</summary>
                <div className="ml-2">
                  {policies.map((policy) => (
                    <Link key={policy.slug} href={policy.url} className="block min-h-11 rounded-lg px-3 py-2 text-sm text-slate-500" onClick={onClose}>
                      {policy.title}
                    </Link>
                  ))}
                </div>
              </details>
            ) : null}
          </nav>
        </div>
      </div>
    </Portal>
  );
}
