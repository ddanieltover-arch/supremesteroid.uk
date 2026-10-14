# Local QA cleanup

Stale PENDING QA orders and other fixture data must only be cleared through the isolated QA reset command.

## Safe command

```bash
php artisan qa:reset
```

Use this only when `APP_ENV` is `local`, `testing`, or `qa`, and only against the local SQLite QA database.

## Rules

- Never run `qa:reset` against production.
- Never run QA cleanup against Neon PostgreSQL.
- The command refuses production hosts and Neon connections via `QualityAssuranceGuard`.
- Do not use migrate:fresh or database resets to clear PENDING QA orders in shared environments.

## What it removes

QA fixture records created for local verification (orders, payments, catalogue demo rows where marked as QA fixtures). It does not touch production catalogue or Neon data.
