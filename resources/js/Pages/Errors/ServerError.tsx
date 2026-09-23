import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { Link } from '@inertiajs/react';

export default function ServerError({ message }: { message?: string }) {
  return (
    <StorefrontLayout seo={{ title: 'Server Error', description: 'An unexpected server error occurred.', robots: 'noindex,nofollow' }}>
      <div className="mx-auto max-w-xl py-12 text-center">
        <div className="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl border border-amber-500/20 bg-amber-500/10 text-amber-400">
          <svg className="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>
        <h1 className="text-3xl font-extrabold text-white sm:text-4xl">Temporary Service Interruption</h1>
        <p className="mt-4 text-base text-slate-300">
          {message || 'The store is experiencing a temporary service issue. Please check back in a few moments.'}
        </p>
        <div className="mt-8 flex justify-center gap-4">
          <Link href="/" className="rounded-full bg-amber-400 px-6 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-amber-300">
            Return to Homepage
          </Link>
          <button
            type="button"
            onClick={() => window.location.reload()}
            className="rounded-full border border-slate-700 bg-slate-800 px-6 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-slate-600 hover:text-white"
          >
            Try Again
          </button>
        </div>
      </div>
    </StorefrontLayout>
  );
}
