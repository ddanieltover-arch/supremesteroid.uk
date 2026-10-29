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
        'inline-flex items-center justify-center rounded-md font-medium transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 focus-visible:ring-offset-white active:scale-[0.98] motion-reduce:active:scale-100 disabled:cursor-not-allowed disabled:opacity-50 disabled:active:scale-100',
        {
          'min-h-9 px-3 py-1.5 text-xs': size === 'sm',
          'min-h-10 px-4 py-2 text-sm': size === 'md',
          'min-h-12 px-6 py-3 text-base': size === 'lg',
          'bg-brand font-semibold text-white hover:bg-brand-dark': variant === 'primary' || variant === 'gold',
          'border border-slate-300 bg-white text-slate-800 hover:border-brand hover:text-brand': variant === 'secondary',
          'border border-rose-300 bg-rose-50 text-rose-700 hover:bg-rose-100': variant === 'danger',
          'text-slate-600 hover:bg-slate-100 hover:text-slate-900': variant === 'ghost',
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
          <span>{children}</span>
        </span>
      ) : (
        children
      )}
    </button>
  );
}
