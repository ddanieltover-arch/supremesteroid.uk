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
        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-mono text-[11px] font-medium uppercase tracking-wider',
        {
          'bg-slate-800 text-slate-300 border border-slate-700': variant === 'default',
          'bg-amber-500/10 text-amber-300 border border-amber-500/30': variant === 'gold',
          'bg-emerald-500/10 text-emerald-300 border border-emerald-500/30': variant === 'emerald',
          'bg-yellow-500/10 text-yellow-300 border border-yellow-500/30': variant === 'amber',
          'bg-rose-500/10 text-rose-300 border border-rose-500/30': variant === 'rose',
          'bg-slate-900 text-slate-400 border border-slate-800': variant === 'neutral',
        },
        className,
      )}
    >
      {children}
    </span>
  );
}
