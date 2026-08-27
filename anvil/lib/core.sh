#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/lib/core.sh
#
# The shared engine helpers used by every other lib/*.sh and every skin
# (anvilctl / TUI / Web UI). Pure functions only — no side effects on source.
#
# Responsibilities:
#   * Logging primitives (anvil_log, anvil_die, anvil_require)
#   * Floor-version checks against config/versions.env  (anvil_doctor_versions)
#   * Listener-map / port-registry probe                   (anvil_listener_map)
#   * Inter-lib contract: ANVIL_ROOT resolution + config sourcing guard
#
# Sourcing is safe: only definitions, set -euo pipefail, guarded config source.

set -euo pipefail

# Resolve ANVIL_ROOT if a sibling lib sourced us without anvilctl's help.
if [[ -z "${ANVIL_ROOT:-}" ]]; then
  ANVIL_ROOT="$(dirname "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")")"
fi
export ANVIL_ROOT

# Config file is sourced once per process; subsequent sources are no-ops
# because every var uses ${VAR:-default} in config/anvil.conf.
if [[ -z "${ANVIL_CONF_LOADED:-}" ]]; then
  # shellcheck source=../config/anvil.conf
  source "${ANVIL_ROOT}/config/anvil.conf"
  export ANVIL_CONF_LOADED=1
fi

# Path to the version-floors file (single source of truth).
ANVIL_VERSIONS_ENV="${ANVIL_ROOT}/config/versions.env"
export ANVIL_VERSIONS_ENV

# ---------------------------------------------------------------------------
# Logging primitives
# ---------------------------------------------------------------------------

# anvil_log LEVEL MSG...   — writes a timestamped line to stderr.
# Levels: DEBUG (off unless ANVIL_DEBUG=1), INFO, WARN, ERROR.
anvil_log() {
  local level="${1:?}"
  shift
  local msg="$*"
  local ts
  ts="$(date -u +'%Y-%m-%dT%H:%M:%SZ')"
  case "$level" in
    DEBUG) [[ "${ANVIL_DEBUG:-0}" == "1" ]] || return 0 ;;
    INFO|WARN|ERROR) ;;
    *) level=INFO ;;
  esac
  printf '%s [%s] anvil: %s\n' "$ts" "$level" "$msg" >&2
}

# Convenience wrappers.
anvil_info()  { anvil_log INFO  "$@"; }
anvil_warn()  { anvil_log WARN  "$@"; }
anvil_error() { anvil_log ERROR "$@"; }
anvil_debug() { anvil_log DEBUG "$@"; }

# anvil_die CODE MSG...   — log ERROR then exit CODE.
anvil_die() {
  local code="${1:?}"
  shift
  anvil_error "$*"
  exit "$code"
}

