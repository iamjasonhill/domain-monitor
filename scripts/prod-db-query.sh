#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# shellcheck source=./_prod-db-common.sh
source "${SCRIPT_DIR}/_prod-db-common.sh"

if [ "$#" -eq 0 ] || [ "${1:-}" = "--help" ]; then
    cat <<'EOF'
Usage: scripts/prod-db-query.sh "<sql>"

Run a one-off SQL query against the production database via SSH with read-only defaults.

Safety:
  - This helper is intended for read queries.
  - Read-only is a default session setting, not a hard database permission boundary.
  - For truly enforced read-only access, use a dedicated read-only database role.

Required environment overrides:
  PROD_SSH_HOST

Optional environment overrides:
  PROD_SSH_USER
  PROD_APP_ROOT (or PROD_APP_ENV_FILE)
  PROD_APP_ENV_FILE

Examples:
  scripts/prod-db-query.sh "select now();"
  scripts/prod-db-query.sh "select count(*) from web_properties;"
EOF
    exit 0
fi

query="$1"

normalized_query="$(printf '%s' "$query" | tr '\n' ' ' | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
lowercase_query="$(printf '%s' "$normalized_query" | tr '[:upper:]' '[:lower:]')"
multi_statement_pattern=';[[:space:]]*[^[:space:];]'

if [ -z "$normalized_query" ]; then
    echo "Error: Query must not be empty." >&2
    exit 1
fi

if [[ "$normalized_query" =~ $multi_statement_pattern ]]; then
    echo "Error: Multiple SQL statements are not allowed." >&2
    exit 1
fi

if [[ "$lowercase_query" == explain\ * ]] && [[ "$lowercase_query" =~ (^|[[:space:][:punct:]])analyze([[:space:][:punct:]]|$) ]]; then
    echo "Error: EXPLAIN ANALYZE is not allowed because it executes the underlying statement." >&2
    exit 1
fi

case "$lowercase_query" in
    select\ *|show\ *|explain\ *|values\ *|table\ *)
        ;;
    *)
        echo "Error: Only clearly read-only SQL is allowed (SELECT, SHOW, EXPLAIN, VALUES, TABLE)." >&2
        exit 1
        ;;
esac

run_remote_prod_psql -X -v ON_ERROR_STOP=1 -P pager=off -c "$query"
