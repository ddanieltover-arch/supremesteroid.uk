import { FileText, Lock, Mail, RefreshCw, Shield, Truck } from 'lucide-react';

const blocks = [
  { icon: Lock, title: 'Secure ordering', body: 'Checkout and payment review stay on the server. Prices cannot be changed in the browser.' },
  { icon: Truck, title: 'Shipping', body: 'Delivery options and free-shipping eligibility are calculated from the live basket.' },
  { icon: Mail, title: 'Customer support', body: 'Contact the store for order, documentation, or delivery questions.' },
  { icon: FileText, title: 'Documentation', body: 'Product documents appear only when a verified record exists on the catalogue item.' },
  { icon: RefreshCw, title: 'Returns', body: 'Return requests follow the store policy for unused, lawfully supplied goods.' },
  { icon: Shield, title: 'Privacy', body: 'Account, cart, and payment pages are not offered for public indexing.' },
];

export function TrustBlocks() {
  return (
    <section aria-label="Store information" className="mt-14 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      {blocks.map((block) => (
        <article key={block.title} className="hover-lift group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h3 className="flex items-center gap-2 text-sm font-semibold text-slate-900">
            <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-brand">
              <block.icon className="h-4 w-4" aria-hidden="true" />
            </span>
            {block.title}
          </h3>
          <p className="mt-3 text-xs leading-relaxed text-slate-500">{block.body}</p>
        </article>
      ))}
    </section>
  );
}
