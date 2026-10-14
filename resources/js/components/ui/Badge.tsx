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
          'border border-slate-700 bg-slate-900 text-slate-200': variant === 'default',
          'bg-amber-400 text-slate-950': variant === 'gold',
          'border border-emerald-500/30 bg-emerald-500/10 text-emerald-300': variant === 'emerald',
          'border border-amber-500/30 bg-amber-500/10 text-amber-300': variant === 'amber',
          'border border-rose-500/30 bg-rose-500/10 text-rose-300': variant === 'rose',
          'border border-slate-700 bg-slate-950 text-slate-300': variant === 'neutral',
        },
        className,
      )}
    >
      {children}
    </span>
  );
}
