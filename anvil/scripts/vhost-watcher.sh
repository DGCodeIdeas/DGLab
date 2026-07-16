#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/scripts/vhost-watcher.sh
#
# Auto-vhost watcher for Anvil. Watches the Anvil www directory ($WWW_DIR) for
# project directory create/delete events using inotifywait and keeps nginx
# vhosts + SSL certificates in sync by reusing the Phase 1 lib functions:
#
#   * anvil_project_register  -> registers + issues SSL + generates vhost
#   * anvil_vhost_remove      -> purges vhost config + certs
#   * anvil_vhost_reload      -> graceful nginx reload
#
# Modes:
#   (default) loop   : watch forever, reacting to create/delete events.
#   --once           : process current project directories once, then exit.
#
# Clean shutdown on SIGTERM/SIGINT. Requires inotify-tools (`inotifywait`).

set -euo pipefail

# Resolve Anvil root from this script's location: anvil/scripts/vhost-watcher.sh
# -> anvil/. Export it so the sourced lib scripts do not re-derive it.
ANVIL_ROOT="$(dirname "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")")"
export ANVIL_ROOT

# shellcheck source=../config/anvil.conf
source "${ANVIL_ROOT}/config/anvil.conf"
# shellcheck source=../lib/docker.sh
source "${ANVIL_ROOT}/lib/docker.sh"
# shellcheck source=../lib/vhost.sh
source "${ANVIL_ROOT}/lib/vhost.sh"
# shellcheck source=../lib/ssl.sh
source "${ANVIL_ROOT}/lib/ssl.sh"
# shellcheck source=../lib/project.sh
source "${ANVIL_ROOT}/lib/project.sh"
# shellcheck source=../lib/db.sh
source "${ANVIL_ROOT}/lib/db.sh"
# shellcheck source=../lib/ec2.sh
source "${ANVIL_ROOT}/lib/ec2.sh"

# ---------------------------------------------------------------------------
# Preconditions
# ---------------------------------------------------------------------------
if ! command -v inotifywait >/dev/null 2>&1; then
  echo "ERROR: 'inotifywait' (from inotify-tools) is required but not found on PATH." >&2
  echo "       Install it with:  apt-get install -y inotify-tools" >&2
  exit 1
fi

mkdir -p "$WWW_DIR"

# ---------------------------------------------------------------------------
# Handlers
# ---------------------------------------------------------------------------

# React to a newly created project directory.
handle_create() {
  local path="$1"
  # Only react to directories (inotify may report file creates too).
  [[ -d "$path" ]] || return 0
  local project
  project="$(basename "$path")"
  echo "[watch] new project directory: ${project}"
  # anvil_project_register is idempotent and internally triggers
  # anvil_ssl_project + anvil_vhost_generate.
  anvil_project_register "$project" || echo "WARN: failed to register ${project}" >&2
  anvil_vhost_reload || true
}

# React to a removed project directory.
handle_delete() {
  local path="$1"
  local project
  project="$(basename "$path")"
  echo "[watch] project directory removed: ${project}"
  anvil_vhost_remove "$project" || echo "WARN: failed to remove vhost for ${project}" >&2
  anvil_vhost_reload || true
}

# Process all existing project directories once (used by --once and at the
# start of loop mode so pre-existing folders are synced).
sync_existing() {
  local dir
  shopt -s nullglob
  for dir in "$WWW_DIR"/*/; do
    handle_create "${dir%/}"
  done
  shopt -u nullglob
}

# ---------------------------------------------------------------------------
# Usage / argument parsing
# ---------------------------------------------------------------------------
usage() {
  cat <<'EOF'
Usage:
  vhost-watcher.sh            Watch WWW_DIR for create/delete events (loop mode).
  vhost-watcher.sh --once     Sync current project directories once, then exit.
  vhost-watcher.sh -h|--help  Show this help.
EOF
}

ONCE=0
case "${1:-}" in
  --once) ONCE=1 ;;
  -h|--help) usage; exit 0 ;;
  "") ;;
  *) echo "ERROR: unknown argument '${1}'" >&2; usage; exit 1 ;;
esac

# ---------------------------------------------------------------------------
# Clean shutdown
# ---------------------------------------------------------------------------
# shellcheck disable=SC2317  # called only via trap; body is not "reachable" statically
cleanup() {
  echo "[watch] received shutdown signal; exiting."
  exit 0
}
trap cleanup SIGTERM SIGINT

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
if [[ "$ONCE" -eq 1 ]]; then
  echo "[watch] --once mode: syncing existing projects in ${WWW_DIR}"
  sync_existing
  echo "[watch] --once mode: done."
  exit 0
fi

# Loop mode: sync what already exists, then watch for changes.
echo "[watch] loop mode: syncing existing projects in ${WWW_DIR}"
sync_existing

echo "[watch] loop mode: watching ${WWW_DIR} for create/delete (Ctrl-C to stop)"
inotifywait -m -e create -e delete --format '%e %w%f' "$WWW_DIR" | while read -r event path; do
  case "$event" in
    CREATE*) handle_create "$path" ;;
    DELETE*) handle_delete "$path" ;;
  esac
done

# If the pipeline above ends (e.g. inotifywait terminated), exit cleanly.
exit 0
