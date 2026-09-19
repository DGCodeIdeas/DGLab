#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/lib/tengine.sh
#
# Tengine internal-LB-tier lifecycle. Responsibilities:
#   * Render the LB config from the template (lb/tengine.conf) with port +
#     path + current-symlink substitutions from anvil.conf.
#   * install_config — copy the rendered config to /etc/anvil/lb/.
#   * validate / reload — wrappers over `nginx -t` and `nginx -s reload`.
#   * start / stop / status — systemd wrappers (prod/staging only).
#   * dyups_* — wrappers over the dyups HTTP API (the blue/green switch).
#   * check_status — pretty-print upstream health from /_anvil/upstream.
#   * doctor — admin-API reachability + version check (delegated to core).
#
# §7.4 of the v3 doc is the authoritative reference for the config contents.

set -euo pipefail

if [[ -z "${ANVIL_ROOT:-}" ]]; then
  # shellcheck source=lib/core.sh
  source "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")/core.sh"
fi

# ---------------------------------------------------------------------------
# Template rendering
# ---------------------------------------------------------------------------

# anvil_tengine_render OUT  — renders $ANVIL_ROOT/lb/tengine.conf → $OUT.
anvil_tengine_render() {
  local out="${1:?Usage: anvil_tengine_render OUT}"
  local template="${ANVIL_ROOT}/lb/tengine.conf"
  [[ -f "$template" ]] || anvil_die 2 "missing template: $template"

  sed -e "s|__TENGINE_LISTEN_PORT__|${TENGINE_LISTEN_PORT}|g" \
      -e "s|__FRANKENPHP_BLUE_PORT__|${FRANKENPHP_BLUE_PORT}|g" \
      -e "s|__ANVIL_DYUPS_STATE__|${ANVIL_DYUPS_STATE}|g" \
      -e "s|__ANVIL_LOG_DIR__|${ANVIL_LOG_DIR}|g" \
      -e "s|__ANVIL_RUN_DIR__|${ANVIL_RUN_DIR}|g" \
      -e "s|__ANVIL_CURRENT_SYMLINK__|${ANVIL_CURRENT_SYMLINK}|g" \
      "$template" > "$out"
  anvil_debug "rendered tengine.conf → $out (listen=${TENGINE_LISTEN_PORT} upstream=${FRANKENPHP_BLUE_PORT})"
}

# anvil_tengine_install_config  — renders + installs to $ANVIL_LB_TENGINE_CONF.
# Also creates the runtime dirs (ANVIL_DYUPS_STATE, ANVIL_RUN_DIR, ANVIL_LOG_DIR).
anvil_tengine_install_config() {
  [[ $EUID -eq 0 ]] || anvil_die 1 "anvil_tengine_install_config requires root (use sudo)"
  mkdir -p "$(dirname "$ANVIL_LB_TENGINE_CONF")" \
           "$ANVIL_DYUPS_STATE" \
           "$ANVIL_RUN_DIR" \
           "$ANVIL_LOG_DIR"
  chown -R tengine:tengine "$ANVIL_DYUPS_STATE" "$ANVIL_RUN_DIR" "$ANVIL_LOG_DIR"
  anvil_tengine_render "$ANVIL_LB_TENGINE_CONF"
  chmod 0644 "$ANVIL_LB_TENGINE_CONF"
  anvil_info "tengine config installed: $ANVIL_LB_TENGINE_CONF"
}

# ---------------------------------------------------------------------------
# Lifecycle
# ---------------------------------------------------------------------------

# anvil_tengine_validate [CONF]  — exit 0 if config is syntactically valid.
anvil_tengine_validate() {
  local cfg="${1:-$ANVIL_LB_TENGINE_CONF}"
  [[ -x "$ANVIL_TENGINE_BIN" ]] || anvil_die 2 "missing tengine binary: $ANVIL_TENGINE_BIN"
  [[ -f "$cfg" ]] || anvil_die 2 "missing config: $cfg"
  "$ANVIL_TENGINE_BIN" -t -c "$cfg"
}

# anvil_tengine_reload  — graceful HUP reload (re-reads config, no dropped conns).
anvil_tengine_reload() {
  [[ -x "$ANVIL_TENGINE_BIN" ]] || anvil_die 2 "missing tengine binary"
  local cfg="${1:-$ANVIL_LB_TENGINE_CONF}"
  "$ANVIL_TENGINE_BIN" -s reload -c "$cfg"
  anvil_info "tengine reloaded (HUP)"
}

# anvil_tengine_start / stop / status — systemd wrappers. No-ops in dev collapsed.
anvil_tengine_start() {
  if [[ "${ANVIL_ENV:-dev}" == "dev" && "${ANVIL_STACK_MODE:-slim}" == "slim" ]]; then
    anvil_info "tengine start: skipped (dev collapsed mode)"
    return 0
  fi
  [[ -f /etc/systemd/system/anvil-tengine.service ]] \
    || anvil_die 2 "missing systemd unit; run 'anvilctl provision install-units' first"
  systemctl start anvil-tengine
  anvil_info "started anvil-tengine"
}

