export type MoneyPricing = {
  currency: string;
  base_price: string;
  sale_price: string | null;
  effective_price: string;
  is_on_sale: boolean;
  discount_percent?: number | null;
};

export type ProductCard = {
  id: string;
  sku: string;
  name: string;
  slug: string;
  status?: string;
  classification?: string | null;
  short_description?: string | null;
  brand?: string | null;
  brand_slug?: string | null;
  category?: string | null;
  category_slug?: string | null;
  image_url?: string | null;
  image_alt?: string | null;
  batch_number?: string | null;
  lab_verification_reference?: string | null;
  available_quantity?: number | null;
  pricing: MoneyPricing | null;
  is_purchasable: boolean;
  is_featured?: boolean;
  variant_count?: number;
  has_variants?: boolean;
  badges?: Array<{ code: string; label: string }>;
  stock?: { label: string; code: string; quantity?: number | null };
};

export type ProductVariant = {
  id: string;
  sku: string;
  name: string;
  effective_price: string;
  is_active: boolean;
  is_purchasable: boolean;
  available_quantity?: number | null;
  stock?: { label: string; code: string; quantity?: number | null };
  is_on_sale?: boolean;
  compare_at_price?: string | null;
  attributes?: Array<{ name: string; value: string }>;
};

export type ProductDetail = Omit<ProductCard, 'brand' | 'category'> & {
  description?: string | null;
  ingredients?: string | null;
  safety_guidelines?: string | null;
  brand?: { id: string; name: string; slug: string } | null;
  category?: { id: string; name: string; slug: string } | null;
  images: Array<{ id: string; url: string; alt_text?: string | null; caption?: string | null; thumbnail_url?: string | null; is_primary: boolean; variant_id?: string | null }>;
  variants: ProductVariant[];
  specifications?: Array<{ name: string; value: string }>;
  related_products?: ProductCard[];
  related_groups?: {
    related?: ProductCard[];
    alternative?: ProductCard[];
    complementary?: ProductCard[];
  };
  shipping_methods?: ShippingMethod[];
  ineligibility_reasons?: string[];
  highlights?: string[];
  content?: Record<string, string>;
  faqs?: Array<{ question: string; answer: string }>;
  reviews?: {
    average?: number | null;
    count?: number;
    items?: Array<{
      id: string;
      rating: number;
      title?: string | null;
      body?: string | null;
      display_name?: string | null;
      verified_purchase?: boolean;
      created_at?: string | null;
    }>;
  };
  breadcrumbs?: Array<{ name: string; url: string }>;
  structured_data?: Array<Record<string, unknown>>;
  stock?: { label: string; code: string; quantity?: number | null };
  badges?: Array<{ code: string; label: string }>;
  allow_stock_notification?: boolean;
  is_preview?: boolean;
};

export type CartItem = {
  id: string;
  product_id: string;
  product_name: string;
  product_slug: string;
  product_sku: string;
  variant_id: string | null;
  variant_name: string | null;
  unit_price: string;
  quantity: number;
  subtotal: string;
  line_total: string;
  image_url?: string | null;
  is_eligible: boolean;
  ineligibility_reasons: string[];
};

export type CartTotals = {
  subtotal_amount: string;
  discount_amount: string;
  shipping_amount: string;
  tax_amount: string;
  grand_total_amount: string;
  items_count: number;
  currency: string;
};

export type ShippingEligibility = {
  free_shipping_threshold: string;
  current_eligible_subtotal: string;
  remaining_to_threshold: string;
  free_shipping_eligible: boolean;
  progress_percent: number;
  currency: string;
};

export type CartPayload = {
  id: string | null;
  items: CartItem[];
  totals: CartTotals;
  shipping_eligibility: ShippingEligibility;
  is_checkout_ready: boolean;
};

export type ShippingMethod = {
  id?: string;
  code: string;
  name: string;
  rate?: number;
  rate_amount?: string;
  currency: string;
  estimated_days?: string | null;
  is_discreet?: boolean;
  is_free?: boolean;
  is_eligible_for_free_threshold?: boolean;
  description?: string | null;
};

export type SeoProps = {
  title: string;
  description: string;
  canonical?: string;
  robots?: string;
  og_title?: string;
  og_description?: string;
  og_image?: string | null;
};

export type Paginated<T> = {
  data: T[];
  links?: Array<{ url: string | null; label: string; active: boolean }>;
  current_page?: number;
  last_page?: number;
  per_page?: number;
  from?: number | null;
  to?: number | null;
  total?: number;
  prev_page_url?: string | null;
  next_page_url?: string | null;
};

export type CatalogueNav = {
  categories: Array<{
    id: string;
    name: string;
    slug: string;
    children?: Array<{ id: string; name: string; slug: string }>;
  }>;
  brands: Array<{ id: string; name: string; slug: string; logo_url?: string | null }>;
  featured: ProductCard[];
  new_arrivals: ProductCard[];
};

export type CatalogueFilters = {
  category?: string | null;
  brand?: string | null;
  search?: string | null;
  q?: string | null;
  sort?: string;
  availability?: string | null;
  min_price?: string | null;
  max_price?: string | null;
  collection?: string | null;
  attributes?: Record<string, string>;
};

export type SuggestResponse = {
  results: Array<{ id: string; name: string; slug?: string; sku?: string; url: string; brand?: string | null; image_url?: string | null; pricing?: MoneyPricing | null }>;
  products?: Array<{ id: string; name: string; url: string; brand?: string | null; image_url?: string | null; pricing?: MoneyPricing | null }>;
  brands?: Array<{ id: string; name: string; url: string }>;
  categories?: Array<{ id: string; name: string; url: string }>;
  view_all?: string;
};

export type HomepageCta = { label: string; url: string };

export type HomepageSection = { key: string; enabled: boolean; position: number };

export type HomepagePayload = {
  announcement?: { enabled: boolean; message: string } | null;
  hero: {
    eyebrow: string;
    headline: string;
    description: string;
    primary_cta: HomepageCta;
    secondary_cta: HomepageCta;
    products: ProductCard[];
  };
  categories: Array<{ id: string; name: string; slug: string; description?: string | null; image_url?: string | null; product_count: number; url: string }>;
  featuredProducts: ProductCard[];
  newProducts: ProductCard[];
  saleProducts: ProductCard[];
  brands: Array<{ id: string; name: string; slug: string; description?: string | null; logo_url?: string | null; product_count: number; url: string }>;
  intent: Array<{ key: string; label: string; url: string }>;
  editorial?: { label: string; heading: string; body: string; cta: HomepageCta; image_url?: string | null } | null;
  trustItems: Array<{ title: string; body: string }>;
  shipping: { currency: string; free_shipping_threshold: string; methods: ShippingMethod[] };
  faqs: Array<{ id: string; question: string; answer: string }>;
  reviews?: Array<{ id: string; rating: number; title?: string | null; body?: string | null; display_name?: string | null; product_name?: string | null; product_url?: string | null; verified_purchase?: boolean }>;
  newsletter?: { heading: string; body: string } | null;
  sections: HomepageSection[];
};

export type CheckoutPreview = {
  country_code: string;
  shipping_method_code: string;
  items_subtotal: string;
  discount: string;
  shipping: string;
  tax: string;
  grand_total: string;
  currency: string;
};
