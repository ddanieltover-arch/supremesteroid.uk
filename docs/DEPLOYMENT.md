# Deployment

The application is hosting-agnostic. Use `docs/DEPLOYMENT_STANDARD_SERVER.md` for a normal PHP, Docker, FrankenPHP, or Nginx + PHP-FPM deploy.

`Dockerfile.vercel` and `docs/VERCEL_COMPATIBILITY.md` remain for hosts that specifically deploy that container. They are optional.

## 1. Deployment model

The application runs in a normal PHP process. Local disk is ephemeral on any container host. Orders, payments, sessions, and cache belong in PostgreSQL. Durable uploads belong in S3-compatible object storage. Inventory cleanup runs through `php artisan schedule:run`. The HTTP cron endpoint is optional and calls the same releaser.

---

## 2. Prerequisites for Vercel Deployment

1. **Neon PostgreSQL Database:**
   - Create a project on [Neon.tech](https://neon.tech).
   - Obtain connection string with SSL: `postgres://neondb_owner:***@ep-***.eu-west-2.aws.neon.tech/neondb?sslmode=require`.
2. **External Object Storage (S3 / Cloudflare R2):**
   - Bucket: `supreme-steroids-assets`.
   - Access Key ID & Secret Access Key configured.
3. **Domain & DNS:**
   - Configure apex or CNAME routing for `supremesteroid.uk` to Vercel edge networks.

---

## 3. Required Environment Variables on Vercel

Add the following variables in the **Vercel Project Settings > Environment Variables**:

| Variable | Description | Example / Recommended Value |
|---|---|---|
| `APP_NAME` | Application Name | `Supreme Steroids` |
| `APP_ENV` | Environment Mode | `production` |
| `APP_KEY` | 32-character AES encryption key | Generated via `php artisan key:generate --show` |
| `APP_DEBUG` | Debug Flag | `false` |
| `APP_URL` | Production Domain URL | `https://supremesteroid.uk` |
| `DB_CONNECTION` | Database Driver | `pgsql` |
| `DATABASE_URL` | Pooled Connection String | Neon pooled endpoint with `?sslmode=require` |
| `SESSION_DRIVER` | Session Backend | `database` |
| `CACHE_STORE` | Cache Store | `database` |
| `QUEUE_CONNECTION` | Queue Driver | `sync` |
| `FILESYSTEM_DISK` | Storage Driver | `s3` |
| `AWS_ACCESS_KEY_ID` | Storage Access Key | Provided by AWS / R2 |
| `AWS_SECRET_ACCESS_KEY` | Storage Secret Key | Provided by AWS / R2 |
| `AWS_DEFAULT_REGION` | Storage Region | `auto` or `eu-west-2` |
| `AWS_BUCKET` | S3 Bucket Name | `supreme-steroids-assets` |
| `AWS_URL` | CDN / Custom Domain URL | `https://assets.supremesteroid.uk` |
| `LOG_CHANNEL` | Log Routing | `stderr` |

---

## 4. Multi-Stage Docker Build (`Dockerfile.vercel`)

The deployment employs a two-tier container compilation:
- **Tier 1 (Node 22 Builder):** Runs `npm ci` and `npm run build` using Vite to compile React, Inertia, and Tailwind assets into `public/build`.
- **Tier 2 (FrankenPHP Alpine):**
  - Installs PHP 8.3/8.5 with `pdo_pgsql`, `pgsql`, `opcache`, `intl`, `bcmath`.
  - Configures optimized production `php.ini` (OPcache preloading, memory 512M).
  - Runs `composer install --no-dev --optimize-autoloader`.
  - Injects compiled assets and binds to `0.0.0.0:3000`.

---

## 5. Deployment Commands & Migration Run

During deployment or initial release:
```bash
# 1. Run migrations against Neon PostgreSQL
php artisan migrate --force

# 2. Seed initial shipping zones and settings
php artisan db:seed --class=InitialFoundationSeeder --force

# 3. Optimize configuration and route caching
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
