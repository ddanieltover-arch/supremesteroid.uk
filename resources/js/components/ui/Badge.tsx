import type { ReactNode } from 'react';
import clsx from 'clsx';

export function Badge({
  children,
  variant = 'default',
  className = '',
}: {
  children: ReactNode;
  variant?: 'default' | 'gold' | 'emerald' | 'amber' | 'rose' | 'neutral';
  className?: string;
}) {
  return (
    <span
      className={clsx(
        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wider',
        {
          'border border-slate-200 bg-slate-100 text-slate-700': variant === 'default',
          'bg-blue-600 text-white': variant === 'gold',
          'border border-emerald-200 bg-emerald-50 text-emerald-700': variant === 'emerald',
          'border border-blue-200 bg-blue-50 text-blue-700': variant === 'amber',
          'border border-rose-200 bg-rose-50 text-rose-700': variant === 'rose',
          'border border-slate-200 bg-white text-slate-600': variant === 'neutral',
        },
        className,
      )}
    >
      {children}
    </span>
  );
}
