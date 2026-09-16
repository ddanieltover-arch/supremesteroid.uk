# Supreme Steroids - Security Baseline & Controls

## 1. Security Architecture Principles

Supreme Steroids implements a defense-in-depth posture addressing application, network, data, and access layers.

---

## 2. Implemented Security Controls

### 2.1 CSRF & Session Protections
- All state-changing HTTP requests (`POST`, `PUT`, `PATCH`, `DELETE`) require a valid CSRF token verified by Laravel middleware.
- Cookies are configured with `HttpOnly=true`, `SameSite=Lax`, and `Secure=true` in production.
- Sessions are stored in the database or Redis rather than local files to prevent local session tampering in multi-instance environments.

### 2.2 Injection & Mass Assignment Defense
- **SQL Injection:** All database queries utilize Eloquent ORM or parameterized PDO queries. Raw string concatenation in queries is strictly prohibited.
- **Mass Assignment:** All Eloquent models explicitly define `$fillable` attributes or guarded boundaries to prevent parameter manipulation.
- **XSS Protection:** React JSX automatically escapes dynamic values. Server-side inputs undergo sanitization via Symfony HtmlSanitizer where rich-text is permitted.

### 2.3 Authentication, Hashing & Password Security
- Passwords are encrypted using Argon2id or Bcrypt with high cost factors.
- Multi-factor authentication (MFA / 2FA) ready via Filament and Laravel Fortify/Breeze patterns.
- Dedicated rate limiting prevents brute-force attempts on login (`5 attempts per minute`) and checkout (`10 requests per 5 minutes`).

### 2.4 Authorization & Privilege Isolation
- Role-Based Access Control (`UserRole` enum):
  - `CUSTOMER`: Public storefront, order tracking, address book, reviews.
  - `STAFF`: Order fulfillment, shipment label entry, customer support.
  - `MANAGER`: Inventory adjustments, catalogue compliance review, promotion management.
  - `SUPER_ADMIN`: Full system control, financial review, configuration, audit log inspection.
- Laravel Policies (`OrderPolicy`, `PaymentPolicy`, `ProductPolicy`, `UserPolicy`) enforce server-side validation on every privileged endpoint. Frontend role checks are never trusted on their own.

### 2.5 Audit Logging
- Privileged operations (payment approvals/rejections, compliance status changes, order cancellations, setting modifications) create immutable records in `audit_logs` capturing:
  - `actor_id` (admin user)
  - `event_type`
  - `ip_address`
  - `user_agent`
  - `old_values` (JSON)
  - `new_values` (JSON)
  - `timestamp`

### 2.6 Security-Conscious Headers
The Vercel and middleware configuration injects:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`
