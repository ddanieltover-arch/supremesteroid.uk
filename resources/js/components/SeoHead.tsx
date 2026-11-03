import { Head } from '@inertiajs/react';
import type { SeoProps } from '@/types';

export default function SeoHead({ seo, defaultTitle = 'Supreme Steroids' }: { seo?: SeoProps; defaultTitle?: string }) {
  const title = seo?.title || defaultTitle;
  const description = seo?.description || 'Explore the published Supreme Steroids catalogue with server-confirmed product information.';
  const canonical = seo?.canonical || undefined;
  const ogImage = seo?.og_image || undefined;

  return (
    <Head title={title}>
      <meta head-key="description" name="description" content={description} />
      {canonical ? <link head-key="canonical" rel="canonical" href={canonical} /> : null}
      <meta head-key="theme-color" name="theme-color" content="#1e6fd9" />
      <meta head-key="robots" name="robots" content={seo?.robots || 'index,follow'} />
      <meta head-key="og:title" property="og:title" content={seo?.og_title || title} />
      <meta head-key="og:description" property="og:description" content={seo?.og_description || description} />
      <meta head-key="og:type" property="og:type" content="website" />
      <meta head-key="og:site_name" property="og:site_name" content="Supreme Steroids" />
      <meta head-key="og:locale" property="og:locale" content="en_GB" />
      {canonical ? <meta head-key="og:url" property="og:url" content={canonical} /> : null}
      {ogImage ? <meta head-key="og:image" property="og:image" content={ogImage} /> : null}
      <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
      <meta head-key="twitter:title" name="twitter:title" content={seo?.og_title || title} />
      <meta head-key="twitter:description" name="twitter:description" content={seo?.og_description || description} />
      {ogImage ? <meta head-key="twitter:image" name="twitter:image" content={ogImage} /> : null}
    </Head>
  );
}
