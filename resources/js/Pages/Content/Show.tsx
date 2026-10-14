import StorefrontLayout from '../../Layouts/StorefrontLayout';
import type { SeoProps } from '@/types';

export default function ContentShow({
  page,
  seo,
}: {
  page: { title: string; slug: string; content?: string | null; is_placeholder: boolean };
  seo?: SeoProps;
}) {
  return (
    <StorefrontLayout seo={seo}>
      <article className="mx-auto max-w-3xl">
        <h1 className="text-3xl font-semibold text-white">{page.title}</h1>
        {page.is_placeholder || !page.content ? (
          <p className="mt-6 text-sm leading-relaxed text-slate-400">
            This page is ready for approved store content. No legal or policy wording has been published here yet.
          </p>
        ) : (
          <div className="prose-invert mt-6 space-y-4 text-sm leading-relaxed text-slate-300" dangerouslySetInnerHTML={{ __html: page.content }} />
        )}
      </article>
    </StorefrontLayout>
  );
}
