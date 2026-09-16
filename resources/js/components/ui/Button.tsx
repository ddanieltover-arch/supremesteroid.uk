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
        'inline-flex items-center justify-center rounded-lg font-medium transition-all duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 disabled:cursor-not-allowed disabled:opacity-50',
        {
          'px-3 py-1.5 text-xs min-h-9': size === 'sm',
          'px-4 py-2 text-sm min-h-10': size === 'md',
          'px-6 py-3 text-base min-h-12': size === 'lg',
          'bg-slate-100 text-slate-950 hover:bg-white font-semibold': variant === 'primary',
          'bg-slate-800/90 text-slate-200 hover:bg-slate-700/80 border border-slate-700/60': variant === 'secondary',
          'bg-rose-900/40 text-rose-300 border border-rose-700/50 hover:bg-rose-900/60': variant === 'danger',
          'text-slate-400 hover:text-slate-100 hover:bg-slate-800/40': variant === 'ghost',
          'bg-gradient-to-r from-amber-500 via-amber-400 to-amber-600 text-slate-950 font-bold shadow-lg shadow-amber-500/20 hover:brightness-110':
            variant === 'gold',
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
