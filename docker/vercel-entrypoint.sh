#!/bin/sh
set -eu

export PORT="${PORT:-80}"

cd /app

database_url="${DATABASE_URL:-}"
migrate_url="${DATABASE_URL_UNPOOLED:-$database_url}"

case "$migrate_url" in
  *xxxx*|*YOUR_NEON*|*YOUR_PASSWORD*|"")
    echo "Skipping migrations: database URL is not configured."
    ;;
  *)
    echo "Running database migrations."
    DATABASE_URL="$migrate_url" php artisan migrate --force --no-interaction || echo "Migrations failed; serving requests anyway."
    ;;
esac

exec frankenphp run --config /etc/caddy/Caddyfile
