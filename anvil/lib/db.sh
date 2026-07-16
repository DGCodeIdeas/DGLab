#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/lib/db.sh
#
# Wraps MySQL execution inside the local docker stack. Idempotent and
# injection-safe (database names are validated against [A-Za-z0-9_]).
# Sourcing is safe: only definitions + a guarded config source.

set -euo pipefail

if [[ -z "${ANVIL_ROOT:-}" ]]; then
  # shellcheck source=../config/anvil.conf
  source "$(dirname "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")")/config/anvil.conf"
fi

# anvil_db_create DB_NAME
#   Creates the database if it does not already exist.
anvil_db_create() {
  local db_name="${1:?Usage: anvil_db_create DB_NAME}"

  # Validate to prevent SQL injection via the database name.
  if [[ ! "$db_name" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "ERROR: invalid database name '${db_name}' (allowed: [A-Za-z0-9_])" >&2
    return 1
  fi

  docker compose -f "$ANVIL_COMPOSE_FILE" exec -T mysql \
    mysql -e "CREATE DATABASE IF NOT EXISTS \`${db_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  echo "Database '${db_name}' ensured."
}
