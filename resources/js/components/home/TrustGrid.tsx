import { FileText, Lock, Mail, Shield, Truck } from 'lucide-react';
import type { HomepagePayload } from '@/types';

const icons = [Shield, Lock, Truck, Mail, FileText];

export function TrustGrid({ items }: { items: HomepagePayload['trustItems'] }) {
  if (items.length === 0) {
    return null;
  }

  return (
    <section aria-labelledby="trust-heading" className="mt-16">
      <h2 id="trust-heading" className="text-2xl font-semibold text-slate-900">
        How the store works
      </h2>
      <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {items.map((item, index) => {
          const Icon = icons[index % icons.length];
          return (
            <article
              key={item.title}
              className="hover-lift group rounded-2xl border border-slate-200 bg-white/80 p-5"
            >
              <Icon className="h-5 w-5 text-brand" aria-hidden="true" />
              <h3 className="mt-3 text-sm font-semibold text-slate-900">{item.title}</h3>
              <p className="mt-2 text-sm leading-relaxed text-slate-500">{item.body}</p>
            </article>
          );
        })}
      </div>
    </section>
  );
}
