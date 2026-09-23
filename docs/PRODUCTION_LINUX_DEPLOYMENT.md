# Linux production deployment

Primary runtime: Ubuntu LTS, FrankenPHP, Laravel 13, Neon PostgreSQL, S3 or R2, external SMTP. Nginx + PHP-FPM is an alternative and must not run at the same time as FrankenPHP. Vercel files remain in the repo and are not required for this deploy.

Do not commit filled environment files. Do not reset the database. Do not run `migrate:fresh`, `migrate:rollback`, or `migrate:reset`.

## Directory layout

```text
/var/www/supremesteroid/
  current -> releases/YYYYMMDDHHMMSS
  releases/
  shared/.env
  shared/storage/
```

`current` is the only live release. FrankenPHP's document root is `/var/www/supremesteroid/current/public`. The rest of the tree is not the web root.

Create the tree before the first deploy:

```bash
sudo mkdir -p /var/www/supremesteroid/releases /var/www/supremesteroid/shared/storage/{app/public,framework/cache/data,framework/sessions,framework/views,logs}
sudo chown -R deploy:www-data /var/www/supremesteroid
sudo chmod 750 /var/www/supremesteroid
sudo chmod 640 /var/www/supremesteroid/shared/.env
sudo chmod 770 /var/www/supremesteroid/shared/storage
```

Copy `.env.production.example` to `/var/www/supremesteroid/shared/.env` and fill it on the server.

## Permissions

- Deploy as the `deploy` user. Do not run `scripts/deploy.sh` as root.
- FrankenPHP runs as `www-data`.
- Application code, `vendor`, and `public/build` are owned by `deploy` and are not writable by `www-data`.
- `www-data` may write only `shared/storage` and `current/bootstrap/cache`.
- Do not use `chmod -R 777`.
- `.env` is mode `640`, group `www-data`, and lives outside `public`.

## Server preparation

```bash
sudo apt update
sudo apt install -y git unzip curl ca-certificates
php -v
composer --version
node --version
pnpm --version
```

Required PHP is 8.4.1 or newer. Extensions: `pdo_pgsql`, `pgsql`, `intl`, `zip`, `bcmath`, `opcache`, `pcntl`, `mbstring`, `tokenizer`, `xml`, `ctype`, `curl`, `fileinfo`.

Install FrankenPHP from the official package and confirm `frankenphp version`. Install Node.js 22 and pnpm 12.4.2.

## Deploy

```bash
DEPLOY_REPO=git@github.com:ORG/supremesteroid.uk.git DEPLOY_BRANCH=main ./scripts/deploy.sh
```

The script fails on the first error. It retrieves a release, installs Composer production dependencies, installs and builds the frontend with a frozen lockfile, runs outstanding migrations, links `public/storage`, and runs `php artisan optimize`. It never drops or rebuilds the database.

When `DATABASE_URL_UNPOOLED` is set, migrations use `php artisan migrate --database=pgsql_direct --force`. Application traffic uses the pooled `DATABASE_URL`.

After a successful deploy, reload FrankenPHP:

```bash
sudo systemctl reload supremesteroid
```

`deploy/systemd/supremesteroid.service` uses `SIGUSR2` for that reload so existing requests can finish.

## FrankenPHP

The public HTTPS process is Caddy. FrankenPHP listens only on `127.0.0.1:8080`. Use `docs/ORACLE_CLOUD_DEPLOYMENT.md` for the Ubuntu ARM64 server. Do not open port 8080 in the firewall.

Do not enable application HSTS until that redirect has been checked in a browser. Leave `HSTS_ENABLED=false`. After HTTPS is stable, set `HSTS_ENABLED=true`, reload PHP config, and confirm the `Strict-Transport-Security` header. Start with a short max-age on a staging host before using the one-year value already coded for the opt-in flag.

Forwarded headers: this Caddyfile terminates TLS itself, so Laravel does not need a second HTTPS redirect. Trust-proxy support remains enabled for a later proxy.

## Nginx alternative

Use this only if FrankenPHP is not installed. Stop the `supremesteroid` systemd unit first. Document root must be `current/public`. `docker/nginx.conf` is the route map: static files are served directly, and every other request goes to `index.php`. Deny access to dotfiles. Do not point Nginx at the project root.

