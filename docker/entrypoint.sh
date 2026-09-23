#!/bin/sh
set -eu

export PORT="${PORT:-8080}"

cd /app

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  echo "Running outstanding migrations."
  if [ -n "${DATABASE_URL_UNPOOLED:-}" ]; then
    php artisan migrate --database=pgsql_direct --force --no-interaction
  else
    php artisan migrate --force --no-interaction
  fi
fi

php artisan config:cache || true
php artisan view:cache || true

exec frankenphp run --config /etc/caddy/Caddyfile
