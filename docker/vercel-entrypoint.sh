#!/bin/sh
set -eu

export PORT="${PORT:-80}"

cd /app

exec frankenphp run --config /etc/caddy/Caddyfile
