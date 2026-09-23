#!/usr/bin/env bash
set -euo pipefail

if [[ "$(id -u)" -eq 0 ]]; then
  echo "Do not run deploy.sh as root." >&2
  exit 1
fi

APP_BASE="${APP_BASE:-/var/www/supremesteroid}"
RELEASES="${APP_BASE}/releases"
SHARED="${APP_BASE}/shared"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
PHP_BIN="${PHP_BIN:-php}"
TIMESTAMP="$(date +%Y%m%d%H%M%S)"
RELEASE="${RELEASES}/${TIMESTAMP}"
REPO="${DEPLOY_REPO:-}"
BRANCH="${DEPLOY_BRANCH:-main}"

if [[ -z "${REPO}" ]]; then
  echo "Set DEPLOY_REPO to the git remote URL." >&2
  exit 1
fi

if [[ ! -f "${SHARED}/.env" ]]; then
  echo "Missing ${SHARED}/.env. Copy .env.production.example and fill it before deploying." >&2
  exit 1
fi

mkdir -p "${RELEASES}" "${SHARED}/storage"

git clone --depth 1 --branch "${BRANCH}" "${REPO}" "${RELEASE}"
ln -sfn "${SHARED}/.env" "${RELEASE}/.env"

if [[ ! -d "${SHARED}/storage/framework" ]]; then
  mkdir -p "${SHARED}/storage/framework/cache/data" \
    "${SHARED}/storage/framework/sessions" \
    "${SHARED}/storage/framework/views" \
    "${SHARED}/storage/logs" \
    "${SHARED}/storage/app/public"
fi

rm -rf "${RELEASE}/storage"
ln -sfn "${SHARED}/storage" "${RELEASE}/storage"

cd "${RELEASE}"

composer install --no-dev --optimize-autoloader --no-interaction
corepack enable
corepack prepare pnpm@12.4.2 --activate
pnpm install --frozen-lockfile
pnpm run build

if grep -q '^DATABASE_URL_UNPOOLED=.\+' "${SHARED}/.env"; then
  "${PHP_BIN}" artisan migrate --database=pgsql_direct --force --no-interaction
else
  "${PHP_BIN}" artisan migrate --force --no-interaction
fi

"${PHP_BIN}" artisan storage:link --no-interaction || true
"${PHP_BIN}" artisan optimize --no-interaction

ln -sfn "${RELEASE}" "${APP_BASE}/current"

if command -v systemctl >/dev/null 2>&1 && systemctl is-active --quiet supremesteroid; then
  systemctl reload supremesteroid || systemctl restart supremesteroid
fi

mapfile -t OLD_RELEASES < <(ls -1dt "${RELEASES}"/* 2>/dev/null || true)
if ((${#OLD_RELEASES[@]} > KEEP_RELEASES)); then
  for old in "${OLD_RELEASES[@]:KEEP_RELEASES}"; do
    if [[ "${old}" != "$(readlink -f "${APP_BASE}/current")" ]]; then
      rm -rf "${old}"
    fi
  done
fi

echo "Deployed ${RELEASE}"
