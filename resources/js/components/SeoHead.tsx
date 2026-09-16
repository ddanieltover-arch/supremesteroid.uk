import { Head } from '@inertiajs/react';
import type { SeoProps } from '@/types';

export default function SeoHead({ seo, defaultTitle = 'Supreme Steroids' }: { seo?: SeoProps; defaultTitle?: string }) {
  const title = seo?.title || defaultTitle;
  const description = seo?.description || 'Lawful sports nutrition, wellness, and certified research formulations.';

  return (
    <Head title={title}>
      <meta name="description" content={description} />
      {seo?.canonical ? <link rel="canonical" href={seo.canonical} /> : null}
      <meta name="robots" content={seo?.robots || 'index,follow'} />
      <meta property="og:title" content={seo?.og_title || title} />
      <meta property="og:description" content={seo?.og_description || description} />
      <meta property="og:type" content="website" />
      {seo?.og_image ? <meta property="og:image" content={seo.og_image} /> : null}
      <meta name="twitter:card" content="summary_large_image" />
    </Head>
  );
}
