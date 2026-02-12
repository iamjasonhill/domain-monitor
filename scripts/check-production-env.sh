#!/bin/bash

set -euo pipefail

get_env_value_from_file() {
    local key="$1"
    local file_path="$2"

    if [ ! -f "$file_path" ]; then
        return 0
    fi

    awk -F '=' -v key="$key" '
        $0 !~ /^[[:space:]]*#/ && $1 == key {
            value = substr($0, index($0, "=") + 1)
            gsub(/^[[:space:]]+|[[:space:]]+$/, "", value)
            gsub(/^"|"$/, "", value)
            print value
            exit
        }
    ' "$file_path"
}

APP_ENV_VALUE="${APP_ENV:-}"
HORIZON_ALLOWED_EMAILS_VALUE="${HORIZON_ALLOWED_EMAILS:-}"

if [ -z "$APP_ENV_VALUE" ]; then
    APP_ENV_VALUE="$(get_env_value_from_file "APP_ENV" ".env")"
fi

if [ -z "$HORIZON_ALLOWED_EMAILS_VALUE" ]; then
    HORIZON_ALLOWED_EMAILS_VALUE="$(get_env_value_from_file "HORIZON_ALLOWED_EMAILS" ".env")"
fi

if [ "$APP_ENV_VALUE" = "production" ] && [ -z "$HORIZON_ALLOWED_EMAILS_VALUE" ]; then
    echo "Error: HORIZON_ALLOWED_EMAILS must be set when APP_ENV=production."
    exit 1
fi

echo "Production environment guard check passed."
