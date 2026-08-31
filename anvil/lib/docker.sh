#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/lib/docker.sh
#
# Wraps `docker compose` for the local Anvil stack. Every function is
# idempotent / re-runnable and performs no action on source — only definitions.
#
# Sourcing is safe: it sets `set -euo pipefail` (inherits to the caller shell,
# which is the desired behaviour) and sources the config if not already loaded.

set -euo pipefail

# Source config if the caller (anvilctl or another lib) has not done so.
if [[ -z "${ANVIL_ROOT:-}" ]]; then
  # shellcheck source=../config/anvil.conf
  source "$(dirname "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")")/config/anvil.conf"
fi

# Internal helper: run docker compose against the resolved compose file.
_anvil_docker_compose() {
  docker compose -f "$ANVIL_COMPOSE_FILE" "$@"
}

# Bring the local stack up (detached). Safe to re-run.
anvil_docker_up() {
  _anvil_docker_compose up -d "$@"
}

# Tear the local stack down. Safe to re-run.
anvil_docker_down() {
  _anvil_docker_compose down "$@"
}

# List container status for the stack.
anvil_docker_ps() {
  _anvil_docker_compose ps "$@"
}

# Tail/fetch logs for the stack (pass extra args through, e.g. "-f nginx").
anvil_docker_logs() {
  _anvil_docker_compose logs "$@"
}
