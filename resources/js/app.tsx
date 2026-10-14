import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import type { ComponentType, ErrorInfo, ReactNode } from 'react';
import React, { Component } from 'react';
import '../css/app.css';

interface ErrorBoundaryProps {
  children: ReactNode;
}

interface ErrorBoundaryState {
  hasError: boolean;
  error: Error | null;
}

class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  public state: ErrorBoundaryState = {
    hasError: false,
    error: null,
  };

  public static getDerivedStateFromError(error: Error): ErrorBoundaryState {
    return { hasError: true, error };
  }

  public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    console.error('Uncaught error in React render tree:', error, errorInfo);
  }

  public render() {
    if (this.state.hasError) {
      return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-[#020617] p-6 text-slate-100 antialiased">
          <div className="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900/90 p-8 shadow-2xl backdrop-blur">
            <div className="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/10 text-amber-400">
              <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
            </div>
            <h1 className="text-xl font-bold text-white">Application Error</h1>
            <p className="mt-2 text-sm text-slate-400">
              An unexpected display error occurred while rendering this page.
            </p>
            {this.state.error?.message ? (
              <pre className="mt-4 overflow-x-auto rounded-lg bg-slate-950 p-3 font-mono text-xs text-rose-300">
                {this.state.error.message}
              </pre>
            ) : null}
            <button
              type="button"
              onClick={() => window.location.reload()}
              className="mt-6 w-full rounded-xl bg-amber-400 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-300"
            >
              Reload Page
            </button>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}

createInertiaApp({
  title: (title) => {
    if (!title) {
      return 'Supreme Steroids';
    }
    return /supreme\s*steroids/i.test(title) ? title : `${title} | Supreme Steroids`;
  },
  progress: {
    color: '#f59e0b',
    delay: 120,
  },
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.tsx');
    const page = pages[`./Pages/${name}.tsx`];
    if (!page) {
      throw new Error(`Inertia page not found: ${name}`);
    }

    return page().then((module) => {
      const resolved = module as { default?: ComponentType } & ComponentType;
      return resolved.default ?? resolved;
    });
  },
  setup({ el, App, props }) {
    createRoot(el).render(
      <ErrorBoundary>
        <App {...props} />
      </ErrorBoundary>
    );
  },
});

