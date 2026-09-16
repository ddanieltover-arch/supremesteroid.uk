#!/bin/sh
set -eu

PORT="${PORT:-3000}"
export PORT
export SERVER_NAME=":${PORT}"

cd /app

php artisan package:discover --ansi || true
php artisan config:cache || true
php artisan view:cache || true

exec frankenphp run --config /etc/caddy/Caddyfile
