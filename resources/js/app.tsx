import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import type { ComponentType, ErrorInfo, ReactNode } from 'react';
import React, { Component } from 'react';
import '../css/app.css';

// Eager primary storefront pages so visits swap instantly on click with 0ms chunk latency.
import Home from './Pages/Home';
import CatalogueIndex from './Pages/Catalogue/Index';
import CatalogueShow from './Pages/Catalogue/Show';
import CartIndex from './Pages/Cart/Index';
import CheckoutIndex from './Pages/Checkout/Index';
import BlogIndex from './Pages/Blog/Index';
import BlogShow from './Pages/Blog/Show';
import ContentShow from './Pages/Content/Show';
import CheckoutDelivery from './Pages/Checkout/Delivery';
import CheckoutPayment from './Pages/Checkout/Payment';
import CheckoutConfirmation from './Pages/Checkout/Confirmation';

const eagerPages: Record<string, ComponentType<any>> = {
  'Home': Home,
  'Catalogue/Index': CatalogueIndex,
  'Catalogue/Show': CatalogueShow,
  'Cart/Index': CartIndex,
  'Checkout/Index': CheckoutIndex,
  'Blog/Index': BlogIndex,
  'Blog/Show': BlogShow,
  'Content/Show': ContentShow,
  'Checkout/Delivery': CheckoutDelivery,
  'Checkout/Payment': CheckoutPayment,
  'Checkout/Confirmation': CheckoutConfirmation,
};

const pageModules = import.meta.glob('./Pages/**/*.tsx');
const resolvedPages = new Map<string, ComponentType>(Object.entries(eagerPages));
const resolvingPages = new Map<string, Promise<ComponentType>>();

function resolvePage(name: string): ComponentType | Promise<ComponentType> {
  const cached = resolvedPages.get(name);
  if (cached) {
    return cached;
  }

  const inflight = resolvingPages.get(name);
  if (inflight) {
    return inflight;
  }

  const loader = pageModules[`./Pages/${name}.tsx`];
  if (!loader) {
    throw new Error(`Inertia page not found: ${name}`);
  }

  const pending = loader().then((module) => {
    const resolved = module as { default?: ComponentType } & ComponentType;
    const component = resolved.default ?? resolved;
    resolvedPages.set(name, component);
    resolvingPages.delete(name);
    return component;
  });

  resolvingPages.set(name, pending);
  return pending;
}

// Background pre-warm remaining secondary pages (Account, Auth, Orders) on idle
if (typeof window !== 'undefined') {
  const warmSecondaryPages = () => {
    Object.entries(pageModules).forEach(([path, loader]) => {
      const pageName = path.replace(/^\.\/Pages\//, '').replace(/\.tsx$/, '');
      if (!resolvedPages.has(pageName)) {
        void loader().then((module) => {
          const resolved = module as { default?: ComponentType } & ComponentType;
          resolvedPages.set(pageName, resolved.default ?? resolved);
        }).catch(() => {
          // Ignore idle prewarm errors
        });
      }
    });
  };

  if ('requestIdleCallback' in window) {
    (window as unknown as { requestIdleCallback: (cb: () => void) => void }).requestIdleCallback(warmSecondaryPages);
  } else {
    setTimeout(warmSecondaryPages, 1000);
  }

  // Fast link interaction listener: prefetch Inertia payload on hover / touchstart
  const prefetchedLinks = new Set<string>();
  const triggerPrefetch = (target: EventTarget | null) => {
    if (!(target instanceof Element)) return;
    const anchor = target.closest('a');
    if (!anchor) return;
    const href = anchor.getAttribute('href');
    // Skip in-page hashes ("#x" / "/#x") — prefetching them triggers a full "/" visit.
    if (
      !href ||
      href.includes('#') ||
      href.startsWith('mailto:') ||
      href.startsWith('tel:') ||
      href.startsWith('javascript:')
    ) {
      return;
    }
    if (href.startsWith('/logout') || href.startsWith('/internal') || href.includes('download')) {
      return;
    }

    const isInternal = href.startsWith('/') || href.startsWith(window.location.origin);
    if (!isInternal) return;

    const cleanPath = href.startsWith(window.location.origin) ? href.slice(window.location.origin.length) : href;
    if (prefetchedLinks.has(cleanPath)) return;
    prefetchedLinks.add(cleanPath);

    try {
      router.prefetch(cleanPath, {}, { cacheFor: 60000 });
    } catch {
      // Ignore prefetch failures
    }
  };

  document.addEventListener('mouseover', (e) => triggerPrefetch(e.target), { passive: true });
  document.addEventListener('touchstart', (e) => triggerPrefetch(e.target), { passive: true });
}

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
        <div className="flex min-h-screen flex-col items-center justify-center bg-[#f9f9f9] p-6 text-slate-900 antialiased">
          <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-lg">
            <div className="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
              <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
            </div>
            <h1 className="text-xl font-bold text-slate-900">Application Error</h1>
            <p className="mt-2 text-sm text-slate-500">
              An unexpected display error occurred while rendering this page.
            </p>
            {this.state.error?.message ? (
              <pre className="mt-4 overflow-x-auto rounded-lg bg-slate-50 p-3 font-mono text-xs text-rose-600">
                {this.state.error.message}
              </pre>
            ) : null}
            <button
              type="button"
              onClick={() => window.location.reload()}
              className="mt-6 w-full rounded-xl bg-blue-600 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500"
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

/**
 * Inertia JS v3 reads only <script data-page type="application/json">.
 * Laravel may still emit the legacy #app[data-page] attribute until
 * inertia.use_script_element_for_initial_page is enabled — support both.
 */
function readInitialPage() {
  if (typeof document === 'undefined') {
    return undefined;
  }

  const script = document.querySelector('script[data-page="app"][type="application/json"]');
  if (script?.textContent) {
    try {
      return JSON.parse(script.textContent);
    } catch {
      // fall through to legacy attribute
    }
  }

  const el = document.getElementById('app');
  const raw = el?.dataset?.page;
  if (raw) {
    try {
      return JSON.parse(raw);
    } catch {
      return undefined;
    }
  }

  return undefined;
}

createInertiaApp({
  page: readInitialPage(),
  title: (title) => {
    if (!title) {
      return 'Supreme Steroids';
    }
    return /supreme\s*steroids/i.test(title) ? title : `${title} | Supreme Steroids`;
  },
  progress: false,
  resolve: (name) => resolvePage(name),
  setup({ el, App, props }) {
    if (!el) {
      console.error('Inertia root element #app was not found.');
      return;
    }

    createRoot(el).render(
      <ErrorBoundary>
        <App {...props} />
      </ErrorBoundary>
    );
  },
});
