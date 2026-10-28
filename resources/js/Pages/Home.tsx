import StorefrontLayout from '../Layouts/StorefrontLayout';
import { Head } from '@inertiajs/react';
import { ApprovedReviews } from '@/components/home/ApprovedReviews';
import { BrandShowcase } from '@/components/home/BrandShowcase';
import { CategoryShowcase } from '@/components/home/CategoryShowcase';
import { EditorialFeature } from '@/components/home/EditorialFeature';
import { FaqSection } from '@/components/home/FaqSection';
import { HeroSection } from '@/components/home/HeroSection';
import { IntentDiscovery } from '@/components/home/IntentDiscovery';
import { NewsletterSignup } from '@/components/home/NewsletterSignup';
import { ProductRail } from '@/components/home/ProductRail';
import { ShippingInformation } from '@/components/home/ShippingInformation';
import { TrustGrid } from '@/components/home/TrustGrid';
import { HomeEditorialContent } from '@/components/home/HomeEditorialContent';
import { Reveal } from '@/components/ui/Reveal';
import type { HomepagePayload, ProductCard as ProductCardType, SeoProps } from '@/types';
import type { ReactNode } from 'react';

const DEFAULT_SECTIONS = [
  { key: 'hero', enabled: true, position: 10 },
  { key: 'featured', enabled: true, position: 20 },
  { key: 'new', enabled: true, position: 30 },
  { key: 'offers', enabled: true, position: 40 },
  { key: 'categories', enabled: true, position: 50 },
  { key: 'intent', enabled: true, position: 60 },
  { key: 'brands', enabled: true, position: 70 },
  { key: 'editorial', enabled: true, position: 80 },
  { key: 'trust', enabled: true, position: 90 },
  { key: 'shipping', enabled: true, position: 100 },
  { key: 'faq', enabled: true, position: 110 },
  { key: 'newsletter', enabled: true, position: 120 },
];

function SectionReveal({ children }: { children: ReactNode }) {
  return <Reveal className="mt-0">{children}</Reveal>;
}

export default function Home({
  homepage,
  featuredProducts = [],
  structured_data = [],
  seo,
}: {
  homepage?: HomepagePayload;
  featuredProducts?: ProductCardType[];
  structured_data?: Array<Record<string, unknown>>;
  seo?: SeoProps;
}) {
  const data = homepage ?? {
    hero: {
      eyebrow: 'Official store',
      headline: 'Buy oral and injectable products online',
      description: 'Lab-tested products with next-day UK delivery options and clear product information before you check out.',
      primary_cta: { label: 'Shop now', url: '/shop' },
      secondary_cta: { label: 'Browse categories', url: '/#categories' },
      products: featuredProducts.slice(0, 3),
    },
    categories: [],
    featuredProducts,
    newProducts: [],
    saleProducts: [],
    brands: [],
    intent: [],
    editorial: null,
    trustItems: [],
    shipping: { currency: 'GBP', free_shipping_threshold: '300.00', methods: [] },
    faqs: [],
    newsletter: null,
    sections: DEFAULT_SECTIONS,
  };

  const sections = (data.sections?.length ? data.sections : DEFAULT_SECTIONS)
    .filter((section) => section.enabled)
    .sort((a, b) => a.position - b.position);

  return (
    <StorefrontLayout seo={seo}>
      <Head>
        {structured_data.map((schema, index) => (
          <script key={index} type="application/ld+json">
            {JSON.stringify(schema)}
          </script>
        ))}
      </Head>

      {sections.map((section) => {
        switch (section.key) {
          case 'hero':
            return <HeroSection key={section.key} hero={data.hero} />;
          case 'categories':
            return (
              <SectionReveal key={section.key}>
                <CategoryShowcase categories={data.categories} />
              </SectionReveal>
            );
          case 'featured':
            return (
              <SectionReveal key={section.key}>
                <ProductRail id="featured" eyebrow="Selected" title="Featured collection" products={data.featuredProducts} />
              </SectionReveal>
            );
          case 'new':
            return (
              <SectionReveal key={section.key}>
                <ProductRail id="new" eyebrow="Just published" title="New arrivals" products={data.newProducts} />
              </SectionReveal>
            );
          case 'offers':
            return (
              <SectionReveal key={section.key}>
                <ProductRail id="offers" eyebrow="Current prices" title="Selected offers" products={data.saleProducts} />
              </SectionReveal>
            );
          case 'intent':
            return (
              <SectionReveal key={section.key}>
                <IntentDiscovery groups={data.intent} />
              </SectionReveal>
            );
          case 'brands':
            return (
              <SectionReveal key={section.key}>
                <BrandShowcase brands={data.brands} />
              </SectionReveal>
            );
          case 'editorial':
            return data.editorial ? (
              <SectionReveal key={section.key}>
                <EditorialFeature editorial={data.editorial} />
              </SectionReveal>
            ) : null;
          case 'trust':
            return (
              <SectionReveal key={section.key}>
                <TrustGrid items={data.trustItems} />
                <ApprovedReviews reviews={data.reviews} />
              </SectionReveal>
            );
          case 'shipping':
            return (
              <SectionReveal key={section.key}>
                <ShippingInformation shipping={data.shipping} />
              </SectionReveal>
            );
          case 'faq':
            return (
              <SectionReveal key={section.key}>
                <FaqSection faqs={data.faqs} />
              </SectionReveal>
            );
          case 'newsletter':
            return data.newsletter ? (
              <SectionReveal key={section.key}>
                <NewsletterSignup newsletter={data.newsletter} />
              </SectionReveal>
            ) : null;
          default:
            return null;
        }
      })}

      <SectionReveal>
        <HomeEditorialContent />
      </SectionReveal>
    </StorefrontLayout>
  );
}
