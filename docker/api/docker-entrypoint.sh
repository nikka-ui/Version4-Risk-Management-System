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

# Placeholder / empty APP_KEY cannot boot Laravel. Prefer a durable secret;
# rotating the key invalidates encrypted session cookies and causes 419s.
case "${APP_KEY:-}" in
  ""|*CHANGE_ME*)
    GENERATED_KEY_FILE="${APP_GENERATED_KEY_FILE:-/var/www/html/storage/framework/app_key_generated}"
    if [ -f "${GENERATED_KEY_FILE}" ]; then
      APP_KEY="$(tr -d '\r\n' < "${GENERATED_KEY_FILE}")"
    fi
    case "${APP_KEY:-}" in
      ""|*CHANGE_ME*)
        APP_KEY="base64:$(dd if=/dev/urandom bs=32 count=1 2>/dev/null | base64 | tr -d '\r\n')"
        if mkdir -p "$(dirname "${GENERATED_KEY_FILE}")" 2>/dev/null; then
          printf '%s\n' "${APP_KEY}" > "${GENERATED_KEY_FILE}" 2>/dev/null || true
        fi
        echo "rms-api: using generated APP_KEY (replace docker/secrets/app_key.txt for durable installs)" >&2
        ;;
    esac
    export APP_KEY
    ;;
esac

exec "$@"
