export type MoneyPricing = {
  currency: string;
  base_price: string;
  sale_price: string | null;
  effective_price: string;
  is_on_sale: boolean;
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
};

export type ProductVariant = {
  id: string;
  sku: string;
  name: string;
  effective_price: string;
  is_active: boolean;
  is_purchasable: boolean;
  available_quantity?: number | null;
};

export type ProductDetail = Omit<ProductCard, 'brand' | 'category'> & {
  description?: string | null;
  ingredients?: string | null;
  safety_guidelines?: string | null;
  brand?: { id: string; name: string; slug: string } | null;
  category?: { id: string; name: string; slug: string } | null;
  images: Array<{ id: string; url: string; alt_text?: string | null; is_primary: boolean }>;
  variants: ProductVariant[];
  specifications?: Array<{ name: string; value: string }>;
  related_products?: ProductCard[];
  shipping_methods?: ShippingMethod[];
  ineligibility_reasons?: string[];
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
  total?: number;
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
