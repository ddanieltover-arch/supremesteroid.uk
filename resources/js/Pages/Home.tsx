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
import type { HomepagePayload, ProductCard as ProductCardType, SeoProps } from '@/types';

const DEFAULT_SECTIONS = [
  { key: 'hero', enabled: true, position: 10 },
  { key: 'categories', enabled: true, position: 20 },
  { key: 'featured', enabled: true, position: 30 },
  { key: 'new', enabled: true, position: 40 },
  { key: 'offers', enabled: true, position: 50 },
  { key: 'intent', enabled: true, position: 60 },
  { key: 'brands', enabled: true, position: 70 },
  { key: 'editorial', enabled: true, position: 80 },
  { key: 'trust', enabled: true, position: 90 },
  { key: 'shipping', enabled: true, position: 100 },
  { key: 'faq', enabled: true, position: 110 },
  { key: 'newsletter', enabled: true, position: 120 },
];

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
      eyebrow: 'UK catalogue',
      headline: 'Precision in product. Clarity in information.',
      description: 'Browse published, compliance-approved products.',
      primary_cta: { label: 'Explore Collection', url: '/shop' },
      secondary_cta: { label: 'Shop Categories', url: '/#categories' },
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
            return <CategoryShowcase key={section.key} categories={data.categories} />;
          case 'featured':
            return <ProductRail key={section.key} id="featured" eyebrow="Selected" title="Featured collection" products={data.featuredProducts} />;
          case 'new':
            return <ProductRail key={section.key} id="new" eyebrow="Just published" title="New arrivals" products={data.newProducts} />;
          case 'offers':
            return <ProductRail key={section.key} id="offers" eyebrow="Current prices" title="Selected offers" products={data.saleProducts} />;
          case 'intent':
            return <IntentDiscovery key={section.key} groups={data.intent} />;
          case 'brands':
            return <BrandShowcase key={section.key} brands={data.brands} />;
          case 'editorial':
            return data.editorial ? <EditorialFeature key={section.key} editorial={data.editorial} /> : null;
          case 'trust':
            return (
              <div key={section.key}>
                <TrustGrid items={data.trustItems} />
                <ApprovedReviews reviews={data.reviews} />
              </div>
            );
          case 'shipping':
            return <ShippingInformation key={section.key} shipping={data.shipping} />;
          case 'faq':
            return <FaqSection key={section.key} faqs={data.faqs} />;
          case 'newsletter':
            return data.newsletter ? <NewsletterSignup key={section.key} newsletter={data.newsletter} /> : null;
          default:
            return null;
        }
      })}
    </StorefrontLayout>
  );
}
