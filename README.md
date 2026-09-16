# Supreme Steroids (https://supremesteroid.uk)

Production-oriented e-commerce application foundation for **Supreme Steroids**. Architected for high security, testability, compliance rigor, and serverless execution on **Vercel** with **FrankenPHP** and **Neon PostgreSQL**.

---

## 1. Technology Stack

- **Backend:** Laravel 13.x, PHP 8.3+ / 8.5 (FrankenPHP)
- **Frontend:** React 19, Inertia.js 2.x, TypeScript 5.8+, Tailwind CSS 4.x, Vite 6.x
- **Admin Panel:** Filament 4.x (isolated from consumer UI)
- **Database:** PostgreSQL 16+ / Neon PostgreSQL in production
- **Deployment:** Vercel Multi-Stage Docker with FrankenPHP (`Dockerfile.vercel`)
- **Storage:** AWS S3 / Cloudflare R2 via Flysystem

---

## 2. Store Identity & Legal Reference

- **Store Display Name:** Supreme Steroids
- **Legal/Company Name:** Supreme Steroids
- **Business Address:** 8 King Street, Hammersmith, London W6 9HW, United Kingdom
- **Support Email:** [info@supremesteroid.uk](mailto:info@supremesteroid.uk)
- **Primary Domain:** `https://supremesteroid.uk`
- **Logo Configuration:** Managed via `STORE_APPROVED_LOGO_URL` in `.env` / `config/store.php` for persistent CDN delivery.

---

## 3. Local Development Setup

### 3.1 Prerequisites
- PHP 8.3+ or FrankenPHP
- Composer 2.x
- Node.js 20+ & npm
- PostgreSQL 16+ (or local SQLite for testing)

### 3.2 Installation Steps

```bash
# 1. Clone repository
git clone https://github.com/supremesteroids/storefront.git
cd storefront

# 2. Copy environment file
cp .env.example .env

# 3. Install PHP dependencies
composer install

# 4. Generate application encryption key
php artisan key:generate

# 5. Install Node dependencies
npm install

# 6. Run database migrations and seeders
php artisan migrate --seed

# 7. Start local development server
php artisan serve --port=8000
# In a separate terminal, start Vite frontend
npm run dev
```

---

## 4. Database Setup & Migrations

```bash
# Run all pending database migrations
php artisan migrate

# Rollback and re-run migrations with initial foundation seeders
php artisan migrate:fresh --seed

# Run specific seeder
php artisan db:seed --class=InitialFoundationSeeder
```

---

## 5. Testing

```bash
# Run test suite via PHPUnit / Artisan
php artisan test

# Run frontend type-check
npm run lint

# Compile frontend build
npm run build
```

---

## 6. Vercel Deployment Overview

Deployments to Vercel use the provided `Dockerfile.vercel` and `vercel.json`:
1. Push to the `main` branch connected to Vercel.
2. Ensure required production environment variables (`DATABASE_URL`, `APP_KEY`, `AWS_*`) are configured in Vercel Project Settings.
3. The multi-stage Docker build compiles the Vite frontend, prepares the FrankenPHP worker, and serves the application with sub-millisecond cold starts.

---

## 7. Important Architectural Decisions

1. **Stateless Container Model:** All sessions and caches are kept in Neon PostgreSQL or managed Redis. No local persistent filesystem is assumed.
2. **Strict Product Compliance:** Lawful products only. Every product is guarded by `ProductStatus` and `ProductClassification` enums with a mandatory `purchasable` query scope.
3. **Auditable Manual Payments:** Manual Bank Transfer and Cryptocurrency state machine with comprehensive audit trails for submitted references, transaction hashes, and admin reviews.
4. **Dynamic Shipping Engine:** Shipping zones, delivery methods, and rules (such as Free UK Delivery on orders >= £300) are persisted in the database, not hardcoded into frontend components.

For detailed documentation, inspect:
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- [docs/DATABASE.md](docs/DATABASE.md)
- [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)
- [docs/SECURITY.md](docs/SECURITY.md)
