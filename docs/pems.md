# Pulse Engineering Memory — Supreme Steroids

## Project profile

Supreme Steroids is a Laravel 13 + Inertia + React + Filament commerce application. The customer storefront is a single Laravel/Inertia application. No loyalty, referrals, PWA, or branding redesign in this phase.

## Technology profile

Laravel 13, PHP 8.3+, React 19, Inertia, TypeScript, Tailwind 4, Filament 4, PostgreSQL/Neon, UUID keys, PHPUnit 11, Vite 6 + laravel-vite-plugin.

## Architecture profile

Service/action architecture with policies. Authoritative services: ProductPurchaseEligibilityService, PricingService, CartService, CheckoutSessionService, OrderCreationService, InventoryReservationService, ShippingEngine, PaymentService, PaymentStateMachine, OrderStateMachine, AuditLogger, AdminMetricsService, ObjectStorageService, SeoService, WishlistService.

Frontend: `resources/js/app.tsx` is the only customer entry. Mock SPA under `src/` is deprecated.

## Decisions

- Inventory reserved at order creation with row locks; permanently decremented only when payment is PAID.
- Client prices are never trusted. Checkout snapshots are server-calculated.
- Public storefront uses purchasable/published scopes only.
- Idempotency key on orders is unique; replays return the original order.
- Human order numbers use `SS-YYYY-000001`.
- Payment proofs are stored as object-storage metadata (`stored_files`), not on the Vercel filesystem.
- Vercel Cron hits `/internal/cron/release-expired-inventory` with `CRON_SECRET`.

## Active work

Production hardening: pooled Neon config, readiness endpoint, persistent Filament/product image disk, secret-gated cron, canonical host. Production deploy not executed in this phase.

## Risks

Do not claim production-ready until Neon migrate, Vercel env, object storage, mail, cron secret, and a real deploy are verified. Queue remains `sync`. No Redis was added.
