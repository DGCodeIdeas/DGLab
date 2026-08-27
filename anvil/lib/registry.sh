#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/lib/registry.sh
#
# Tenant registry — the data source for Caddy's on_demand_tls ask endpoint
# (§7.3 of v3 doc) and the successor to Anvil v1's `lib/vhost.sh` render step.
#
# Contract:
#   * Backing store: $WWW_DIR/ — each subdirectory is a tenant root.
#     (v1 heritage: project roots under www/ were rendered to nginx vhosts;
#     v3 keeps the directory layout but treats presence as the registration
#     signal. The TUI/Web UI may add a JSON sidecar later without breaking
#     this contract.)
#   * ask endpoint behavior: 200 → certificate may be issued for the SNI;
#     any other status → deny. The endpoint is exposed at /_anvil/tls-allowed
#     by Tengine (§7.4) and proxied to the FrankenPHP app, which calls
#     anvil_registry_is_allowed() in-process via a tiny PHP shim.
#
# This bash lib is used by:
#   * anvilctl scan            — re-scans $WWW_DIR and logs registered tenants
#   * anvilctl projects        — lists tenants as TSV
#   * scripts/vhost-watcher.sh — inotify on $WWW_DIR, re-scans on create/delete
#   * the PHP shim that powers /_anvil/tls-allowed (via a `bash -c` call)

set -euo pipefail

if [[ -z "${ANVIL_ROOT:-}" ]]; then
  # shellcheck source=lib/core.sh
  source "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")/core.sh"
fi

# anvil_registry_is_allowed HOSTNAME  — exit 0 if a tenant exists for HOSTNAME.
# Matching rule: a subdirectory of $WWW_DIR whose name matches the first label
# of HOSTNAME (the tenant slug), OR a www/<hostname> directory exactly.
#
# Examples:
#   HOSTNAME=foo.dglab.example.com → look for $WWW_DIR/foo/  (first label)
#   HOSTNAME=api.dglab.example.com → look for $WWW_DIR/api/
#   HOSTNAME=dglab.example.com     → look for $WWW_DIR/_root/ (the primary site)
#
# The PHP shim calls this with `bash -c 'source lib/registry.sh; anvil_registry_is_allowed "$1"' -- HOSTNAME`.
anvil_registry_is_allowed() {
  local hostname="${1:?Usage: anvil_registry_is_allowed HOSTNAME}"
  local slug

  # Primary site FQDN — always allowed (it has a fixed vhost in the edge Caddyfile).
  if [[ "$hostname" == "$ANVIL_PRIMARY_FQDN" ]]; then
    return 0
  fi

  # First label of the hostname is the tenant slug.
  slug="${hostname%%.*}"
  [[ -z "$slug" || "$slug" == "$hostname" ]] && return 1

  # Allow only [a-z0-9-]+ slugs (defends against traversal: "..", "/", etc.).
  [[ "$slug" =~ ^[a-z0-9-]+$ ]] || return 1

  [[ -d "${WWW_DIR}/${slug}" ]]
}

# anvil_registry_list  — prints registered tenants as TSV: SLUG  ROOT  CREATED.
anvil_registry_list() {
  printf 'SLUG\tROOT\tCREATED\n'
  if [[ ! -d "$WWW_DIR" ]]; then
    return 0
  fi
  local slug root created
  for entry in "$WWW_DIR"/*/; do
    [[ -d "$entry" ]] || continue
    slug="$(basename "$entry")"
    root="$entry"
    created="$(stat -c '%y' "$entry" 2>/dev/null | awk '{print $1}')"
    printf '%s\t%s\t%s\n' "$slug" "$root" "$created"
  done
}

# anvil_registry_scan  — re-scans $WWW_DIR and logs registered tenants.
# Idempotent; the watcher calls this on inotify events.
anvil_registry_scan() {
  anvil_info "registry scan: $WWW_DIR"
  local count=0
  if [[ -d "$WWW_DIR" ]]; then
    count="$(find "$WWW_DIR" -maxdepth 1 -mindepth 1 -type d | wc -l)"
  fi
  anvil_info "registry scan: ${count} tenant(s) registered"
  anvil_registry_list
}

# anvil_registry_register SLUG  — creates $WWW_DIR/<slug>/ with a placeholder.
# Idempotent. Refuses slugs that don't match [a-z0-9-]+.
anvil_registry_register() {
  local slug="${1:?Usage: anvil_registry_register SLUG}"
  [[ "$slug" =~ ^[a-z0-9-]+$ ]] || anvil_die 2 "invalid slug: '$slug' (allowed: [a-z0-9-]+)"
  mkdir -p "${WWW_DIR}/${slug}"
  anvil_info "registered tenant: $slug → ${WWW_DIR}/${slug}"
}

# anvil_registry_unregister SLUG  — removes $WWW_DIR/<slug>/ (with confirmation).
anvil_registry_unregister() {
  local slug="${1:?Usage: anvil_registry_unregister SLUG}"
  [[ "$slug" =~ ^[a-z0-9-]+$ ]] || anvil_die 2 "invalid slug: '$slug'"
  local target="${WWW_DIR}/${slug}"
  [[ -d "$target" ]] || { anvil_warn "tenant not registered: $slug"; return 0; }
  rm -rf -- "$target"
  anvil_info "unregistered tenant: $slug"
}
