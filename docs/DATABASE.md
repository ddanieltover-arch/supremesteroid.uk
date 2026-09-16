# Supreme Steroids - Database Schema & Foundation

## 1. Overview

The database is built on **PostgreSQL 16+** using Neon's serverless driver in production. All primary entity IDs utilize UUID v7 or ULID for distributed generation without collisions. Foreign key constraints, cascading policies, composite indexes, and strict typing are applied across all tables.

---

## 2. Core Tables and Relationships

### 2.1 Identity, RBAC & Addresses
- **`users`**: Customer and administrative accounts (`id`, `name`, `email`, `password`, `role` enum, `email_verified_at`, `created_at`, `updated_at`, `deleted_at`).
- **`addresses`**: User shipping and billing locations (`id`, `user_id`, `type`, `full_name`, `address_line_1`, `address_line_2`, `city`, `postal_code`, `country_code`, `is_default`).

### 2.2 Product Catalogue & Compliance
- **`categories`**: Hierarchical category tree with slug, parent_id, SEO metadata.
- **`brands`**: Manufacturer details, certified legal provenance.
- **`products`**:
  - `id` (UUID)
  - `sku`, `name`, `slug`
  - `status` (`DRAFT`, `COMPLIANCE_REVIEW`, `ACTIVE`, `OUT_OF_STOCK`, `ARCHIVED`)
  - `classification` (`OTC_CONSUMER`, `RESEARCH_USE`, `OTHER_LAWFUL_PRODUCT`)
  - `price_amount`, `currency` (GBP)
  - `compliance_notes`, `compliance_verified_at`, `compliance_officer_id`
  - `brand_id`, `category_id`
  - `description`, `ingredients`, `usage_notes`
  - `softDeletes`, `timestamps`
- **`product_variants`**: Specific pack sizes, strengths, or options.
- **`product_images`**: S3/CDN object keys, display order, alt text.
- **`product_attributes`**: Lab verification references, batch numbers, certificates of analysis.
- **`product_categories`**: Pivot for secondary categories.

### 2.3 Inventory Management
- **`inventory`**: Tracked stock quantities per variant/warehouse (`sku`, `quantity_on_hand`, `quantity_reserved`, `safety_stock`).
- **`inventory_movements`**: Auditable stock movements (`order_deduction`, `manual_restock`, `damage_writeoff`, `reference_id`, `actor_id`).

### 2.4 Orders & State Tracking
- **`orders`**:
  - `id` (UUID), `order_number` (e.g. `SS-2026-XXXXX`)
  - `user_id`, `status` (`OrderStatus` enum)
  - `subtotal_amount`, `shipping_amount`, `total_amount`, `currency`
  - `shipping_method_id`, `shipping_address_id`, `billing_address_id`
  - `created_at`, `updated_at`
- **`order_items`**: Order line items capturing snapshot unit price, quantity, and product metadata.
- **`order_status_history`**: Immutable audit logs of status transitions (`previous_status`, `new_status`, `actor_id`, `notes`, `created_at`).

### 2.5 Payments & Manual Verification
- **`payments`**: Overall payment record linked to an order (`amount`, `currency`, `method` enum, `status` enum).
- **`payment_submissions`**:
  - Bank Transfer details: `payment_reference`, `bank_sender_name`, `transfer_date`, `receipt_file_url`.
  - Cryptocurrency details: `crypto_network` (BTC, USDT-TRC20, etc.), `tx_hash`, `wallet_address`, `amount_crypto`.
  - Admin review fields: `reviewed_by`, `reviewed_at`, `admin_notes`, `approval_status`.

### 2.6 Dynamic Shipping Engine
- **`shipping_zones`**: Geographic zones (`UK Domestic`, `Europe`, `International`) matching ISO country codes.
- **`shipping_methods`**: Delivery options (`Standard`, `Express`, `Discreet`).
- **`shipping_rules`**: Flat rates, free shipping thresholds (e.g., Free UK Delivery on orders >= £300), weight calculations.

### 2.7 Content, Reviews & Engagement
- **`wishlists` & `wishlist_items`**: Customer saved items.
- **`reviews`**: Moderated product reviews (`rating`, `comment`, `status`, `verified_purchase`).
- **`pages` & `blog_posts`**: Informational content, company policy, and announcements.
- **`seo_metadata`**: Polymorphic or entity-attached meta titles, descriptions, canonical URLs, and OpenGraph tags.
- **`settings`**: Key-value store for site parameters, logo URLs, and support contact details.
- **`audit_logs`**: Tamper-evident record of administrative actions and sensitive operations.
