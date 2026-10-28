import { HelpCircle } from 'lucide-react';
import { InfoPageChrome } from '@/components/content/InfoPageChrome';
import StorefrontLayout from '../../Layouts/StorefrontLayout';
import type { SeoProps } from '@/types';

export default function ContentShow({
  page,
  seo,
}: {
  page: { title: string; slug: string; content?: string | null; is_placeholder: boolean };
  seo?: SeoProps;
}) {
  const isFaq = page.slug === 'faq';
  const isBpc = page.slug === 'bpc-157-uk';

  return (
    <StorefrontLayout seo={seo}>
      <InfoPageChrome
        title={page.title}
        activeSlug={page.slug}
        extraBadges={
          <>
            {isFaq ? (
              <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-semibold text-emerald-300">
                Help & Answers
              </span>
            ) : null}
            {isBpc ? (
              <span className="inline-flex items-center gap-1 rounded-full bg-violet-500/20 px-3 py-1 text-xs font-semibold text-violet-300">
                Peptide Protocol
              </span>
            ) : null}
          </>
        }
      >
        {page.is_placeholder || !page.content ? (
          <div className="py-12 text-center">
            <HelpCircle className="mx-auto h-12 w-12 text-slate-300" />
            <p className="mt-4 text-base font-semibold text-slate-700">Content Pending Review</p>
            <p className="mt-2 text-sm text-slate-500">
              This section is undergoing compliance validation. Official details will be posted shortly.
            </p>
          </div>
        ) : (
          <article className="blog-prose max-w-none" dangerouslySetInnerHTML={{ __html: page.content }} />
        )}
      </InfoPageChrome>
    </StorefrontLayout>
  );
}