# anvil_require CMD...   — exit 127 with a clear message if any CMD is missing.
anvil_require() {
  local missing=()
  for cmd in "$@"; do
    if ! command -v "$cmd" >/dev/null 2>&1; then
      missing+=("$cmd")
    fi
  done
  if (( ${#missing[@]} > 0 )); then
    anvil_die 127 "missing required commands: ${missing[*]} (install via anvil/install.sh)"
  fi
}

# ---------------------------------------------------------------------------
# Version-floor checks  (anvilctl doctor)
# ---------------------------------------------------------------------------

# _anvil_parse_versions_env  — emits KEY=VALUE lines for non-comment, non-blank.
_anvil_parse_versions_env() {
  [[ -f "$ANVIL_VERSIONS_ENV" ]] || return 1
  awk -F= '/^[A-Z][A-Z0-9_]*=/ {gsub(/[[:space:]].*/, "", $2); print $1"="$2}' \
    "$ANVIL_VERSIONS_ENV"
}

# _anvil_version_ge A B  — semver compare A >= B. Returns 0 if A>=B, 1 if A<B,
# 2 if either is non-semver (caller decides whether to warn).
_anvil_version_ge() {
  local a="${1:?}" b="${2:?}"
  # Strip leading 'v' and any build metadata.
  a="${a#v}"; a="${a%%-*}"; a="${a%%+*}"
  b="${b#v}"; b="${b%%-*}"; b="${b%%+*}"
  # Quick exit on equality.
  [[ "$a" == "$b" ]] && return 0
  # Compare major.minor.patch numerically.
  local IFS=. a_major a_minor a_patch b_major b_minor b_patch
  read -r a_major a_minor a_patch <<< "$a"
  read -r b_major b_minor b_patch <<< "$b"
  a_major="${a_major:-0}"; a_minor="${a_minor:-0}"; a_patch="${a_patch:-0}"
  b_major="${b_major:-0}"; b_minor="${b_minor:-0}"; b_patch="${b_patch:-0}"
  # Reject non-numeric.
  [[ "$a_major$a_minor$a_patch$b_major$b_minor$b_patch" =~ ^[0-9]+$ ]] || return 2
  if (( a_major > b_major )); then return 0; fi
  (( a_major < b_major )) && return 1
  if (( a_minor > b_minor )); then return 0; fi
  (( a_minor < b_minor )) && return 1
  (( a_patch >= b_patch )) && return 0
  return 1
}

# _anvil_caddy_version  — prints the installed Caddy version (e.g. "v2.11.4")
# or returns 1 if caddy is not on PATH.
_anvil_caddy_version() {
  command -v caddy >/dev/null 2>&1 || return 1
  caddy version 2>/dev/null | awk '{print $1; exit}'
}

# _anvil_frankenphp_version  — prints FrankenPHP version (e.g. "v1.12.7").
_anvil_frankenphp_version() {
  command -v frankenphp >/dev/null 2>&1 || return 1
  frankenphp version 2>/dev/null | awk '{print $2; exit}'
}

# _anvil_frankenphp_embedded_caddy  — prints the Caddy lineage string from
# `frankenphp version` output, e.g. "Caddy v2.11.4-lineage". Returns 1 if
# frankenphp is missing or the lineage line is absent.
_anvil_frankenphp_embedded_caddy() {
  command -v frankenphp >/dev/null 2>&1 || return 1
  frankenphp version 2>/dev/null | awk '/[Cc]addy/ {for (i=1;i<=NF;i++) if ($i ~ /^v?[0-9]+\.[0-9]+\.[0-9]+/) {print $i; exit}}'
}

# _anvil_tengine_version  — prints Tengine version (e.g. "Tengine/3.2.0").
_anvil_tengine_version() {
  local bin="${ANVIL_TENGINE_BIN:-/usr/local/tengine/sbin/nginx}"
  [[ -x "$bin" ]] || return 1
  "$bin" -v 2>&1 | awk -F/ '/^[Tt]engine/ {print $2; exit}' | awk '{print $1}'
}

# anvil_doctor_versions   — exit 0 if every floor in versions.env is satisfied
# by the installed binaries, 1 otherwise. Prints a one-line verdict per check.
# Doctor skips Tengine if it is not installed AND we are in collapsed dev mode
# (the trio's Tengine tier is optional there per §3.5 of the v3 doc).
anvil_doctor_versions() {
  local fail=0
  local floor_caddy floor_frankenphp floor_tengine floor_embedded
  while IFS='=' read -r k v; do
    case "$k" in
      CADDY)                       floor_caddy="$v" ;;
      FRANKENPHP)                  floor_frankenphp="$v" ;;
      TENGINE)                     floor_tengine="$v" ;;
      FRANKENPHP_EMBEDDED_CADDY)   floor_embedded="$v" ;;
    esac
  done < <(_anvil_parse_versions_env)

  # --- Caddy ---
  if installed="$(_anvil_caddy_version 2>/dev/null)"; then
    if _anvil_version_ge "$installed" "$floor_caddy"; then
      anvil_info "OK    caddy $installed (floor $floor_caddy)"
    else
      anvil_error "FAIL  caddy $installed < floor $floor_caddy (CVE posture)"
      fail=1
    fi
  else
    # Caddy absent is fine in dev collapsed mode (FrankenPHP embeds Caddy);
    # in prod it is required. We do not enforce environment here — anvilctl
    # decides whether to call this on the Caddy binary specifically.
    anvil_warn "MISS  caddy not on PATH (collapsed dev mode? OK; prod requires it)"
  fi

  # --- FrankenPHP ---
  if installed="$(_anvil_frankenphp_version 2>/dev/null)"; then
    if _anvil_version_ge "$installed" "$floor_frankenphp"; then
      anvil_info "OK    frankenphp $installed (floor $floor_frankenphp)"
    else
      anvil_error "FAIL  frankenphp $installed < floor $floor_frankenphp"
      fail=1
    fi
    # Embedded-Caddy lineage check (CVE-2026-27589 posture, §2.2 v3 doc).
    if emb="$(_anvil_frankenphp_embedded_caddy 2>/dev/null)"; then
      if _anvil_version_ge "$emb" "$floor_embedded"; then
        anvil_info "OK    frankenphp embedded-caddy $emb (floor $floor_embedded)"
      else
        anvil_error "FAIL  frankenphp embedded-caddy $emb < floor $floor_embedded — rebuild via xcaddy/docker"
        fail=1
      fi
    else
      anvil_warn "MISS  frankenphp embedded-caddy lineage undetectable (manual check needed)"
    fi
  else
    anvil_error "FAIL  frankenphp not installed (required by ADR-017 worker mode)"
    fail=1
  fi

  # --- Tengine (optional in collapsed dev) ---
  if installed="$(_anvil_tengine_version 2>/dev/null)"; then
    if _anvil_version_ge "$installed" "$floor_tengine"; then
      anvil_info "OK    tengine $installed (floor $floor_tengine)"
    else
      anvil_error "FAIL  tengine $installed < floor $floor_tengine (CVE-2026-42945 'Rift' — Option B until upgrade)"
      fail=1
    fi
  else
    anvil_warn "MISS  tengine not installed (collapsed dev? OK; prod LB tier requires it)"
  fi

  return $fail
}

