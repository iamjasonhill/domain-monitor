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
APP_URL_VALUE="${APP_URL:-}"
QUEUE_CONNECTION_VALUE="${QUEUE_CONNECTION:-}"
HORIZON_ALLOWED_EMAILS_VALUE="${HORIZON_ALLOWED_EMAILS:-}"

if [ -z "$APP_ENV_VALUE" ]; then
    APP_ENV_VALUE="$(get_env_value_from_file "APP_ENV" ".env")"
fi

if [ -z "$APP_URL_VALUE" ]; then
    APP_URL_VALUE="$(get_env_value_from_file "APP_URL" ".env")"
fi

if [ -z "$QUEUE_CONNECTION_VALUE" ]; then
    QUEUE_CONNECTION_VALUE="$(get_env_value_from_file "QUEUE_CONNECTION" ".env")"
fi

if [ -z "$HORIZON_ALLOWED_EMAILS_VALUE" ]; then
    HORIZON_ALLOWED_EMAILS_VALUE="$(get_env_value_from_file "HORIZON_ALLOWED_EMAILS" ".env")"
fi

if [ "$APP_ENV_VALUE" = "production" ]; then
    if [ -z "$HORIZON_ALLOWED_EMAILS_VALUE" ]; then
        echo "Error: HORIZON_ALLOWED_EMAILS must be set when APP_ENV=production."
        exit 1
    fi

    if [ "$QUEUE_CONNECTION_VALUE" != "redis" ]; then
        echo "Error: QUEUE_CONNECTION must be set to redis when APP_ENV=production."
        exit 1
    fi

    case "$APP_URL_VALUE" in
        https://*)
            ;;
        *)
            echo "Error: APP_URL must use https:// when APP_ENV=production."
            exit 1
            ;;
    esac
fi

echo "Production environment guard check passed."
