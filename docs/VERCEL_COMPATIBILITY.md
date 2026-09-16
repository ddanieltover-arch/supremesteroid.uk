# Vercel compatibility review

This is an inspection of assumptions that break on Vercel/FrankenPHP. Local HTTP 200 does **not** mean the app is Vercel-ready. Production was **not** deployed in this phase.

## Current architecture

The customer storefront is Laravel + Inertia + React. `laravel-vite-plugin` compiles `resources/js/app.tsx` and `resources/css/app.css` into `public/build`. `Dockerfile.vercel` runs `pnpm build` in the frontend stage and copies `public/build` into the FrankenPHP image.

## Required production environment

- `APP_KEY`, `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false`
- `DATABASE_URL` (Neon **pooled** connection string) and `DB_SSLMODE=require`
- `SESSION_DRIVER=database`, `CACHE_STORE=database` (or Redis)
- `FILESYSTEM_DISK` / `OBJECT_STORAGE_DISK` = `s3` or `r2`
- `CRON_SECRET` — Vercel Cron sends `Authorization: Bearer $CRON_SECRET`
- Bank/crypto instruction env vars if those methods should display account details

## Cron

`vercel.json` schedules `GET /internal/cron/release-expired-inventory` every 5 minutes.

The endpoint is rejected unless `Authorization: Bearer <CRON_SECRET>` or `X-Cron-Secret` matches `store.cron_secret`. Re-running the cleanup is safe.

Vercel does **not** run `php artisan schedule:work`. The HTTP cron is the production mechanism.

## Remaining Vercel risks

| Issue | Notes |
|---|---|
| FrankenPHP listen port | `docker/vercel-entrypoint.sh` sets `SERVER_NAME=:${PORT}` at container start. Not verified by a local Docker build in every environment. |
| Filament product images | `ProductResource` FileUpload uses `config('filesystems.cloud')` and UUID filenames. Payment proofs are not a Filament upload. |
| Queue `sync` | Current jobs run in the request. Do not claim background processing. |
| Neon cold starts | `/api/health` opens PDO on every request |
| True reservation races | Guaranteed on Postgres `lockForUpdate`; SQLite tests are sequential |

## Mitigated in this phase

- Inertia production Vite pipeline (`public/build` + manifest)
- Authenticated inventory-expiry cron
- S3/R2 storage abstraction for payment proofs
- `DATABASE_URL` mapped onto the pgsql connection
- Mock SPA entry (`index.html` / `src/main.tsx`) removed
