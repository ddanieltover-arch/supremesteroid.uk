import { Link } from '@inertiajs/react';
import type { HomepagePayload } from '@/types';

export function ApprovedReviews({ reviews }: { reviews?: HomepagePayload['reviews'] }) {
  if (!reviews || reviews.length === 0) {
    return null;
  }

  return (
    <section aria-labelledby="reviews-heading" className="mt-16">
      <h2 id="reviews-heading" className="text-2xl font-semibold text-slate-900">
        Recent approved reviews
      </h2>
      <div className="mt-6 grid gap-4 md:grid-cols-3">
        {reviews.map((review) => (
          <article key={review.id} className="hover-lift rounded-2xl border border-slate-200 bg-white/80 p-5">
            <p className="text-xs text-brand">{review.rating}/5</p>
            <p className="mt-2 font-semibold text-slate-900">{review.title || 'Customer review'}</p>
            {review.body ? <p className="mt-2 text-sm text-slate-500">{review.body}</p> : null}
            <p className="mt-3 text-xs text-slate-500">
              {review.display_name || 'Customer'}
              {review.verified_purchase ? ' · Verified purchase' : ''}
            </p>
            {review.product_url && review.product_name ? (
              <Link href={review.product_url} className="mt-3 inline-flex text-sm text-blue-600">
                {review.product_name}
              </Link>
            ) : null}
          </article>
        ))}
      </div>
    </section>
  );
}