## Scheduler

The Linux scheduler is authoritative. `routes/console.php` runs `inventory:release-expired` every five minutes with overlap protection. Install this crontab for `www-data`:

```bash
* * * * * cd /var/www/supremesteroid/current && php artisan schedule:run >> /dev/null 2>&1
```

Verify:

```bash
cd /var/www/supremesteroid/current
php artisan schedule:list
php artisan schedule:run -v
```

The HTTP endpoint `GET /internal/cron/release-expired-inventory` remains for hosts that cannot run cron. A normal Linux server does not need `CRON_SECRET` for inventory cleanup. Readiness still expects the secret if that check is used.

## Queue

Keep `QUEUE_CONNECTION=sync`. Do not install Redis for this deploy. No background worker is running. Later, `database` or `redis` can be selected without changing checkout code. Bulk email, image derivatives, and large exports are the later queue candidates.

## Logging

Production uses `LOG_CHANNEL=daily` and `LOG_LEVEL=info`. Files are `shared/storage/logs/laravel-YYYY-MM-DD.log`. Laravel keeps 14 days. `deploy/logrotate/supremesteroid` compresses and retains 14 rotated files. Connection URLs and obvious secret assignments are redacted. Do not log passwords, tokens, payment credentials, database passwords, `CRON_SECRET`, or private wallet keys. Monitor disk use on `/var/www/supremesteroid/shared/storage/logs` and FrankenPHP journal logs with `journalctl -u supremesteroid`.

## Database

`php artisan migrate:status` must show no pending migrations before go-live. Use the direct Neon connection for that command when checking from an operator shell. Do not print the URL. Schema changes must stay readable by the previous release so `scripts/rollback.sh` can switch code without reversing migrations.

## Object storage

Set `OBJECT_STORAGE_DISK=s3` or `r2`. Product images, payment proofs, and future CMS media must not be stored only on the server disk. Payment proofs are private. Bucket policy: block public listing; public read only for the product-image prefix if images are served directly; no public read on `payment_proofs/`. Prefer signed URLs or the authorized download route.

## Mail

Set the SMTP variables in `shared/.env`. The application sends order confirmation, payment submission acknowledgement, payment approval or rejection, and shipping mail. Password reset and email verification use framework notifications. Send a test to an internal mailbox only.

## Domain and DNS

Canonical URL: `https://supremesteroid.uk`.

- Apex `A` or `AAAA` record to the server.
- `www` `CNAME` or `A` to the same server.
- Caddy and the production canonical-host middleware send `www` to the apex with HTTP 301.
- `APP_URL=https://supremesteroid.uk`.

## Backups

Use Neon point-in-time recovery or scheduled dumps. Enable object-storage versioning on the bucket if the provider offers it. Back up `shared/.env` to a password manager or an offline secret store, not into `public/` or the bucket that the application can list. Do not put database passwords in a web-accessible backup.

## Rollback

```bash
./scripts/rollback.sh
./scripts/rollback.sh /var/www/supremesteroid/releases/YYYYMMDDHHMMSS
```

This switches the `current` symlink to the previous release and reloads FrankenPHP. It does not roll back migrations. Deploy additive schema changes only.

## Health

- `GET /api/health` returns `operational`, `unavailable`, or `misconfigured`. It does not return hosts, usernames, keys, or environment values.
- `GET /up` is the framework health route.
- `GET /internal/readiness` requires `Authorization: Bearer $CRON_SECRET` and returns `READY` or `NOT_READY`.

## Verification commands

```bash
php -v
composer --version
node --version
pnpm --version
cd /var/www/supremesteroid/current
php artisan about
php artisan migrate:status --database=pgsql_direct
php artisan optimize
php artisan schedule:list
php artisan test
pnpm exec tsc --noEmit
pnpm build
```

`php artisan test` is for a copy that still has dev dependencies. Do not install PHPUnit into the live production tree unless you are running a release candidate in a separate directory.

## Security

`APP_DEBUG=false`. Session cookies are secure, encrypted, and `SameSite=lax`. CSRF applies to web forms. Cron and readiness routes are throttled. Admin actions use policies and audit logs. Uploads have a size cap, an allowlist, and generated storage keys. The web root is `public`, so `storage`, `.env`, `vendor`, and config files are not served.
