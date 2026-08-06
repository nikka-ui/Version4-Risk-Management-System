#!/bin/sh
set -eu

# Map Docker Compose secrets (*_FILE) into Laravel env vars.
if [ -n "${APP_KEY_FILE:-}" ] && [ -f "${APP_KEY_FILE}" ]; then
  APP_KEY="$(tr -d '\r\n' < "${APP_KEY_FILE}")"
  export APP_KEY
fi

if [ -n "${DB_PASSWORD_FILE:-}" ] && [ -f "${DB_PASSWORD_FILE}" ]; then
  DB_PASSWORD="$(tr -d '\r\n' < "${DB_PASSWORD_FILE}")"
  export DB_PASSWORD
fi

# Placeholder / empty APP_KEY cannot boot Laravel — generate an ephemeral key
# for local scaffold. Replace docker/secrets/app_key.txt with a real
# `php artisan key:generate --show` value before shared/staging/prod use.
case "${APP_KEY:-}" in
  ""|*CHANGE_ME*)
    APP_KEY="base64:$(dd if=/dev/urandom bs=32 count=1 2>/dev/null | base64 | tr -d '\r\n')"
    export APP_KEY
    echo "rms-api: using ephemeral APP_KEY (replace docker/secrets/app_key.txt for durable installs)" >&2
    ;;
esac

exec "$@"
