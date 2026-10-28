import { forwardRef, type SelectHTMLAttributes } from 'react';
import clsx from 'clsx';

type SelectProps = SelectHTMLAttributes<HTMLSelectElement> & {
  label?: string;
  error?: string;
  options: Array<{ value: string; label: string }>;
};

export const Select = forwardRef<HTMLSelectElement, SelectProps>(
  ({ label, error, options, className = '', id, ...props }, ref) => {
    const selectId = id || (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);

    return (
      <div className="w-full">
        {label ? (
          <label htmlFor={selectId} className="mb-1.5 block text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">
            {label}
          </label>
        ) : null}
        <select
          ref={ref}
          id={selectId}
          aria-invalid={error ? true : undefined}
          className={clsx(
            'w-full rounded-lg border bg-white px-3 py-2 text-sm font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-600/40',
            error ? 'border-rose-500/80' : 'border-slate-200 focus:border-blue-600',
            className,
          )}
          {...props}
        >
          {options.map((opt) => (
            <option key={opt.value} value={opt.value} className="bg-slate-50 text-slate-900">
              {opt.label}
            </option>
          ))}
        </select>
        {error ? (
          <p className="mt-1.5 text-xs text-rose-400" role="alert">
            {error}
          </p>
        ) : null}
      </div>
    );
  },
);

Select.displayName = 'Select';
