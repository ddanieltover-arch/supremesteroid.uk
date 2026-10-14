import type { ButtonHTMLAttributes, ReactNode } from 'react';
import clsx from 'clsx';

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost' | 'gold';
  size?: 'sm' | 'md' | 'lg';
  isLoading?: boolean;
  children: ReactNode;
};

export function Button({
  children,
  variant = 'primary',
  size = 'md',
  isLoading = false,
  className = '',
  disabled,
  type = 'button',
  ...props
}: ButtonProps) {
  return (
    <button
      type={type}
      className={clsx(
        'inline-flex items-center justify-center rounded-md font-medium transition-all duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 disabled:cursor-not-allowed disabled:opacity-50',
        {
          'min-h-9 px-3 py-1.5 text-xs': size === 'sm',
          'min-h-10 px-4 py-2 text-sm': size === 'md',
          'min-h-12 px-6 py-3 text-base': size === 'lg',
          'bg-amber-400 font-semibold text-slate-950 hover:bg-amber-300': variant === 'primary' || variant === 'gold',
          'border border-slate-700 bg-slate-900 text-slate-100 hover:border-amber-400 hover:text-amber-300': variant === 'secondary',
          'border border-rose-500/40 bg-rose-500/10 text-rose-200 hover:bg-rose-500/20': variant === 'danger',
          'text-slate-300 hover:bg-slate-800 hover:text-white': variant === 'ghost',
        },
        className,
      )}
      disabled={disabled || isLoading}
      aria-busy={isLoading || undefined}
      {...props}
    >
      {isLoading ? (
        <span className="flex items-center gap-2">
          <svg className="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          <span>Processing…</span>
        </span>
      ) : (
        children
      )}
    </button>
  );
}
