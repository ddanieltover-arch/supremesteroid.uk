import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/Button';
import type { HomepagePayload } from '@/types';

export function NewsletterSignup({ newsletter }: { newsletter: NonNullable<HomepagePayload['newsletter']> }) {
  const form = useForm({
    email: '',
    consent: false,
  });

  return (
    <section aria-labelledby="newsletter-heading" className="mt-16 rounded-3xl border border-slate-800 bg-gradient-to-br from-slate-950 to-slate-900 p-6 sm:p-8">
      <h2 id="newsletter-heading" className="text-2xl font-semibold text-white">
        {newsletter.heading}
      </h2>
      <p className="mt-2 max-w-2xl text-sm text-slate-400">{newsletter.body}</p>
      <form
        className="mt-6 max-w-xl space-y-3"
        onSubmit={(event) => {
          event.preventDefault();
          form.post('/newsletter', { preserveScroll: true });
        }}
      >
        <label className="block text-sm text-slate-300" htmlFor="newsletter-email">
          Email
          <input
            id="newsletter-email"
            type="email"
            required
            autoComplete="email"
            value={form.data.email}
            onChange={(event) => form.setData('email', event.target.value)}
            className="field mt-1"
          />
        </label>
        {form.errors.email ? <p className="text-xs text-rose-300">{form.errors.email}</p> : null}
        <label className="flex items-start gap-3 text-sm text-slate-400">
          <input
            type="checkbox"
            checked={form.data.consent}
            onChange={(event) => form.setData('consent', event.target.checked)}
            className="mt-1 h-4 w-4"
          />
          <span>I consent to receive occasional catalogue emails. I can unsubscribe later.</span>
        </label>
        {form.errors.consent ? <p className="text-xs text-rose-300">{form.errors.consent}</p> : null}
        {form.recentlySuccessful ? (
          <p role="status" className="text-sm text-emerald-300">
            Thank you. Your consent has been recorded.
          </p>
        ) : null}
        <Button type="submit" variant="gold" className="min-h-12" isLoading={form.processing}>
          Subscribe
        </Button>
      </form>
    </section>
  );
}
