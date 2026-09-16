# Supreme Steroids - Application Architecture

## 1. Executive Summary

Supreme Steroids (`https://supremesteroid.uk`) is an enterprise-grade e-commerce application designed with a clean, modular architecture. The application is structured strictly for serverless and containerized deployment on **Vercel** with **FrankenPHP**, backed by **Neon PostgreSQL** and external object storage (AWS S3 / Cloudflare R2).

## 2. Technology Stack

- **Backend Framework:** Laravel 13.x
- **PHP Runtime:** PHP 8.3+ / 8.5 via FrankenPHP
- **Frontend Layer:** React 19, Inertia.js 2.x, TypeScript 5.8+, Tailwind CSS 4.x, Vite 6.x
- **Admin Panel:** Filament 4.x (architecturally isolated on separate routes/auth)
- **Database Engine:** PostgreSQL 16+ (Serverless Neon PostgreSQL in production)
- **Deployment Strategy:** Vercel Multi-Stage Docker with FrankenPHP

---

## 3. Core Architectural Boundaries

The application enforces strict separation of concerns across dedicated namespace layers:

```
app/
├── Actions/                 # Single-purpose domain transactions
│   ├── Compliance/          # Verification and audit actions
│   ├── Order/               # Order creation, status updates
│   ├── Payment/             # Payment verification, submission
│   └── Shipping/            # Shipping calculation and zone matching
├── Console/                 # Scheduled commands (stateless crons)
├── Enums/                   # Backed PHP enums for all domain finite states
│   ├── OrderStatus.php
│   ├── PaymentMethodType.php
│   ├── PaymentStatus.php
│   ├── ProductClassification.php
│   ├── ProductStatus.php
│   ├── ReviewStatus.php
│   ├── ShippingZoneType.php
│   └── UserRole.php
├── Events/                  # Domain lifecycle events
├── Exceptions/              # Domain-specific typed exceptions
├── Filament/                # Isolated administrative interface
│   ├── Resources/           # Filament resources (Products, Orders, Payments, Shipping, etc.)
│   └── Pages/               # Custom admin dashboards
├── Http/
│   ├── Controllers/         # Ultra-thin controllers routing requests
│   ├── Middleware/          # Inertia sharing, RBAC, security headers
│   └── Requests/            # Form requests encapsulating validation
├── Mail/                    # Mailable notifications
├── Models/                  # Eloquent entities with UUIDs and strict types
├── Notifications/           # Asynchronous customer and staff notifications
├── Policies/                # Gate authorization policies
├── Services/                # Complex domain orchestration services
│   ├── Audit/               # Security & administrative audit logging
│   ├── Compliance/          # Product classification & compliance guard
│   ├── Order/               # Order state transition machine
│   ├── Payment/             # Payment verification state machine
│   └── Shipping/            # Configurable dynamic shipping calculation engine
└── Support/                 # Value objects (Money, CryptoNetwork, AddressDto)
```

---

## 4. Compliance & Catalogue Guard Architecture

To ensure only lawful items can ever be exposed to consumers, the catalogue incorporates a mandatory compliance evaluation pipeline:

1. **State Enums:**
   - `ProductStatus`: `DRAFT`, `COMPLIANCE_REVIEW`, `ACTIVE`, `OUT_OF_STOCK`, `ARCHIVED`
   - `ProductClassification`: `OTC_CONSUMER`, `RESEARCH_USE`, `OTHER_LAWFUL_PRODUCT`
2. **Purchasable Guard Query Scope:**
   - Any query exposed to public storefront routes strictly applies the `purchasable()` scope:
     `status = ACTIVE` AND `classification IN (approved classifications)` AND `compliance_verified_at IS NOT NULL`.
   - Products in `DRAFT`, `COMPLIANCE_REVIEW`, or `ARCHIVED` throw a 404 on customer routes.
3. **Medical & Regulatory Safety Policy:**
   - Dosing calculators, drug-use instructions, steroid cycles, unverified laboratory claims, and fake certifications are explicitly forbidden at the schema and validation layers.

---

## 5. Order & Payment State Machines

### 5.1 Order States (`OrderStatus`)
- `PENDING_PAYMENT` -> `PAYMENT_REVIEW` -> `PAID` -> `PROCESSING` -> `SHIPPED` -> `DELIVERED`
- Terminal / abortive states: `CANCELLED`, `REFUNDED`
- Every transition writes an immutable audit record to `order_status_history` capturing `previous_status`, `new_status`, `actor_id`, `timestamp`, and `notes`.

### 5.2 Payment Architecture (`PaymentStatus`)
- `PENDING` -> `PAYMENT_SUBMITTED` -> `UNDER_REVIEW` -> `PAID` / `REJECTED` / `EXPIRED` / `REFUNDED`
- **Supported Methods:**
  1. **Manual Bank Transfer:** Includes payment reference, verified sender amount, timestamp, document reference.
  2. **Manual Cryptocurrency:** Includes network (BTC, USDT-TRC20, ETH, etc.), transaction hash, wallet destination, amount, explorer link.
- **Admin Review Enforcement:** Approval strictly requires an authorized staff action via Filament or API. No automated third-party webhooks can mark orders as paid without cryptographic verification or manual review.

---

## 6. Configurable Shipping Engine

Shipping prices and rules are completely dynamic and persisted in the database (`shipping_zones`, `shipping_methods`, `shipping_rules`):
- **UK Standard Delivery:** £10
- **UK Express Delivery:** £15
- **UK Discreet Delivery:** £25
- **Free UK Delivery:** £0 on eligible orders >= £300
- **Europe Zone:** £35
- **International Zone:** £50

Administrators can update base rates, thresholds, weight surcharges, and country availability directly via the Filament Admin panel without changing frontend code.

---

## 7. Vercel Serverless / Ephemeral Filesystem Strategy

Because Vercel executes code in ephemeral containers:
1. **Sessions & Caches:** Backed by database tables in Neon PostgreSQL (`sessions`, `cache`) or managed Redis (Upstash).
2. **File Storage:** Local disk is treated as read-only (except `/tmp`). All media, logos, and payment proof attachments use AWS S3 / Cloudflare R2 via Flysystem.
3. **Queue Processing:** Configured for `sync` in serverless or delegated to an external HTTP webhook/SQS dispatcher.

See `docs/VERCEL_COMPATIBILITY.md` for the current Vercel inspection. See `docs/CHECKOUT_PIPELINE.md` for inventory reservation, pricing snapshots, and idempotency rules.

## 8. Catalogue → Checkout authority

- Public catalogue queries must use `Product::purchasable()` / `published()`.
- Purchase eligibility is owned by `ProductPurchaseEligibilityService`.
- Money is owned by `PricingService` (integer pence).
- Checkout snapshots are owned by `CheckoutSessionService`.
- Order creation is owned by `OrderCreationService` inside a single transaction.
- Customers may only view their own orders (`OrderPolicy`).

