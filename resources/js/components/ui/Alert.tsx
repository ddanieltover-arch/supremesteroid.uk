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
    info: <Info className="mt-0.5 h-5 w-5 shrink-0 text-sky-600" aria-hidden="true" />,
    warning: <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" aria-hidden="true" />,
    error: <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-rose-600" aria-hidden="true" />,
    success: <CheckCircle className="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true" />,
  };

  return (
    <div
      className={clsx(
        'flex items-start gap-3 rounded-xl border p-4 text-sm',
        {
          'border-sky-200 bg-sky-50 text-sky-900': type === 'info',
          'border-amber-300 bg-amber-50 text-amber-900': type === 'warning',
          'border-rose-200 bg-rose-50 text-rose-800': type === 'error',
          'border-emerald-200 bg-emerald-50 text-emerald-900': type === 'success',
        },
        className,
      )}
      role="alert"
    >
      {icons[type]}
      <div>
        {title ? <h4 className="mb-1 font-semibold text-slate-900">{title}</h4> : null}
        <div>{children}</div>
      </div>
    </div>
  );
}
