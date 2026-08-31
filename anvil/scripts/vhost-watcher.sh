#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/scripts/vhost-watcher.sh
#
# Tenant watcher for Anvil v3. Watches $WWW_DIR for tenant directory
# create/delete events using inotifywait and keeps the tenant registry in sync
# (lib/registry.sh). Replaces the v1 vhost-render watcher entirely: in v3,
# there are no nginx vhost files to render — Caddy's on-demand TLS asks the
# registry live via /_anvil/tls-allowed, and tenant presence IS registration.
#
# v3 handlers:
#   * anvil_registry_register    -> mkdir $WWW_DIR/<slug> (idempotent)
#   * anvil_registry_unregister  -> rmdir $WWW_DIR/<slug> (idempotent)
#   * anvil_registry_scan        -> log current tenant list
#
# Modes:
#   (default) loop   : watch forever, reacting to create/delete events.
#   --once           : process current tenant directories once, then exit.
#
# Clean shutdown on SIGTERM/SIGINT. Requires inotify-tools (`inotifywait`).
#
# BACKWARD COMPAT: the v1 handlers (anvil_project_register, anvil_vhost_*
# calls) are NOT invoked in v3. The v1 lib/vhost.sh + lib/project.sh files
# remain in the tree for the legacy `anvilctl stack legacy-nginx` escape hatch
# (§8.5 of the v3 doc) and are removed at v3.1.

set -euo pipefail

# Resolve Anvil root from this script's location: anvil/scripts/vhost-watcher.sh
# -> anvil/. Export it so the sourced lib scripts do not re-derive it.
ANVIL_ROOT="$(dirname "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")")"
export ANVIL_ROOT

# shellcheck source=../config/anvil.conf
source "${ANVIL_ROOT}/config/anvil.conf"
# shellcheck source=../lib/core.sh
source "${ANVIL_ROOT}/lib/core.sh"
# shellcheck source=../lib/registry.sh
source "${ANVIL_ROOT}/lib/registry.sh"

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

# React to a newly created tenant directory.
handle_create() {
  local path="$1"
  # Only react to directories (inotify may report file creates too).
  [[ -d "$path" ]] || return 0
  local slug
  slug="$(basename "$path")"
  echo "[watch] new tenant directory: ${slug}"
  # In v3, presence IS registration — no vhost render step. We just log it
  # so Caddy's on-demand TLS ask endpoint will return 200 for this slug's
  # hostnames from this point on. The scan call below surfaces the count.
  anvil_registry_scan
}

# React to a removed tenant directory.
handle_delete() {
  local path="$1"
  local slug
  slug="$(basename "$path")"
  echo "[watch] tenant directory removed: ${slug}"
  # Presence-gone means the ask endpoint will now return non-200 for this
  # slug's hostnames — Caddy will refuse to issue new certs for them. Existing
  # certs remain in Caddy's cert store until they expire; that is by design
  # (immediate revocation would require an OCSP stapling step not in v3 scope).
  anvil_registry_scan
}

# Process all existing tenant directories once (used by --once and at the
# start of loop mode so pre-existing folders are surfaced in the log).
sync_existing() {
  anvil_registry_scan
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
