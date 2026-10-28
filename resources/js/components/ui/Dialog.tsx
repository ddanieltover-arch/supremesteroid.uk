import { useEffect, useId, useRef, type ReactNode } from 'react';
import { Portal } from '@/components/ui/Portal';
import { usePresence } from '@/hooks/usePresence';
import clsx from 'clsx';

export function Dialog({
  open,
  title,
  onClose,
  children,
  size = 'lg',
}: {
  open: boolean;
  title: string;
  onClose: () => void;
  children: ReactNode;
  size?: 'md' | 'lg' | 'full';
}) {
  const titleId = useId();
  const panelRef = useRef<HTMLDivElement>(null);
  const previousFocus = useRef<HTMLElement | null>(null);
  const { mounted, visible } = usePresence(open, 220);

  useEffect(() => {
    if (!open || !visible) {
      return;
    }
    previousFocus.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const panel = panelRef.current;
    const focusable = panel?.querySelector<HTMLElement>('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    focusable?.focus();

    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        onClose();
      }
      if (event.key !== 'Tab' || !panel) {
        return;
      }
      const items = Array.from(panel.querySelectorAll<HTMLElement>('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')).filter(
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
  }, [open, visible, onClose]);

  if (!mounted) {
    return null;
  }

  return (
    <Portal>
      <div className="fixed inset-0 z-[60] flex items-end justify-center sm:items-center">
        <button
          type="button"
          className={clsx(
            'absolute inset-0 bg-slate-900/40 transition-opacity duration-200',
            visible ? 'opacity-100' : 'opacity-0',
          )}
          aria-label="Close dialog"
          onClick={onClose}
        />
        <div
          ref={panelRef}
          role="dialog"
          aria-modal="true"
          aria-labelledby={titleId}
          className={clsx(
            'relative z-10 max-h-[92vh] w-full overflow-y-auto border border-slate-200 bg-white p-5 shadow-2xl transition-all ease-out sm:rounded-2xl',
            visible ? 'translate-y-0 scale-100 opacity-100' : 'translate-y-3 scale-[0.98] opacity-0',
            size === 'full' ? 'h-full sm:h-auto sm:max-w-3xl' : size === 'md' ? 'sm:max-w-md' : 'sm:max-w-3xl',
          )}
          style={{ transitionDuration: '220ms' }}
        >
          <div className="mb-4 flex items-start justify-between gap-4">
            <h2 id={titleId} className="text-lg font-semibold text-slate-900">
              {title}
            </h2>
            <button
              type="button"
              onClick={onClose}
              className="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-500 transition-colors hover:text-slate-900 focus-visible:ring-2 focus-visible:ring-brand"
            >
              Close
            </button>
          </div>
          {children}
        </div>
      </div>
    </Portal>
  );
}
