#!/bin/bash

set -euo pipefail

PROD_SSH_USER="${PROD_SSH_USER:-forge}"
PROD_SSH_HOST="${PROD_SSH_HOST:-}"
PROD_APP_ROOT="${PROD_APP_ROOT:-}"
PROD_APP_ENV_FILE="${PROD_APP_ENV_FILE:-}"

run_remote_prod_psql() {
    local -a psql_args=("$@")
    local env_file_b64
    local psql_args_b64

    if [ -z "$PROD_SSH_HOST" ]; then
        echo "Error: Set PROD_SSH_HOST before using the production DB helpers." >&2
        exit 1
    fi

    if [ -z "$PROD_APP_ENV_FILE" ]; then
        if [ -z "$PROD_APP_ROOT" ]; then
            echo "Error: Set PROD_APP_ROOT or PROD_APP_ENV_FILE before using the production DB helpers." >&2
            exit 1
        fi

        PROD_APP_ENV_FILE="$PROD_APP_ROOT/.env"
    fi

    env_file_b64="$(printf '%s' "${PROD_APP_ENV_FILE}" | base64 | tr -d '\n')"

    if [ "${#psql_args[@]}" -eq 0 ]; then
        psql_args_b64=""
    else
        psql_args_b64="$(printf '%s\0' "${psql_args[@]}" | base64 | tr -d '\n')"
    fi

    ssh -o BatchMode=yes "${PROD_SSH_USER}@${PROD_SSH_HOST}" \
        "PROD_APP_ENV_FILE_B64='${env_file_b64}' PSQL_ARGS_B64='${psql_args_b64}' bash -s" <<'BASH'
set -euo pipefail

env_file="$(printf '%s' "${PROD_APP_ENV_FILE_B64}" | base64 --decode)"

psql_args=()

if [ -n "${PSQL_ARGS_B64}" ]; then
    while IFS= read -r -d '' arg; do
        psql_args+=("$arg")
    done < <(printf '%s' "${PSQL_ARGS_B64}" | base64 --decode)
fi

get_env_value_from_file() {
    local key="$1"
    local file_path="$2"
    local line
    local value

    if [ ! -f "$file_path" ]; then
        return 0
    fi

    line="$(grep -m1 -E "^[[:space:]]*${key}=" "$file_path" || true)"

    if [ -z "$line" ]; then
        return 0
    fi

    value="${line#*=}"
    value="$(printf '%s' "$value" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"

    if [[ "$value" == \"*\" && "$value" == *\" ]]; then
        value="${value:1:${#value}-2}"
        value="${value//\\\"/\"}"
        value="${value//\\\\/\\}"
    elif [[ "$value" == \'*\' && "$value" == *\' ]]; then
        value="${value:1:${#value}-2}"
    fi

    printf '%s\n' "$value"
}

if [ ! -f "$env_file" ]; then
    echo "Error: Production env file not found: $env_file" >&2
    exit 1
fi

db_host="$(get_env_value_from_file "DB_HOST" "$env_file")"
db_port="$(get_env_value_from_file "DB_PORT" "$env_file")"
db_name="$(get_env_value_from_file "DB_DATABASE" "$env_file")"
db_user="$(get_env_value_from_file "DB_USERNAME" "$env_file")"
db_password="$(get_env_value_from_file "DB_PASSWORD" "$env_file")"

if [ -z "$db_name" ] || [ -z "$db_user" ]; then
    echo "Error: Missing DB_DATABASE or DB_USERNAME in $env_file" >&2
    exit 1
fi

export PGPASSWORD="$db_password"
export PGOPTIONS="${PGOPTIONS:+$PGOPTIONS }-c default_transaction_read_only=on"

exec psql \
    -h "${db_host:-127.0.0.1}" \
    -p "${db_port:-5432}" \
    -U "$db_user" \
    -d "$db_name" \
    "${psql_args[@]}"
BASH
}
