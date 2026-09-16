import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { Link } from '@inertiajs/react';

export default function NotFound() {
  return (
    <StorefrontLayout seo={{ title: 'Page not found', description: 'This page does not exist.', robots: 'noindex,nofollow' }}>
      <h1 className="text-3xl font-semibold text-white">Page not found</h1>
      <p className="mt-4 text-slate-400">The page you requested is not available.</p>
      <Link href="/shop" className="mt-6 inline-flex rounded-full bg-amber-400 px-5 py-2 text-sm font-semibold text-slate-950">
        Return to shop
      </Link>
    </StorefrontLayout>
  );
}