anvil_tengine_stop() {
  if [[ "${ANVIL_ENV:-dev}" == "dev" && "${ANVIL_STACK_MODE:-slim}" == "slim" ]]; then
    anvil_info "tengine stop: skipped (dev collapsed mode)"
    return 0
  fi
  systemctl stop anvil-tengine 2>/dev/null || true
  anvil_info "stopped anvil-tengine"
}

anvil_tengine_status() {
  if [[ "${ANVIL_ENV:-dev}" == "dev" && "${ANVIL_STACK_MODE:-slim}" == "slim" ]]; then
    echo "tengine[dev]: off (collapsed mode — use 'anvilctl stack full' to enable)"
    return 0
  fi
  if systemctl is-active --quiet anvil-tengine; then
    echo "tengine[lb]: active (127.0.0.1:${TENGINE_LISTEN_PORT})"
    anvil_tengine_check_status
  else
    echo "tengine[lb]: inactive"
  fi
}

# ---------------------------------------------------------------------------
# dyups — the blue/green switch (§7.7 of v3 doc)
# ---------------------------------------------------------------------------

# anvil_tengine_dyups_set_upstream UPSTREAM_NAME SERVER_LINE
#   POSTs SERVER_LINE to the dyups interface, mutating the live upstream.
#   Example: anvil_tengine_dyups_set_upstream frankenphp "server 127.0.0.1:8091 max_fails=3 fail_timeout=10s;"
#   Returns 0 on success, non-zero on failure (with the dyups response body).
anvil_tengine_dyups_set_upstream() {
  local upstream="${1:?Usage: anvil_tengine_dyups_set_upstream UPSTREAM SERVER_LINE}"
  local server_line="${2:?}"
  local url="http://127.0.0.1:${TENGINE_LISTEN_PORT}/_anvil/dyups/upstream/${upstream}"
  local response http_code
  response="$(curl -sS -o /dev/stderr -w '%{http_code}' -X POST \
                --data "$server_line" "$url" 2>&1)" || true
  http_code="$(awk 'END{print $NF}' <<< "$response")"
  # dyups returns 200 on success, 4xx on bad input, 500 on internal error.
  if [[ "$http_code" == "200" || "$http_code" == "201" ]]; then
    anvil_info "dyups: upstream '${upstream}' → ${server_line}"
    return 0
  fi
  anvil_error "dyups: FAILED to set upstream '${upstream}' (HTTP ${http_code})"
  anvil_error "dyups response: ${response}"
  return 1
}

# anvil_tengine_dyups_get_upstream UPSTREAM_NAME  — prints the current upstream line.
anvil_tengine_dyups_get_upstream() {
  local upstream="${1:?Usage: anvil_tengine_dyups_get_upstream UPSTREAM}"
  curl -fsS "http://127.0.0.1:${TENGINE_LISTEN_PORT}/_anvil/dyups/upstream/${upstream}"
}

# ---------------------------------------------------------------------------
# check_status — active health-check dashboard (§7.4 of v3 doc)
# ---------------------------------------------------------------------------

# anvil_tengine_check_status  — pretty-prints the upstream dashboard.
# Used by `anvilctl status` and `anvilctl verify health`.
anvil_tengine_check_status() {
  local url="http://127.0.0.1:${TENGINE_LISTEN_PORT}/_anvil/upstream"
  if ! response="$(curl -fsS "$url" 2>/dev/null)"; then
    echo "  upstream dashboard: unreachable ($url)"
    return 1
  fi
  # check_status returns HTML; extract the upstream state line.
  local state
  state="$(awk -F'[<>]' '/Upstream|status/ {print}' <<< "$response" | head -5)"
  if [[ -n "$state" ]]; then
    echo "  upstream dashboard:"
    sed 's/^/    /' <<< "$state"
  else
    echo "  upstream dashboard: reachable, but no upstreams registered"
  fi
}

# ---------------------------------------------------------------------------
# Doctor hook
# ---------------------------------------------------------------------------

# anvil_tengine_doctor  — exit 0 if the LB is reachable (or absent legitimately).
anvil_tengine_doctor() {
  if [[ "${ANVIL_ENV:-dev}" == "dev" && "${ANVIL_STACK_MODE:-slim}" == "slim" ]]; then
    anvil_info "OK    tengine dev (collapsed mode — no LB tier)"
    return 0
  fi
  local url="http://127.0.0.1:${TENGINE_LISTEN_PORT}/_anvil/ping"
  if curl -fsS "$url" >/dev/null 2>&1; then
    anvil_info "OK    tengine reachable at $url"
    return 0
  fi
  anvil_warn "MISS  tengine not reachable at $url (unit down?)"
  return 0   # advisory
}
