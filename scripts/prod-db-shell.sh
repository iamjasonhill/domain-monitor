#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# shellcheck source=./_prod-db-common.sh
source "${SCRIPT_DIR}/_prod-db-common.sh"

if [ "${1:-}" = "--help" ]; then
    cat <<'EOF'
Usage: scripts/prod-db-shell.sh

Interactive production DB shell access is intentionally disabled.

Safety:
  - The current production DB credentials are not a true read-only database role.
  - Use scripts/prod-db-query.sh for one-off read queries.
  - Re-enable shell access only after provisioning a dedicated read-only role.

Required environment overrides:
  PROD_SSH_HOST

Optional environment overrides:
  PROD_SSH_USER
  PROD_APP_ROOT (or PROD_APP_ENV_FILE)
  PROD_APP_ENV_FILE

Examples:
  scripts/prod-db-shell.sh
EOF
    exit 0
fi

cat >&2 <<'EOF'
Interactive production DB shell access is disabled because the current
application DB credentials are not a true read-only role.

Use:
  scripts/prod-db-query.sh "select now();"

If you need a real interactive shell, first provision a dedicated read-only
database user on production and update this helper to use that role.
EOF
exit 1
