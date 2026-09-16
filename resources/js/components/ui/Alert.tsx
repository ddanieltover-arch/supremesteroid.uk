import type { ReactNode } from 'react';
import { AlertCircle, AlertTriangle, CheckCircle, Info } from 'lucide-react';
import clsx from 'clsx';

export function Alert({
  type = 'info',
  title,
  children,
  className = '',
}: {
  type?: 'info' | 'warning' | 'error' | 'success';
  title?: string;
  children: ReactNode;
  className?: string;
}) {
  const icons = {
    info: <Info className="mt-0.5 h-5 w-5 shrink-0 text-sky-400" aria-hidden="true" />,
    warning: <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-amber-400" aria-hidden="true" />,
    error: <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-rose-400" aria-hidden="true" />,
    success: <CheckCircle className="mt-0.5 h-5 w-5 shrink-0 text-emerald-400" aria-hidden="true" />,
  };

  return (
    <div
      className={clsx(
        'flex items-start gap-3 rounded-xl border p-4 text-sm',
        {
          'border-sky-800/60 bg-sky-950/40 text-sky-200': type === 'info',
          'border-amber-800/60 bg-amber-950/40 text-amber-200': type === 'warning',
          'border-rose-800/60 bg-rose-950/40 text-rose-200': type === 'error',
          'border-emerald-800/60 bg-emerald-950/40 text-emerald-200': type === 'success',
        },
        className,
      )}
      role="alert"
    >
      {icons[type]}
      <div>
        {title ? <h4 className="mb-1 font-semibold text-slate-100">{title}</h4> : null}
        <div>{children}</div>
      </div>
    </div>
  );
}
