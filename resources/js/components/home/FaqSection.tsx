import type { HomepagePayload } from '@/types';

export function FaqSection({ faqs }: { faqs: HomepagePayload['faqs'] }) {
  if (faqs.length === 0) {
    return null;
  }

  return (
    <section aria-labelledby="faq-heading" className="mt-16">
      <h2 id="faq-heading" className="text-2xl font-semibold text-slate-900">
        Questions
      </h2>
      <div className="mt-6 space-y-3">
        {faqs.map((item) => (
          <details key={item.id} className="hover-lift rounded-2xl border border-slate-200 bg-white/80 open:border-blue-200 open:shadow-md">
            <summary className="cursor-pointer px-4 py-4 text-sm font-semibold text-slate-900 transition-colors hover:text-brand">{item.question}</summary>
            <p className="border-t border-slate-200 px-4 py-4 text-sm leading-relaxed text-slate-500">{item.answer}</p>
          </details>
        ))}
      </div>
    </section>
  );
}
