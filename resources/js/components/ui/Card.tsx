import type { HTMLAttributes, ReactNode } from 'react';
import clsx from 'clsx';

export function Card({
  children,
  className = '',
  ...props
}: HTMLAttributes<HTMLDivElement> & { children: ReactNode }) {
  return (
    <div
      className={clsx('rounded-xl border border-slate-800/80 bg-slate-900/70 p-5 text-slate-100 md:p-6', className)}
      {...props}
    >
      {children}
    </div>
  );
}
