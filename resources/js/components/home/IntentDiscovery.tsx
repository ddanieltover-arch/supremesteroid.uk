import { Link } from '@inertiajs/react';
import type { HomepagePayload } from '@/types';

export function IntentDiscovery({ groups }: { groups: HomepagePayload['intent'] }) {
  if (groups.length === 0) {
    return null;
  }

  return (
    <section aria-labelledby="intent-heading" className="mt-16">
      <p className="text-xs font-semibold uppercase tracking-[0.2em] text-blue-600/80">Explore</p>
      <h2 id="intent-heading" className="mt-2 text-2xl font-semibold text-slate-900">
        Discover by collection
      </h2>
      <div className="mt-6 flex flex-wrap gap-3">
        {groups.map((group) => (
          <Link
            key={group.key}
            href={group.url}
            className="inline-flex min-h-11 items-center rounded-full border border-slate-200 bg-white px-4 text-sm text-slate-800 transition-colors duration-200 hover:border-brand hover:text-brand hover:shadow-md"
          >
            {group.label}
          </Link>
        ))}
      </div>
    </section>
  );
}
