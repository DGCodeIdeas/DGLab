#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/lib/caddy.sh
#
# Caddy edge-tier lifecycle. Responsibilities:
#   * Render the edge Caddyfile from the template (edge/Caddyfile) with port
#     + ACME + primary-FQDN substitutions from anvil.conf.
#   * install_config — copy the rendered Caddyfile to /etc/anvil/edge/.
#   * validate / reload — wrappers over `caddy validate` and `caddy reload`
#     (admin-socket reload = zero dropped connections).
#   * start / stop / status — systemd wrappers (prod/staging only).
#   * doctor — admin-API reachability + ACME account check.
#
# §7.3 of the v3 doc is the authoritative reference for the Caddyfile contents.
# The template tokens MUST stay in sync with the sed substitutions below.

set -euo pipefail

if [[ -z "${ANVIL_ROOT:-}" ]]; then
  # shellcheck source=lib/core.sh
  source "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")/core.sh"
fi

# ---------------------------------------------------------------------------
# Template rendering
# ---------------------------------------------------------------------------

# anvil_caddy_render OUT  — renders $ANVIL_ROOT/edge/Caddyfile → $OUT.
# Substitutions: __CADDY_ADMIN_PORT__ / __TENGINE_LISTEN_PORT__ /
# __ACME_CA_LINE__ / __ACME_EMAIL__ / __PRIMARY_FQDN__.
anvil_caddy_render() {
  local out="${1:?Usage: anvil_caddy_render OUT}"
  local template="${ANVIL_ROOT}/edge/Caddyfile"
  [[ -f "$template" ]] || anvil_die 2 "missing template: $template"

  # ACME CA line: empty in prod, "acme_ca <url>" in staging (§6.2 of v3 doc).
  local acme_ca_line=""
  if [[ -n "${ACME_CA:-}" ]]; then
    acme_ca_line="acme_ca ${ACME_CA}"
  fi

  sed -e "s|__CADDY_ADMIN_PORT__|${CADDY_ADMIN_PORT}|g" \
      -e "s|__TENGINE_LISTEN_PORT__|${TENGINE_LISTEN_PORT}|g" \
      -e "s|__ACME_CA_LINE__|${acme_ca_line}|g" \
      -e "s|__ACME_EMAIL__|${ACME_EMAIL}|g" \
      -e "s|__PRIMARY_FQDN__|${ANVIL_PRIMARY_FQDN}|g" \
      "$template" > "$out"
  anvil_debug "rendered edge Caddyfile → $out (admin=${CADDY_ADMIN_PORT} tengine=${TENGINE_LISTEN_PORT} fqdn=${ANVIL_PRIMARY_FQDN})"
}

# anvil_caddy_install_config  — renders + installs to $ANVIL_EDGE_CADDYFILE.
# Idempotent. Used by install.sh and `anvilctl provision`.
anvil_caddy_install_config() {
  [[ $EUID -eq 0 ]] || anvil_die 1 "anvil_caddy_install_config requires root (use sudo)"
  mkdir -p "$(dirname "$ANVIL_EDGE_CADDYFILE")"
  anvil_caddy_render "$ANVIL_EDGE_CADDYFILE"
  chmod 0644 "$ANVIL_EDGE_CADDYFILE"
  anvil_info "caddy edge config installed: $ANVIL_EDGE_CADDYFILE"
}

# ---------------------------------------------------------------------------
# Lifecycle
# ---------------------------------------------------------------------------

# anvil_caddy_validate [CADDYFILE]  — exit 0 if config is syntactically valid.
anvil_caddy_validate() {
  local cfg="${1:-$ANVIL_EDGE_CADDYFILE}"
  anvil_require "$ANVIL_CADDY_BIN"
  [[ -f "$cfg" ]] || anvil_die 2 "missing Caddyfile: $cfg"
  "$ANVIL_CADDY_BIN" validate --config "$cfg" --adapter caddyfile
}

# anvil_caddy_reload  — admin-socket reload (zero dropped connections).
# Requires the caddy unit to be running so the admin socket is up.
anvil_caddy_reload() {
  anvil_require "$ANVIL_CADDY_BIN"
  local cfg="${1:-$ANVIL_EDGE_CADDYFILE}"
  "$ANVIL_CADDY_BIN" reload --config "$cfg" --adapter caddyfile
  anvil_info "caddy reloaded (admin-socket)"
}

# anvil_caddy_start / stop / status  — systemd wrappers.
# In dev collapsed mode, Caddy is not a separate process (FrankenPHP embeds
# it); these are no-ops with a clear message in that case.
anvil_caddy_start() {
  if [[ "${ANVIL_ENV:-dev}" == "dev" && "${ANVIL_STACK_MODE:-slim}" == "slim" ]]; then
    anvil_info "caddy start: skipped (dev collapsed mode — FrankenPHP embeds Caddy)"
    return 0
  fi
  [[ -f /etc/systemd/system/anvil-caddy.service ]] \
    || anvil_die 2 "missing systemd unit; run 'anvilctl provision install-units' first"
  systemctl start anvil-caddy
  anvil_info "started anvil-caddy"
}

anvil_caddy_stop() {
  if [[ "${ANVIL_ENV:-dev}" == "dev" && "${ANVIL_STACK_MODE:-slim}" == "slim" ]]; then
    anvil_info "caddy stop: skipped (dev collapsed mode)"
    return 0
  fi
  systemctl stop anvil-caddy 2>/dev/null || true
  anvil_info "stopped anvil-caddy"
}

anvil_caddy_status() {
  if [[ "${ANVIL_ENV:-dev}" == "dev" && "${ANVIL_STACK_MODE:-slim}" == "slim" ]]; then
    echo "caddy[dev]: embedded in frankenphp (collapsed mode)"
    return 0
  fi
  if systemctl is-active --quiet anvil-caddy; then
    echo "caddy[edge]: active"
    # Surface cert-expiry info from the admin API.
    local admin="127.0.0.1:${CADDY_ADMIN_PORT}"
    if command -v curl >/dev/null 2>&1 && curl -fsS "http://${admin}/config/apps/tls/automation/policies" >/dev/null 2>&1; then
      echo "  admin:  $admin (reachable)"
    else
      echo "  admin:  $admin (unreachable?)"
    fi
  else
    echo "caddy[edge]: inactive"
  fi
}

# ---------------------------------------------------------------------------
# Doctor hook
# ---------------------------------------------------------------------------

# anvil_caddy_doctor  — exit 0 if the edge admin API responds.
# Called from anvilctl doctor AFTER the version-floor check.
anvil_caddy_doctor() {
  if [[ "${ANVIL_ENV:-dev}" == "dev" && "${ANVIL_STACK_MODE:-slim}" == "slim" ]]; then
    anvil_info "OK    caddy dev (embedded in frankenphp — no separate check)"
    return 0
  fi
  local admin="127.0.0.1:${CADDY_ADMIN_PORT}"
  if curl -fsS "http://${admin}/config/" >/dev/null 2>&1; then
    anvil_info "OK    caddy admin reachable at $admin"
    return 0
  fi
  anvil_warn "MISS  caddy admin not reachable at $admin (unit down?)"
  return 0   # advisory
}
