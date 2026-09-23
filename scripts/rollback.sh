#!/usr/bin/env bash
set -euo pipefail

if [[ "$(id -u)" -eq 0 ]]; then
  echo "Do not run rollback.sh as root." >&2
  exit 1
fi

APP_BASE="${APP_BASE:-/var/www/supremesteroid}"
CURRENT="$(readlink -f "${APP_BASE}/current")"
TARGET="${1:-}"

if [[ -z "${TARGET}" ]]; then
  TARGET="$(ls -1dt "${APP_BASE}/releases"/* | sed -n '2p')"
fi

if [[ -z "${TARGET}" || ! -d "${TARGET}" ]]; then
  echo "No previous release found. Pass a release directory explicitly." >&2
  exit 1
fi

if [[ ! -f "${TARGET}/artisan" ]]; then
  echo "Release is not an application directory: ${TARGET}" >&2
  exit 1
fi

ln -sfn "${TARGET}" "${APP_BASE}/current"

if command -v systemctl >/dev/null 2>&1 && systemctl is-active --quiet supremesteroid; then
  systemctl reload supremesteroid || systemctl restart supremesteroid
fi

echo "Rolled current back from ${CURRENT} to ${TARGET}"
echo "Database migrations were not reversed. Only deploy schema changes that the previous release can still read."
