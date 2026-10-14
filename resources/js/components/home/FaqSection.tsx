import type { HomepagePayload } from '@/types';

export function FaqSection({ faqs }: { faqs: HomepagePayload['faqs'] }) {
  if (faqs.length === 0) {
    return null;
  }

  return (
    <section aria-labelledby="faq-heading" className="mt-16">
      <h2 id="faq-heading" className="text-2xl font-semibold text-white">
        Questions
      </h2>
      <div className="mt-6 space-y-3">
        {faqs.map((item) => (
          <details key={item.id} className="rounded-2xl border border-slate-800 bg-slate-950/60">
            <summary className="cursor-pointer px-4 py-4 text-sm font-semibold text-white">{item.question}</summary>
            <p className="border-t border-slate-800 px-4 py-4 text-sm leading-relaxed text-slate-400">{item.answer}</p>
          </details>
        ))}
      </div>
    </section>
  );
}
