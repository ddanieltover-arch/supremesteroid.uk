# Catalogue → Cart → Checkout → Order Pipeline

## Inventory reservation decision

Inventory is **revalidated** when a cart is calculated and when a checkout snapshot is prepared.

Inventory is **reserved** only when an order is created, inside the same database transaction as the order, order items, payment record, and audit event. Reservations use `lockForUpdate()` on the inventory row so concurrent checkouts cannot oversell.

Permanent decrement of `quantity_on_hand` happens **only when payment is approved (PAID)** via `InventoryReservationService::fulfillOrderReservations()`.

Reservations are **released** when:

- the order is cancelled
- payment is rejected
- payment expires
- the reservation `expires_at` window elapses (`inventory:release-expired`)

Cart quantity is **not** a reservation. Adding an item to a cart does not hide stock from other customers.

## Pricing authority

`PricingService` is the only money calculator. It uses integer pence (and `bcmul` when available). React-supplied unit prices, shipping amounts, discounts, and grand totals are stripped in `StoreCheckoutRequest` and never persisted.

Cart lines always show **current** catalogue prices. When an order is created, item names, SKUs, unit prices, and totals are snapshotted. Later product or account changes cannot rewrite those rows.

## Checkout snapshot

`CheckoutSessionService::prepareCheckout()` persists a server-side snapshot and returns a `checkout_token`. Order creation may reuse that token. If live prices no longer match the snapshot, `PriceChangedException` is thrown and no order is created.

## Idempotency

`orders.idempotency_key` is unique. Replaying the same key for the same customer returns the existing order. Unique constraint violations are recovered rather than inserting a duplicate.
