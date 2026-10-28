import type { HTMLAttributes, ReactNode } from 'react';
import clsx from 'clsx';

export function Card({
  children,
  className = '',
  ...props
}: HTMLAttributes<HTMLDivElement> & { children: ReactNode }) {
  return (
    <div
      className={clsx('rounded-xl border border-slate-200 bg-slate-50/70 p-5 text-slate-900 md:p-6', className)}
      {...props}
    >
      {children}
    </div>
  );
}
