# Standard server deployment

The always-on Linux procedure is `docs/PRODUCTION_LINUX_DEPLOYMENT.md`. This file remains the hosting-agnostic reference. `Dockerfile.vercel` is optional.

Do not commit secret values. Do not reset the database.

## PHP requirements

- PHP 8.4.1 or newer. `composer.lock` requires this through Symfony 8.1.
- Composer 2
- Node.js 22 and pnpm 12 for the frontend build

Required extensions: `pdo_pgsql`, `pgsql`, `intl`, `zip`, `bcmath`, `opcache`, `pcntl`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `curl`, `fileinfo`.

## Install

```bash
composer install --no-dev --optimize-autoloader
corepack enable && corepack prepare pnpm@12.4.2 --activate
pnpm install --frozen-lockfile
pnpm run build
php artisan migrate --force
php artisan config:cache
php artisan view:cache
php artisan event:cache
```

Do not run `route:cache` if route files contain closures.

Neon migrations must use the direct connection, not the pooled URL:

```bash
php artisan migrate --database=pgsql_direct --force
```

`down()` migrations are destructive and are not part of a normal deploy.

## Environment

Copy `.env.example` and set production values. `APP_KEY` is generated once with `php artisan key:generate` and reused. Changing it invalidates encrypted cookies, sessions, and encrypted columns.

Production must set `APP_ENV=production`, `APP_DEBUG=false`, and `APP_URL=https://supremesteroid.uk`.

### Application

`APP_NAME` `APP_ENV` `APP_KEY` `APP_DEBUG` `APP_URL` `APP_TIMEZONE` `APP_LOCALE` `LOG_CHANNEL` `LOG_LEVEL`

### Database

`DB_CONNECTION` `DB_HOST` `DB_PORT` `DB_DATABASE` `DB_USERNAME` `DB_PASSWORD` `DB_SSLMODE`

Neon only, when used: `DATABASE_URL` (pooled, request traffic) `DATABASE_URL_UNPOOLED` (direct, migrations). Do not also invent a second password for a non-Neon host. `PG*` and `POSTGRES_*` are Neon template aliases. Set them only if the host injects those names. Laravel reads `DATABASE_URL` and `DATABASE_URL_UNPOOLED`.

### Session

`SESSION_DRIVER=database` in production. `SESSION_LIFETIME` `SESSION_ENCRYPT` `SESSION_SECURE_COOKIE` `SESSION_DOMAIN` `SESSION_SAME_SITE`

If `SESSION_SECURE_COOKIE` is unset and `APP_ENV=production`, cookies are marked secure.

### Cache

`CACHE_STORE=database` in production. `CACHE_PREFIX`

Do not use `file` for sessions or cache when more than one app instance is running.

### Queue

`QUEUE_CONNECTION=sync` is the current supported mode. No background worker is running. `database` and `redis` can be selected later without changing checkout or payment code, because no job classes are dispatched yet.

Later candidates for a queue: bulk customer email, image derivatives, and large exports. Do not claim those run in the background now.

### Mail

`MAIL_MAILER` `MAIL_HOST` `MAIL_PORT` `MAIL_USERNAME` `MAIL_PASSWORD` `MAIL_FROM_ADDRESS` `MAIL_FROM_NAME`

Covers order placement, payment acknowledgement, approval/rejection, shipping, password reset, and email verification. Tests use `MAIL_MAILER=array`.

### Object storage

`OBJECT_STORAGE_DISK` `FILESYSTEM_DISK` `AWS_ACCESS_KEY_ID` `AWS_SECRET_ACCESS_KEY` `AWS_DEFAULT_REGION` `AWS_BUCKET` `AWS_URL` `AWS_ENDPOINT` `AWS_USE_PATH_STYLE_ENDPOINT`

Cloudflare R2 uses the same S3 driver with `R2_ACCESS_KEY_ID` `R2_SECRET_ACCESS_KEY` `R2_BUCKET` `R2_ENDPOINT` `R2_URL` and `OBJECT_STORAGE_DISK=r2`.

`UPLOAD_MAX_KILOBYTES` `OBJECT_STORAGE_SIGNED_URL_MINUTES`

Production must not use `local` or `public` for durable files.

### Bank transfer

`BANK_ACCOUNT_NAME` `BANK_SORT_CODE` `BANK_ACCOUNT_NUMBER` `BANK_IBAN` `BANK_BIC` `BANK_NAME` `BANK_REFERENCE_HINT`

### Crypto

`CRYPTO_BTC_ADDRESS` `CRYPTO_ETH_ADDRESS` `CRYPTO_USDT_TRC20_ADDRESS` `CRYPTO_USDT_ERC20_ADDRESS` `CRYPTO_REFERENCE_HINT`

These are public receiving addresses and customer instructions, not wallet private keys.

### Cron

`CRON_SECRET`

Required only for the optional HTTP scheduler. A standard server does not need it if `schedule:run` is installed.

### Analytics

No analytics provider is configured. Do not add a vendor key until one is chosen.

### Security

`SESSION_ENCRYPT` `SESSION_SECURE_COOKIE` `BCRYPT_ROUNDS`

## Filesystem

| Use | Class | Store |
|---|---|---|
| Compiled views, config cache, framework cache | Ephemeral | `storage/framework`, `bootstrap/cache` |
| Log files if `LOG_CHANNEL=stack` | Ephemeral | `storage/logs` or stderr |
| Product images, payment proofs, future CMS media | Persistent | `ObjectStorageService` / `OBJECT_STORAGE_DISK` |
| Orders, payments, inventory, users, sessions, cache rows | Persistent | PostgreSQL |

Payment proofs are private. Customers can download only their own proof. Staff access is policy-controlled. Filament product image uploads use the configured cloud disk and generated filenames.

## Scheduler

`routes/console.php` schedules `inventory:release-expired` every five minutes. The command and the HTTP endpoint both call `ExpiredReservationReleaser`. Re-running it does not release rows that are already released.

Standard server cron:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Hosts that cannot run cron may instead call `GET /internal/cron/release-expired-inventory` with `Authorization: Bearer $CRON_SECRET`. That path is optional. It is not the only supported scheduler.

## Web server

FrankenPHP and the generic `Dockerfile` serve `public/index.php`. Nginx + PHP-FPM should use `docker/nginx.conf`: the document root is `public`, and all non-file requests go to `index.php`. Do not point the document root at the project root.

Trust proxies is enabled so HTTPS is detected behind either server.

## Domain and HTTPS

Canonical host is `https://supremesteroid.uk`. In production, `www.supremesteroid.uk` redirects to that host with HTTP 301. Terminate TLS at the reverse proxy or load balancer. Point the apex and `www` records at the host. Set `APP_URL` to the canonical HTTPS URL.

A restrictive Content-Security-Policy is not enabled. It has not been tested against Inertia, Vite, and Filament.

## Docker

```bash
docker build -t supreme-steroids .
docker run --env-file .env -p 8080:8080 supreme-steroids
```

The image does not contain secrets. Set `RUN_MIGRATIONS=true` only when this container should apply outstanding migrations. `Dockerfile.vercel` is a separate image for a Vercel container deploy and is not required here.

## Backups

Back up PostgreSQL and the object-storage bucket. Do not treat `storage/` or `bootstrap/cache` as a backup. Test a restore before relying on it.

## Rollback

1. Deploy the previous application release.
2. Do not run `migrate:rollback` unless that release's `down()` methods were reviewed and no customer data would be dropped.
3. Keep the previous `APP_KEY`.
4. Confirm `/api/health` returns `operational` and `/up` returns success.