# ---------------------------------------------------------------------------
# Listener map (§3.4 of v3 doc) — port-registry collision probe
# ---------------------------------------------------------------------------

# Every entry: PURPOSE  BIND  OWNER. Order matches the v3 doc registry.
ANVIL_PORT_REGISTRY=(
  "Public HTTP (redirect)        :80/tcp              caddy"
  "Public HTTPS / HTTP/3         :443/tcp+udp         caddy"
  "Caddy admin API               127.0.0.1:2020       caddy"
  "Tengine app listener          127.0.0.1:8081       tengine"
  "FrankenPHP blue app           127.0.0.1:8090       frankenphp@blue"
  "FrankenPHP green app          127.0.0.1:8091       frankenphp@green"
  "FrankenPHP blue admin         127.0.0.1:2019       frankenphp@blue"
  "FrankenPHP green admin        127.0.0.1:2018       frankenphp@green"
  "Anvil Web UI                  127.0.0.1:9999       anvil-web"
  "phpMyAdmin (dev)              127.0.0.1:8080       compose"
  "node_exporter                 127.0.0.1:9100       monitoring"
)

# anvil_listener_map   — prints the live listener table (ss -ltn + registry).
# Anvil's doctor uses this to detect port-registry collisions BEFORE start.
anvil_listener_map() {
  anvil_require ss
  printf '%-32s %-22s %s\n' "PURPOSE" "BIND" "OWNER (registry)"
  printf '%-32s %-22s %s\n' "-------" "----" "----------------"
  local bind owner purpose
  for entry in "${ANVIL_PORT_REGISTRY[@]}"; do
    read -r purpose bind owner <<< "$entry"
    printf '%-32s %-22s %s\n' "$purpose" "$bind" "$owner"
  done
  echo
  printf '%-32s %-22s %s\n' "LIVE LISTENERS (ss -ltn)" "PID/Program" ""
  ss -ltnp 2>/dev/null | awk 'NR>1 {printf "%-32s %s\n", $4, $7}' | sort -u | head -30
}

# anvil_check_port_collisions   — exit 1 if any registry bind is held by a
# process whose comm name does not match its expected owner. Catches the
# classic "leftover nginx holding :8081 from a previous stack" class of bug.
anvil_check_port_collisions() {
  anvil_require ss
  local problems=0
  for entry in "${ANVIL_PORT_REGISTRY[@]}"; do
    local purpose bind owner
    read -r purpose bind owner <<< "$entry"
    # Extract the host:port from "127.0.0.1:8081" / ":80/tcp" / ":443/tcp+udp"
    local port
    port="$(awk -F: '{print $NF}' <<< "$bind" | awk '{print $1}' | sed 's/[^0-9]//g')"
    [[ -z "$port" ]] && continue
    # Look for a process holding that port (ss -ltn returns "127.0.0.1:8081").
    if holders="$(ss -ltnp 2>/dev/null | awk -v p=":$port" '$4 ~ p {print $7}' | sort -u)"; then
      if [[ -n "$holders" ]]; then
        while read -r holder; do
          # holder looks like "users:(("caddy",pid=1234,fd=7))"
          local comm
          comm="$(sed -E 's/.*"([^"]+)".*/\1/' <<< "$holder")"
          case "$owner" in
            caddy)               [[ "$comm" == "caddy" ]] && continue ;;
            tengine)             [[ "$comm" == "nginx" || "$comm" == "tengine" ]] && continue ;;
            frankenphp@*)        [[ "$comm" == "frankenphp" ]] && continue ;;
            anvil-web|compose|monitoring) continue ;;
          esac
          anvil_warn "port $port held by '$comm' but registry owner is '$owner' — collision"
          problems=1
        done <<< "$holders"
      fi
    fi
  done
  return $problems
}
