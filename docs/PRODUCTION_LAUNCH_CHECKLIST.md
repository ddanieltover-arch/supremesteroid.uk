# Production launch checklist

Do not paste secret values into this file. Mark each item only after it has been checked on the real server.

## Infrastructure

- [ ] Ubuntu LTS server is reachable by SSH as `deploy`, not root login for daily work
- [ ] PHP 8.4.1+ and required extensions are installed
- [ ] Composer 2, Node.js 22, and pnpm 12.4.2 are installed
- [ ] FrankenPHP is installed and `supremesteroid.service` is enabled
- [ ] `/var/www/supremesteroid` uses releases, `current`, and `shared`
- [ ] `www-data` can write only `shared/storage` and `bootstrap/cache`

## Database

- [ ] Neon pooled `DATABASE_URL` is set for application traffic
- [ ] Direct `DATABASE_URL_UNPOOLED` is set for migrations
- [ ] `DB_SSLMODE=require`
- [ ] `php artisan migrate:status --database=pgsql_direct` shows no pending migrations
- [ ] No database reset or destructive rollback has been run

## Storage

- [ ] `OBJECT_STORAGE_DISK` is `s3` or `r2`
- [ ] Bucket blocks public listing
- [ ] Product images can be uploaded to the cloud disk
- [ ] Payment proofs are private and not guessable
- [ ] Local `storage/` is not the only copy of orders or proofs

## Mail

- [ ] SMTP host, port, username, and password are set outside Git
- [ ] From address is a controlled mailbox
- [ ] A test mailbox received order, payment, and shipping samples
- [ ] Password reset and email verification use the same mailer
- [ ] Automated tests were not pointed at real customers

## Payments

- [ ] Bank account name, sort code, account number, and reference hint are set
- [ ] Public crypto receiving addresses and network labels are set
- [ ] No wallet private key is in the environment or frontend
- [ ] A test payment submission can be reviewed by an authorized admin

## Security

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` exists, is stable, and is not in Git
- [ ] Session driver is `database` and cookies are secure
- [ ] `.env` is mode 640 and outside `public`
- [ ] Admin routes require an authorized role
- [ ] Upload size and MIME limits are active
- [ ] `HSTS_ENABLED` remains false until HTTPS has been confirmed

## Domain

- [ ] Apex DNS points at this server
- [ ] `www` DNS points at this server
- [ ] `APP_URL=https://supremesteroid.uk`
- [ ] `www` redirects to the apex

## SSL

- [ ] HTTPS loads without a certificate warning
- [ ] HTTP redirects to HTTPS
- [ ] After that check, HSTS can be enabled and rechecked

## Scheduler

- [ ] Crontab runs `php artisan schedule:run` every minute from `current`
- [ ] `php artisan schedule:list` shows `inventory:release-expired` every five minutes
- [ ] A second run does not reduce stock for an already released reservation
- [ ] The HTTP cron path is not required on this server

## Application

- [ ] `scripts/deploy.sh` completed without error
- [ ] `php artisan optimize` completed
- [ ] `public/build/manifest.json` exists in the release
- [ ] `GET /api/health` returns `operational` and no secrets
- [ ] `GET /up` returns success
- [ ] Readiness returns `READY` only with the bearer secret

## Admin

- [ ] An admin can sign in to Filament
- [ ] A non-admin cannot review payments
- [ ] Product image upload uses the cloud disk
- [ ] Audit log records payment review

## Checkout

- [ ] Guest or customer can add a test product to the cart
- [ ] Quantity update uses the server subtotal
- [ ] Shipping quote ignores a client-supplied subtotal
- [ ] Checkout creates an order without trusting client prices

## Ordering

- [ ] A test order has a payment record
- [ ] Inventory is reserved at order creation
- [ ] Another customer cannot open that order or its proof
- [ ] Approval marks payment paid and decrements stock
- [ ] Rejection does not mark the order paid

## Monitoring

- [ ] Daily logs are written under `shared/storage/logs`
- [ ] Log rotation is installed
- [ ] FrankenPHP journal shows a clean start
- [ ] Health checks are monitored from outside the server

## Backup

- [ ] Neon backup or point-in-time recovery is enabled
- [ ] Object-storage versioning is enabled if the provider supports it
- [ ] `shared/.env` is stored in a secret manager, not on the web
- [ ] A restore drill is scheduled

## Rollback

- [ ] At least two releases exist before launch traffic
- [ ] `scripts/rollback.sh` switches `current` without running `migrate:rollback`
- [ ] Operators know schema changes must stay compatible with the previous release

## Smoke test

Use a test product, test customer, and test payment data.

- [ ] Homepage loads
- [ ] Shop loads
- [ ] Search returns products
- [ ] Category page loads
- [ ] Product page loads
- [ ] Add to cart
- [ ] Shipping quote matches the server total
- [ ] Checkout creates a test order
- [ ] Payment submission is stored
- [ ] Admin can review the proof
- [ ] Approval or rejection follows the payment state machine
- [ ] Inventory reservation matches the order
- [ ] Order history is visible to that customer only
- [ ] Expected emails arrive at the test mailbox
- [ ] Mobile layout is usable
- [ ] Unknown URL returns the application 404
- [ ] `/api/health` and `/up` succeed and expose no credentials
