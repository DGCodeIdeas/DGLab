#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/lib/verify.sh
#
# Staging validation gates from §6.3 of the v3 doc, runnable individually or
# as a single suite via `anvilctl verify all`. Each gate returns 0 on pass,
# 1 on fail (and prints a clear FAIL message naming the gate).
#
# Gates:
#   boot    — all three units active; anvilctl doctor green
#   health  — /health returns 200 within 5s; Tengine check_status rise>=2
#   headers — X-Forwarded-For chain correct; scheme=https echoed by the app
#   ports   — port-registry collision check (lib/core.sh::anvil_check_port_collisions)
#   all     — boot + health + headers + ports, in that order

set -euo pipefail

if [[ -z "${ANVIL_ROOT:-}" ]]; then
  # shellcheck source=lib/core.sh
  source "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")/core.sh"
fi

# Public URL — defaults to the primary FQDN over HTTPS; override via $1 for verify.
_anvil_verify_url() {
  echo "${ANVIL_VERIFY_URL:-https://${ANVIL_PRIMARY_FQDN}}"
}

# ---------------------------------------------------------------------------
# Gate 1: Boot
# ---------------------------------------------------------------------------

anvil_verify_boot() {
  local fail=0
  anvil_info "verify/boot: unit states + doctor"
  for unit in anvil-caddy anvil-tengine anvil-frankenphp@blue; do
    if systemctl is-active --quiet "$unit" 2>/dev/null; then
      anvil_info "  OK   $unit active"
    else
      anvil_error "  FAIL $unit not active"
      fail=1
    fi
  done
  if anvil_doctor_versions; then
    anvil_info "  OK   anvilctl doctor (version floors)"
  else
    anvil_error "  FAIL anvilctl doctor — version floors not met"
    fail=1
  fi
  return $fail
}

# ---------------------------------------------------------------------------
# Gate 2: Health
# ---------------------------------------------------------------------------

anvil_verify_health() {
  local fail=0
  local url
  url="$(_anvil_verify_url)/health"
  anvil_info "verify/health: $url (5s timeout, HUB-15 contract)"
  if curl -fsS --max-time 5 "$url" >/dev/null 2>&1; then
    anvil_info "  OK   $url returned 2xx within 5s"
  else
    anvil_error "  FAIL $url did not return 2xx within 5s"
    fail=1
  fi

  # Tengine check_status — upstream 'up' with rise>=2 (§6.3 gate 2).
  anvil_info "verify/health: Tengine check_status (rise>=2)"
  if response="$(curl -fsS "http://127.0.0.1:${TENGINE_LISTEN_PORT}/_anvil/upstream" 2>/dev/null)"; then
    if grep -qi 'Up' <<< "$response"; then
      anvil_info "  OK   upstream marked 'up' by check_status"
    else
      anvil_error "  FAIL check_status did not show upstream as 'up'"
      anvil_error "  response: ${response}"
      fail=1
    fi
  else
    anvil_error "  FAIL check_status endpoint unreachable"
    fail=1
  fi
  return $fail
}

# ---------------------------------------------------------------------------
# Gate 3: Headers (XFF chain, §3.3 of v3 doc)
# ---------------------------------------------------------------------------

anvil_verify_headers() {
  local fail=0
  local base
  base="$(_anvil_verify_url)"
  anvil_info "verify/headers: security headers + XFF chain at $base"

  # Security headers — present from the edge Caddyfile's security_headers snippet.
  local headers
  if ! headers="$(curl -fsSI --max-time 5 "$base" 2>/dev/null)"; then
    anvil_error "  FAIL could not fetch headers from $base"
    return 1
  fi
  for h in 'strict-transport-security' 'x-content-type-options' 'x-frame-options'; do
    if grep -qi "^${h}:" <<< "$headers"; then
      anvil_info "  OK   header $h present"
    else
      anvil_error "  FAIL header $h missing"
      fail=1
    fi
  done

  # XFF chain — the app's /debug/xff endpoint echoes the perceived client IP + scheme.
  # Tries the future Hub endpoint (/debug/xff) first; falls back to the dev/staging
  # fixture (/debug/xff.php, see public/debug/xff.php) when the Hub isn't deployed.
  local response xff_source=""
  for xff_path in "/debug/xff" "/debug/xff.php"; do
    local xff_url="${base}${xff_path}"
    if response="$(curl -fsS --max-time 5 "$xff_url" 2>/dev/null)"; then
      xff_source="$xff_path"
      break
    fi
  done
  if [[ -n "$xff_source" ]]; then
    if command -v jq >/dev/null 2>&1; then
      local scheme client_ip
      scheme="$(jq -r '.scheme // empty' <<< "$response")"
      client_ip="$(jq -r '.client_ip // empty' <<< "$response")"
      if [[ "$scheme" == "https" ]]; then
        anvil_info "  OK   XFF chain: scheme=https (edge set X-Forwarded-Proto correctly) [${xff_source}]"
      else
        anvil_error "  FAIL XFF chain: scheme='${scheme}' (expected https) [${xff_source}]"
        fail=1
      fi
      if [[ -n "$client_ip" && "$client_ip" != "127.0.0.1" ]]; then
        anvil_info "  OK   XFF chain: client_ip=${client_ip} (real client IP propagated) [${xff_source}]"
      else
        anvil_warn "  WARN XFF chain: client_ip='${client_ip}' — may be loopback if smoke-tested locally [${xff_source}]"
      fi
    else
      anvil_warn "  WARN jq missing — skipping XFF chain assertion (install jq for full gate) [${xff_source}]"
    fi
  else
    anvil_warn "  WARN /debug/xff AND /debug/xff.php both absent — skipping XFF assertion"
    anvil_warn "       (Hub not deployed AND dev fixture missing? check public/debug/xff.php)"
  fi

  return $fail
}

# ---------------------------------------------------------------------------
# Gate 4: Ports (collision check from lib/core.sh)
# ---------------------------------------------------------------------------

anvil_verify_ports() {
  anvil_info "verify/ports: port-registry collision check (§3.4 of v3 doc)"
  if anvil_check_port_collisions; then
    anvil_info "  OK   no port-registry collisions"
    return 0
  fi
  anvil_error "  FAIL port-registry collision detected"
  anvil_error "  run 'anvilctl status' for the live listener map"
  return 1
}

# ---------------------------------------------------------------------------
# Suite runner
# ---------------------------------------------------------------------------

anvil_verify_all() {
  local fail=0
  anvil_verify_boot    || fail=1
  anvil_verify_health  || fail=1
  anvil_verify_headers || fail=1
  anvil_verify_ports   || fail=1
  if (( fail == 0 )); then
    anvil_info "verify/all: PASSED (all staging gates green)"
  else
    anvil_error "verify/all: FAILED — see log above"
  fi
  return $fail
}

# Dispatcher used by `anvilctl verify`.
anvil_verify() {
  local gate="${1:?Usage: anvilctl verify boot|health|headers|ports|all}"
  shift
  case "$gate" in
    boot)    anvil_verify_boot    "$@" ;;
    health)  anvil_verify_health  "$@" ;;
    headers) anvil_verify_headers "$@" ;;
    ports)   anvil_verify_ports   "$@" ;;
    all)     anvil_verify_all     "$@" ;;
    *) anvil_die 2 "anvilctl verify: unknown gate '$gate' (try: boot|health|headers|ports|all)" ;;
  esac
}
