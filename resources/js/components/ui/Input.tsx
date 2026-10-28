import { forwardRef, type InputHTMLAttributes } from 'react';
import clsx from 'clsx';

type InputProps = InputHTMLAttributes<HTMLInputElement> & {
  label?: string;
  error?: string;
  helperText?: string;
};

export const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ label, error, helperText, className = '', id, ...props }, ref) => {
    const inputId = id || (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);
    const errorId = error && inputId ? `${inputId}-error` : undefined;
    const helperId = !error && helperText && inputId ? `${inputId}-help` : undefined;

    return (
      <div className="w-full">
        {label ? (
          <label htmlFor={inputId} className="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-500">
            {label}
          </label>
        ) : null}
        <input
          ref={ref}
          id={inputId}
          aria-invalid={error ? true : undefined}
          aria-describedby={errorId ?? helperId}
          className={clsx(
            'w-full scroll-mb-28 rounded-lg border bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-500 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-600/40',
            error ? 'border-rose-500/80 focus:border-rose-500' : 'border-slate-200 focus:border-blue-600',
            props.disabled ? 'cursor-not-allowed opacity-60' : '',
            className,
          )}
          {...props}
        />
        {error ? (
          <p id={errorId} className="mt-1.5 text-xs text-rose-400" role="alert">
            {error}
          </p>
        ) : helperText ? (
          <p id={helperId} className="mt-1.5 text-xs text-slate-500">
            {helperText}
          </p>
        ) : null}
      </div>
    );
  },
);

Input.displayName = 'Input';
