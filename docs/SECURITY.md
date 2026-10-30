# Supreme Steroids - Security Baseline & Controls

## 1. Security Architecture Principles

Supreme Steroids implements a defense-in-depth posture addressing application, network, data, and access layers.

---

## 2. Implemented Security Controls

### 2.1 CSRF & Session Protections
- All state-changing HTTP requests (`POST`, `PUT`, `PATCH`, `DELETE`) require a valid CSRF token verified by Laravel middleware.
- Cookies use `HttpOnly=true`, `SameSite=Lax`, and `Secure=true` in production (`SESSION_SECURE_COOKIE` defaults to true when `APP_ENV=production` and the env var is unset).
- Sessions use the `database` driver in production to keep session state consistent across instances.

### 2.2 Injection & Mass Assignment Defense
- **SQL Injection:** Database access uses Eloquent ORM or parameterized queries. Raw string concatenation in queries is prohibited.
- **Mass Assignment:** Models define `$fillable` boundaries. Privilege fields on `User` (`role`, `is_active`, `email_verified_at`) and `Address.user_id` are not mass-assignable; trusted services set them via `forceCreate` / `forceFill`.
- **XSS Protection:** React JSX escapes dynamic values by default. CMS/blog HTML is admin-authored and rendered as trusted HTML (no public HTML input). Tracking links are restricted to `http:` / `https:` schemes.

### 2.3 Authentication, Hashing & Password Security
- Passwords use Laravel’s hashed cast / bcrypt (or argon when configured).
- Password rules default to minimum 8 characters with letters, mixed case, and numbers (`Password::defaults()`).
- Login, register, password reset, and verification endpoints are throttled.
- Inactive accounts (`is_active=false`) cannot sign in; the failure message matches invalid credentials (no account-status oracle).
- Filament MFA is not shipped in this baseline; admin access relies on role + `is_active` + panel gate.

### 2.4 Authorization & Privilege Isolation
- Role-Based Access Control (`UserRole` enum):
  - `CUSTOMER`: Public storefront, order tracking, address book, reviews.
  - `STAFF`: Order fulfillment, shipment label entry, customer support.
  - `MANAGER`: Inventory adjustments, catalogue compliance review, promotion management.
  - `SUPER_ADMIN`: Full system control, financial review, configuration, audit log inspection.
- Laravel Policies (`OrderPolicy`, `PaymentPolicy`, `ProductPolicy`, `UserPolicy`) enforce server-side checks. Frontend role checks are never trusted alone.
- Guest carts require matching `session_id` for mutations (authenticated users cannot mutate another browser’s guest cart).
- Guest payment-proof uploads require session order ownership and the same order status gate as authenticated `submitPaymentProof` (`PENDING_PAYMENT` / `PAYMENT_REVIEW`).

### 2.5 Rate limiting & trusted proxies
- Throttles apply to login/register, password reset, checkout writes, cart mutations, wishlist toggles, payment-proof uploads, search, and cron/readiness endpoints.
- Client IP for throttles comes from trusted proxies only (`TRUSTED_PROXIES`). Use `*` only behind a platform edge that sets `X-Forwarded-For` (for example Vercel). Use `127.0.0.1` for FrankenPHP behind local Caddy.

### 2.6 Audit Logging
- Privileged operations (payment approvals/rejections, compliance status changes, order cancellations, setting modifications) create immutable records in `audit_logs` capturing:
  - `actor_id` (admin user)
  - `event_type`
  - `ip_address`
  - `user_agent`
  - `old_values` (JSON)
  - `new_values` (JSON)
  - `timestamp`

### 2.7 Security-Conscious Headers
Application middleware and platform config inject:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`
- `Strict-Transport-Security` when `HSTS_ENABLED` is true (defaults true in production)

### 2.8 Operational secrets
- Cron and readiness routes require `CRON_SECRET` (Bearer or `X-Cron-Secret`), fail closed when empty, and are throttled.
- Payment proof uploads enforce MIME/size allowlists and private object storage in production.
- Public diagnostic scripts are not shipped under `public/`.
