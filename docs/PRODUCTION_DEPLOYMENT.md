# Production deployment checklist

This document lists configuration by **name only**. Do not commit secret values. A successful local test run is not a production deployment.

## Canonical hostname

- Canonical host: `https://supremesteroid.uk`
- `www.supremesteroid.uk` redirects with HTTP 301 to the `APP_URL` host in production.
- Vercel must attach both hostnames and issue HTTPS certificates.
- DNS: apex `A`/`ALIAS` to Vercel, `www` `CNAME` to the Vercel project. Do not point either record at a local machine.
- `APP_URL` must be `https://supremesteroid.uk` after the domain is connected. Until then, the Vercel hostname is temporary and the site is not domain-ready.

## Neon

- Request traffic uses the **pooled** `DATABASE_URL` (`-pooler` host) via the `pgsql` connection.
- Migrations use the **direct** connection: `php artisan migrate --database=pgsql_direct --force`
- `DATABASE_URL_UNPOOLED` (or `DB_DIRECT_URL`) must be the non-pooler Neon URL.
- `DB_SSLMODE=require`.
- Migration `0001_01_01_000008_create_stored_files_and_payment_proof_metadata` is additive: new `stored_files` table, nullable `payment_submissions.proof_file_id`, unique wishlist pair only when no duplicates exist. Its `down()` is destructive and must not be run.
- Do not reset the database.

## Persistent data dependencies

| Data | Store |
|---|---|
| Products, orders, payments, inventory, users, sessions, cache, jobs table | PostgreSQL |
| Payment proofs, product images, CMS media | Object storage (`OBJECT_STORAGE_DISK`) |
| `storage/` and `bootstrap/cache` | Ephemeral container cache only |
| Queue | `sync` (in-request). No Redis in this phase. |

Future work that should not stay on `sync`: bulk customer email, image derivatives, large exports, and any job that must survive a container restart.

## Mail

`MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` stay in the environment.

Application mail covers order placement, payment submission acknowledgement, payment approval/rejection, and shipping. Password reset and email verification use framework notifications once mail is configured. Tests use `MAIL_MAILER=array` and must not send real email.

## Cron

Vercel schedules `GET /internal/cron/release-expired-inventory` (`*/5 * * * *`). The application validates `Authorization: Bearer CRON_SECRET`. Vercel does not run `schedule:work`. Re-running the command only releases reservations still marked `ACTIVE`.

Readiness is `GET /internal/readiness` with the same bearer secret. It returns `READY` or `NOT_READY` and does not include secret values.

## Security headers

Responses set `nosniff`, `DENY` framing, referrer policy, permissions policy, and HSTS on HTTPS/production. A restrictive Content-Security-Policy was **not** added because it was not tested against Inertia, Vite, and Filament and could blank the storefront.

## Environment variable names

`APP_KEY` `APP_ENV` `APP_DEBUG` `APP_URL` `APP_NAME`

`DB_CONNECTION` `DATABASE_URL` `DATABASE_URL_UNPOOLED` `DB_SSLMODE`

`SESSION_DRIVER` `SESSION_SECURE_COOKIE` `CACHE_STORE` `QUEUE_CONNECTION` `LOG_CHANNEL`

`OBJECT_STORAGE_DISK` `FILESYSTEM_DISK` `AWS_ACCESS_KEY_ID` `AWS_SECRET_ACCESS_KEY` `AWS_DEFAULT_REGION` `AWS_BUCKET` `AWS_URL` `AWS_ENDPOINT` `AWS_USE_PATH_STYLE_ENDPOINT` `R2_ACCESS_KEY_ID` `R2_SECRET_ACCESS_KEY` `R2_BUCKET` `R2_ENDPOINT` `R2_URL` `UPLOAD_MAX_KILOBYTES` `OBJECT_STORAGE_SIGNED_URL_MINUTES`

`BANK_ACCOUNT_NAME` `BANK_SORT_CODE` `BANK_ACCOUNT_NUMBER` `BANK_IBAN` `BANK_BIC` `BANK_NAME` `BANK_REFERENCE_HINT` `CRYPTO_BTC_ADDRESS` `CRYPTO_ETH_ADDRESS` `CRYPTO_USDT_TRC20_ADDRESS` `CRYPTO_USDT_ERC20_ADDRESS` `CRYPTO_REFERENCE_HINT`

`CRON_SECRET`

`MAIL_MAILER` `MAIL_HOST` `MAIL_PORT` `MAIL_USERNAME` `MAIL_PASSWORD` `MAIL_FROM_ADDRESS` `MAIL_FROM_NAME`

`APP_KEY` must be generated once and reused. Changing it invalidates encrypted cookies, sessions, and encrypted columns. Never commit it.

## Deploy commands (manual if CLI is unauthenticated)

```bash
php artisan migrate --database=pgsql_direct --force
php artisan migrate:status
npx vercel --prod
```

Set the environment names above in the Vercel project before the production deploy. After deploy, check `/api/health` (status only), then `GET /internal/readiness` with the cron bearer token.

## Post-deploy smoke test

Use a test product, test customer, and test payment data. Do not use a real customer payment.

1. GET `/`
2. GET `/shop`
3. Open a product
4. Add the product to the cart
5. Update quantity
6. Confirm the subtotal is the server total
7. Confirm the shipping quote is the server quote
8. Proceed to checkout
9. Create a test order
10. Confirm a payment record exists
11. Submit a payment proof using test data
12. Confirm an admin can review that proof
13. Confirm another customer cannot open the order or proof
14. Confirm inventory is reserved
15. Run `inventory:release-expired` against expired test reservations
16. Confirm the cron endpoint rejects a bad secret
17. Confirm the cron endpoint accepts the correct secret
18. Check the mobile layout
19. Confirm the browser console has no critical errors
