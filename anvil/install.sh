#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/install.sh
#
# Anvil unified interactive entrypoint — the ONLY user-facing script.
# All operations are accessible through a single menu (dialog/whiptail).
#
# Three entrypoints exist in the entire project:
#   1. install.sh   — this file (interactive menu + CLI flags)
#   2. bin/anvilctl  — CLI dispatcher for scripted/alias use
#   3. uninstall.sh — clean removal of anvil + conflicting servers
#
# Everything else under lib/ is non-runnable library modules.
#
# Menu sections:
#   Install:       Full, Bootstrap only, Trio only, Build Tengine
#   Runtime:       Start, Stop, Restart service, Status, Stack mode
#   Deploy:        Deploy, Rollback
#   Tenants:       List, Register, Unregister, Scan, Watch
#   EC2:           Provision, Tunnel, Billing
#   Diagnostics:   Doctor, Verify, Logs
#   Maintenance:   Uninstall, Reinstall units, Build assets
#
# Non-interactive usage (passes through to anvilctl):
#   sudo ./install.sh --bootstrap            Phase 1 only
#   sudo ./install.sh --trio [--env prod]   Trio only
#   sudo ./install.sh --full [--env prod]   Bootstrap + Trio
#   sudo ./install.sh --uninstall           Run the uninstaller
#   sudo ./install.sh --doctor              Health checks
#   sudo ./install.sh <anvilctl command>    Any anvilctl subcommand
#
# Requires root; re-execs via sudo when invoked unprivileged.

set -euo pipefail

# ---------------------------------------------------------------------------
# Resolve ANVIL_ROOT
# ---------------------------------------------------------------------------
ANVIL_ROOT="$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")"
export ANVIL_ROOT

# ---------------------------------------------------------------------------
# Parse arguments — if any CLI flag is passed, check for install-specific
# flags first, then fall through to anvilctl for everything else.
# ---------------------------------------------------------------------------
MODE="menu"  # default: interactive menu
for arg in "$@"; do
  case "$arg" in
    --bootstrap)      MODE="bootstrap" ;;
    --trio)           MODE="trio" ;;
    --full)           MODE="full" ;;
    --uninstall)      MODE="uninstall" ;;
    --doctor)         MODE="doctor" ;;
    --menu)           MODE="menu" ;;
    --yes|--noninteractive) NONINTERACTIVE=1 ;;
    --env)            ANVIL_INSTALL_ENV="${2:-production}"; shift 2 ;;
    -h|--help)
      cat <<'EOF'
Anvil — unified interactive entrypoint

Interactive:  sudo ./install.sh

Install shortcuts:
  sudo ./install.sh --bootstrap            Docker, dnsmasq, mkcert, sass
  sudo ./install.sh --trio [--env prod]   Caddy + Tengine + FrankenPHP
  sudo ./install.sh --full [--env prod]   Bootstrap + Trio
  sudo ./install.sh --uninstall           Remove anvil + conflicts
  sudo ./install.sh --doctor              Health checks

Runtime (delegates to anvilctl):
  sudo ./install.sh start [-d]            Start the active stack
  sudo ./install.sh stop                  Stop the active stack
  sudo ./install.sh status                Show stack status
  sudo ./install.sh restart <svc>         Restart one service
  sudo ./install.sh deploy <env>           Deploy to environment
  sudo ./install.sh logs [svc]             Tail service logs
  ... any anvilctl subcommand works
EOF
      exit 0 ;;
    *)
      # Unknown flag or subcommand — delegate to anvilctl.
      MODE="delegate"
      break
      ;;
  esac
done

# ---------------------------------------------------------------------------
# Privilege check
# ---------------------------------------------------------------------------
if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
  echo "This requires root. Re-executing with sudo..." >&2
  exec sudo "${ANVIL_ROOT}/install.sh" "$@"
fi

# ---------------------------------------------------------------------------
# Source config + all shared libs
# ---------------------------------------------------------------------------
# shellcheck source=config/anvil.conf
source "${ANVIL_ROOT}/config/anvil.conf"
# shellcheck source=lib/core.sh
source "${ANVIL_ROOT}/lib/core.sh"
# shellcheck source=lib/caddy.sh
source "${ANVIL_ROOT}/lib/caddy.sh"
# shellcheck source=lib/tengine.sh
source "${ANVIL_ROOT}/lib/tengine.sh"
# shellcheck source=lib/frankenphp.sh
source "${ANVIL_ROOT}/lib/frankenphp.sh"
# shellcheck source=lib/deploy.sh
source "${ANVIL_ROOT}/lib/deploy.sh"
# shellcheck source=lib/verify.sh
source "${ANVIL_ROOT}/lib/verify.sh"
# shellcheck source=lib/registry.sh
source "${ANVIL_ROOT}/lib/registry.sh"
# shellcheck source=lib/docker.sh
source "${ANVIL_ROOT}/lib/docker.sh"
# shellcheck source=lib/ssl.sh
source "${ANVIL_ROOT}/lib/ssl.sh"
# shellcheck source=lib/vhost.sh
source "${ANVIL_ROOT}/lib/vhost.sh"
# shellcheck source=lib/project.sh
source "${ANVIL_ROOT}/lib/project.sh"
# shellcheck source=lib/db.sh
source "${ANVIL_ROOT}/lib/db.sh"
# shellcheck source=lib/ec2.sh
source "${ANVIL_ROOT}/lib/ec2.sh"
# shellcheck source=lib/web.sh
source "${ANVIL_ROOT}/lib/web.sh"

# Installer-specific paths.
: "${ANVIL_SYSTEMD_RESOLV_CONF:=/run/systemd/resolve/resolv.conf}"
: "${ANVIL_DNSMASQ_CONF:=/etc/dnsmasq.d/anvil.conf}"
: "${ANVIL_RESOLVED_CONF:=/etc/systemd/resolved.conf.d/anvil.conf}"
: "${ANVIL_MKCERT_PATH:=/usr/local/bin/mkcert}"
: "${ANVIL_SASS_PATH:=/usr/local/bin/sass}"
: "${ANVIL_MKCERT_GITHUB_API:=https://api.github.com/repos/FiloSottile/mkcert/releases/latest}"
: "${ANVIL_SASS_GITHUB_API:=https://api.github.com/repos/sass/dart-sass/releases/latest}"
: "${ANVIL_FALLBACK_DNS:=1.1.1.1 8.8.8.8}"
: "${DOMAIN_TLD:=test}"

# ---------------------------------------------------------------------------
# UI backend
# ---------------------------------------------------------------------------
UI=""
if command -v dialog >/dev/null 2>&1; then
  UI="dialog"
elif command -v whiptail >/dev/null 2>&1; then
  UI="whiptail"
fi

WARNINGS=()
warn() { WARNINGS+=("$1"); echo "WARNING: $1" >&2; }
ui_msg() { "$UI" --title "$1" --msgbox "$2" 20 70 2>/dev/null || echo -e "\n$1:\n$2"; }
ui_confirm() { "$UI" --title "$1" --yesno "$2" 12 60 2>/dev/null; }

# =========================================================================
# PHASE 1: Bootstrap (Docker, dnsmasq, mkcert, sass, inotify-tools)
# =========================================================================
install_tools() {
  if command -v inotifywait >/dev/null 2>&1; then
    echo "  inotify-tools already present, skipping."
    return 0
  fi
  apt-get update -qq || warn "apt-get update failed"
  apt-get install -y inotify-tools || warn "failed to install inotify-tools"
}

install_docker() {
  if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    echo "  docker + compose already installed."
  else
    apt-get update -qq || warn "apt-get update failed"
    apt-get install -y ca-certificates curl gnupg \
      || { warn "failed to install docker prerequisites"; return 1; }
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc \
      || { warn "failed to download Docker GPG key"; return 1; }
    chmod a+r /etc/apt/keyrings/docker.asc
    local codename
    codename="$(awk -F= '/^VERSION_CODENAME=/{print $2}' /etc/os-release)"
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu ${codename} stable" \
      > /etc/apt/sources.list.d/docker.list
    apt-get update -qq || warn "apt-get update (docker) failed"
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin \
      || { warn "failed to install Docker packages"; return 1; }
  fi
  if ! docker compose version >/dev/null 2>&1; then
    apt-get install -y docker-compose-plugin || warn "docker compose plugin missing"
  else
    echo "  docker compose plugin present."
  fi
}

install_dns() {
  if command -v dnsmasq >/dev/null 2>&1; then
    echo "  dnsmasq already installed."
  else
    apt-get update -qq || warn "apt-get update failed"
    apt-get install -y dnsmasq || { warn "failed to install dnsmasq"; return 1; }
  fi
  local upstreams="" real_resolv="${ANVIL_SYSTEMD_RESOLV_CONF}"
  if [[ -f "$real_resolv" ]]; then
    upstreams="$(grep -E '^nameserver' "$real_resolv" 2>/dev/null | awk '{print $2}' | grep -v '^127\.0\.0\.53$' || true)"
  fi
  {
    echo "# Anvil-managed dnsmasq configuration"
    echo "address=/.${DOMAIN_TLD}/127.0.0.1"
    if [[ -n "$upstreams" ]]; then
      while IFS= read -r ip; do [[ -n "$ip" ]] && echo "server=${ip}"; done <<< "$upstreams"
    else
      local fs; local -a dns_arr; read -ra dns_arr <<< "${ANVIL_FALLBACK_DNS}"
      for fs in "${dns_arr[@]}"; do echo "server=${fs}"; done
    fi
  } > "${ANVIL_DNSMASQ_CONF}"
  echo "  wrote ${ANVIL_DNSMASQ_CONF}"
  mkdir -p "$(dirname "${ANVIL_RESOLVED_CONF}")"
  echo -e "[Resolve]\nDNSStubListener=no" > "${ANVIL_RESOLVED_CONF}"
  echo "  wrote ${ANVIL_RESOLVED_CONF}"
  if [[ -L /etc/resolv.conf ]]; then rm -f /etc/resolv.conf; fi
  if ! grep -q "nameserver 127.0.0.1" /etc/resolv.conf 2>/dev/null; then
    echo "nameserver 127.0.0.1" > /etc/resolv.conf
  fi
  systemctl restart systemd-resolved || warn "failed to restart systemd-resolved"
  systemctl enable dnsmasq || warn "failed to enable dnsmasq"
  systemctl restart dnsmasq || warn "failed to restart dnsmasq"
  echo "  dnsmasq configured: *.${DOMAIN_TLD} -> 127.0.0.1"
}

install_mkcert() {
  if command -v mkcert >/dev/null 2>&1; then
    echo "  mkcert already installed."
  else
    if ! command -v curl >/dev/null 2>&1; then
      apt-get update -qq || warn "apt-get update failed"
      apt-get install -y curl ca-certificates \
        || { warn "curl unavailable"; return 1; }
    fi
    local arch url; arch="$(uname -m)"
    case "$arch" in x86_64|amd64) arch="linux-amd64" ;; aarch64|arm64) arch="linux-arm64" ;;
      armv7l) arch="linux-arm" ;; *) warn "unsupported arch ${arch}"; return 1 ;; esac
    url="$(curl -fsSL "${ANVIL_MKCERT_GITHUB_API}" \
      | grep -oP '"browser_download_url":\s*"\Khttps://[^"]*mkcert[^"]*'"$arch"'[^"]*' \
      | head -1)" || true
    if [[ -z "$url" ]]; then warn "could not determine mkcert download URL"; return 1; fi
    echo "  downloading mkcert..."
    if curl -fsSL "$url" -o "${ANVIL_MKCERT_PATH}"; then
      chmod +x "${ANVIL_MKCERT_PATH}"; echo "  mkcert installed to ${ANVIL_MKCERT_PATH}"
    else warn "failed to download mkcert"; return 1; fi
  fi
  mkcert -install 2>/dev/null || warn "mkcert -install failed"
}

install_sass() {
  if command -v sass >/dev/null 2>&1; then echo "  sass already installed."; return 0; fi
  if ! command -v curl >/dev/null 2>&1; then
    apt-get update -qq || warn "apt-get update failed"
    apt-get install -y curl ca-certificates || { warn "curl unavailable"; return 1; }
  fi
  local arch url tmp sass_bin; arch="$(uname -m)"
  case "$arch" in x86_64|amd64) arch="linux-x64" ;; aarch64|arm64) arch="linux-arm64" ;;
    *) warn "unsupported arch ${arch} for dart-sass"; return 1 ;; esac
  url="$(curl -fsSL "${ANVIL_SASS_GITHUB_API}" \
    | grep -oP '"browser_download_url":\s*"\Khttps://[^"]*dart-sass-[^"]*'"$arch"'\.tar\.gz' \
    | head -1)" || true
  if [[ -z "$url" ]]; then warn "could not determine dart-sass download URL"; return 1; fi
  tmp="$(mktemp -d)"
  if curl -fsSL "$url" -o "${tmp}/sass.tar.gz"; then
    tar -xzf "${tmp}/sass.tar.gz" -C "$tmp"
    sass_bin="$(find "$tmp" -name sass -type f | head -1)"
    if [[ -n "$sass_bin" ]]; then
      cp "$sass_bin" "${ANVIL_SASS_PATH}"; chmod +x "${ANVIL_SASS_PATH}"
      echo "  sass installed to ${ANVIL_SASS_PATH}"
    else warn "sass binary not found in archive"; rm -rf "$tmp"; return 1; fi
  else warn "failed to download dart-sass"; rm -rf "$tmp"; return 1; fi
  rm -rf "$tmp"
}

run_bootstrap() {
  local -a STEPS=(tools docker dns mkcert sass)
  local total=${#STEPS[@]} i=0
  for step in "${STEPS[@]}"; do
    i=$((i + 1)); echo "  [$i/$total] $step"
    "install_${step}" || true
  done
}

# =========================================================================
# PHASE 2: Trio install (delegates to lib/install-trio.sh)
# =========================================================================
run_trio() {
  # shellcheck source=lib/install-trio.sh
  source "${ANVIL_ROOT}/lib/install-trio.sh"
  anvil_install_trio "$@"
}

# =========================================================================
# PHASE 3: Build Tengine from source
# =========================================================================
run_build_tengine() {
  local version="${1:-}"
  if [[ -z "$version" ]]; then
    local -A FLOORS
    while IFS='=' read -r k v; do FLOORS["$k"]="$v"; done < <(_anvil_parse_versions_env)
    version="${FLOORS[TENGINE]:-3.2.0}"
  fi
  echo "Building Tengine ${version} from source..."
  bash "${ANVIL_ROOT}/lib/tengine-build.sh" --version "$version"
}

# =========================================================================
# Doctor
# =========================================================================
run_doctor() {
  echo "=== Anvil Doctor ==="
  anvil_doctor_versions || true
  anvil_caddy_doctor || true
  anvil_tengine_doctor || true
  anvil_frankenphp_doctor || true
  anvil_check_port_collisions || true
  echo "=== Done ==="
}

# =========================================================================
# Runtime control functions (mirror anvilctl dispatch)
# =========================================================================

# --- Start ---
menu_start() {
  local env
  env="$("$UI" --title "Start Stack" --menu \
    "Select environment:" 12 50 3 \
    "dev"       "Local dev (laptop)" \
    "staging"   "Staging server" \
    "production" "Production server" 3>&1 1>&2 2>&3)" || return 0
  export ANVIL_ENV="$env"
  case "$env" in
    dev)
      if [[ "${DATA_SOURCE:-docker}" == "docker" ]]; then
        docker compose -f "${ANVIL_ROOT}/dev/compose.data.yml" up -d || anvil_warn "data tier failed"
      fi
      if [[ "${ANVIL_STACK_MODE:-slim}" == "slim" ]]; then
        anvil_info "starting dev (collapsed mode)"
        nohup "$ANVIL_FRANKENPHP_BIN" run --config "${ANVIL_ROOT}/dev/Caddyfile.dev" \
          >"${ANVIL_LOG_DIR:-/tmp}/anvil-dev.log" 2>&1 &
        anvil_info "dev started (pid $!)"
      else
        anvil_frankenphp_start blue; anvil_tengine_start; anvil_caddy_start
      fi
      ;;
    staging|production)
      anvil_caddy_start; anvil_tengine_start; anvil_frankenphp_start blue
      ;;
  esac
  ui_msg "Started" "Stack started for env=$env"
}

# --- Stop ---
menu_stop() {
  case "${ANVIL_ENV:-dev}" in
    dev)
      if [[ "${ANVIL_STACK_MODE:-slim}" == "slim" ]]; then
        pkill -f "frankenphp run --config ${ANVIL_ROOT}/dev/Caddyfile.dev" 2>/dev/null || true
      else
        anvil_caddy_stop; anvil_tengine_stop; anvil_frankenphp_stop blue
      fi
      ;;
    *) anvil_caddy_stop; anvil_tengine_stop; anvil_frankenphp_stop blue ;;
  esac
  ui_msg "Stopped" "Stack stopped."
}

# --- Status ---
menu_status() {
  local out
  out="=== Anvil v3 status ===\n"
  out+="env=${ANVIL_ENV:-dev} stack=${ANVIL_STACK_MODE:-slim} data=${DATA_SOURCE:-docker}\n\n"
  out+="--- Caddy ---\n$(anvil_caddy_status 2>&1 || echo 'not running')\n"
  out+="--- Tengine ---\n$(anvil_tengine_status 2>&1 || echo 'not running')\n"
  out+="--- FrankenPHP ---\n$(anvil_frankenphp_status "${ANVIL_ENV:-dev}" 2>&1 || echo 'not running')\n"
  out+="\n--- Tenants ---\n$(anvil_registry_list 2>&1)"
  ui_msg "Status" "$out"
}

# --- Restart service ---
menu_restart() {
  local svc
  svc="$("$UI" --title "Restart Service" --menu \
    "Select service to restart:" 14 50 6 \
    "caddy"     "Caddy edge proxy" \
    "tengine"   "Tengine internal LB" \
    "app@blue"  "FrankenPHP blue worker" \
    "app@green" "FrankenPHP green worker" \
    "data"      "Docker data tier" 3>&1 1>&2 2>&3)" || return 0
  case "$svc" in
    caddy)         anvil_caddy_start ;;
    tengine)       anvil_tengine_start ;;
    app@blue)      systemctl restart anvil-frankenphp@blue ;;
    app@green)     systemctl restart anvil-frankenphp@green ;;
    data)          docker compose -f "${ANVIL_ROOT}/dev/compose.data.yml" restart ;;
  esac
  ui_msg "Restarted" "$svc restarted."
}

# --- Deploy ---
menu_deploy() {
  local env
  env="$("$UI" --title "Deploy" --menu \
    "Select target environment:" 12 50 3 \
    "staging"   "Staging server" \
    "production" "Production server" 3>&1 1>&2 2>&3)" || return 0
  if ui_confirm "Deploy to $env" "Deploy the current release to $env?\n\nThis will:.Stop the inactive pool, swap release, verify health.\n\nProceed?"; then
    anvil_deploy "$env" --strategy blue-green
    ui_msg "Deployed" "Deployment to $env complete."
  fi
}

# --- Rollback ---
menu_rollback() {
  local env
  env="$("$UI" --title "Rollback" --menu \
    "Select environment to rollback:" 12 50 3 \
    "staging"   "Staging server" \
    "production" "Production server" 3>&1 1>&2 2>&3)" || return 0
  if ui_confirm "Rollback $env" "Swap $env back to the previous pool?\n\nProceed?"; then
    anvil_rollback "$env"
    ui_msg "Rolled back" "$env rolled back."
  fi
}

# --- Logs ---
menu_logs() {
  local svc
  svc="$("$UI" --title "Logs" --menu \
    "Select service to tail logs:" 14 50 5 \
    "caddy"       "Caddy edge (journalctl)" \
    "tengine"     "Tengine LB (journalctl)" \
    "app"         "FrankenPHP blue (journalctl)" \
    "data"        "Docker data tier" \
    "dev"         "Dev collapsed mode log" 3>&1 1>&2 2>&3)" || return 0
  case "$svc" in
    caddy)         journalctl -u anvil-caddy -f ;;
    tengine)       journalctl -u anvil-tengine -f ;;
    app)           journalctl -u anvil-frankenphp@blue -f ;;
    data)          docker compose -f "${ANVIL_ROOT}/dev/compose.data.yml" logs -f ;;
    dev)           tail -f "${ANVIL_LOG_DIR:-/tmp}/anvil-dev.log" ;;
  esac
}

# --- Verify ---
menu_verify() {
  local check
  check="$("$UI" --title "Verify" --menu \
    "Select validation gate:" 14 55 6 \
    "boot"    "Systemd unit boot check" \
    "health"  "HTTP health endpoints" \
    "headers" "Security header audit" \
    "ports"   "Port binding verification" \
    "all"     "Run all gates" 3>&1 1>&2 2>&3)" || return 0
  local out
  out="$(anvil_verify "$check" 2>&1)"
  ui_msg "Verify ($check)" "$out"
}

# --- Tenants ---
menu_tenants() {
  while true; do
    local action
    action="$("$UI" --title "Tenant Management" --menu \
      "Tenant registry operations:" 14 55 6 \
      "list"       "List all tenants" \
      "register"   "Register a new tenant" \
      "unregister" "Remove a tenant" \
      "scan"       "Re-scan for tenants" \
      "back"       "Return to main menu" 3>&1 1>&2 2>&3)" || return 0
    case "$action" in
      list)
        local out; out="$(anvil_registry_list 2>&1)"
        ui_msg "Tenants" "$out"
        ;;
      register)
        local slug
        slug="$("$UI" --title "Register Tenant" --inputbox \
          "Enter tenant slug (e.g. myproject):" 10 50 3>&1 1>&2 2>&3)" || continue
        [[ -n "$slug" ]] && anvil_registry_register "$slug"
        ui_msg "Registered" "Tenant '$slug' registered."
        ;;
      unregister)
        local slug
        slug="$("$UI" --title "Unregister Tenant" --inputbox \
          "Enter tenant slug to remove:" 10 50 3>&1 1>&2 2>&3)" || continue
        if [[ -n "$slug" ]] && ui_confirm "Unregister" "Remove tenant '$slug'? This deletes ${WWW_DIR:-/var/www/anvil}/$slug/"; then
          anvil_registry_unregister "$slug"
          ui_msg "Unregistered" "Tenant '$slug' removed."
        fi
        ;;
      scan)
        anvil_registry_scan
        ui_msg "Scan" "Tenant scan complete."
        ;;
      back|"") break ;;
    esac
  done
}

# --- EC2 ---
menu_ec2() {
  while true; do
    local action
    action="$("$UI" --title "EC2 Operations" --menu \
      "AWS EC2 + RDS provisioning:" 14 55 6 \
      "provision"     "Provision EC2 + RDS instance" \
      "tunnel"        "Open SSH tunnel to RDS" \
      "billing"       "Show this month's AWS cost" \
      "billing-alarm" "Create CloudWatch billing alarm" \
      "back"          "Return to main menu" 3>&1 1>&2 2>&3)" || return 0
    case "$action" in
      provision)  anvil_ec2_provision ;;
      tunnel)     anvil_ec2_tunnel ;;
      billing)    local out; out="$(anvil_ec2_billing 2>&1)"; ui_msg "AWS Billing" "$out" ;;
      billing-alarm) anvil_ec2_billing_alarm ;;
      back|"") break ;;
    esac
  done
}

# =========================================================================
# Main interactive menu
# =========================================================================
menu_main() {
  if [[ -z "$UI" ]]; then
    echo "ERROR: neither 'dialog' nor 'whiptail' is installed." >&2
    echo "       Install one first:  apt-get install -y dialog" >&2
    echo "       Or use non-interactive mode:  sudo ./install.sh --full" >&2
    exit 1
  fi

  while true; do
    local choice
    choice="$("$UI" --clear --title "Anvil v3" \
      --menu "Select an action:" 24 65 16 \
      "1"  "Install — Full (bootstrap + trio)" \
      "2"  "Install — Bootstrap only (Docker, DNS, mkcert, sass)" \
      "3"  "Install — Trio only (Caddy + Tengine + FrankenPHP)" \
      "4"  "Install — Build Tengine from source" \
      "5"  "Runtime — Start stack" \
      "6"  "Runtime — Stop stack" \
      "7"  "Runtime — Restart service" \
      "8"  "Runtime — Status" \
      "9"  "Deploy — Blue/green deploy" \
      "10" "Deploy — Rollback" \
      "11" "Tenants — Manage tenant registry" \
      "12" "EC2 — Provision / tunnel / billing" \
      "13" "Diagnostics — Doctor (health check)" \
      "14" "Diagnostics — Verify (staging gates)" \
      "15" "Diagnostics — Logs" \
      "16" "Maintenance — Reinstall systemd units" \
      "17" "Maintenance — Build frontend assets" \
      "18" "Uninstall — Remove anvil + conflicts" \
      "19" "Exit" 3>&1 1>&2 2>&3)" || { clear; exit 0; }

    case "$choice" in
      1)
        ui_confirm "Full Install" "This will install:\n\n  1. Docker Engine + Compose\n  2. dnsmasq (*.test DNS)\n  3. mkcert (local CA)\n  4. dart-sass\n  5. inotify-tools\n  6. Caddy + Tengine + FrankenPHP\n  7. Systemd units + firewall\n\nProceed?" \
          && { run_bootstrap; run_trio --noninteractive; ui_msg "Done" "Full install complete.\n\nNext:  anvilctl doctor"; }
        ;;
      2)
        run_bootstrap
        ui_msg "Done" "Bootstrap complete.\n\nRun trio install next, or:\n  sudo ./install.sh --trio"
        ;;
      3)
        run_trio --noninteractive
        ;;
      4)
        run_build_tengine
        ;;
      5)  menu_start ;;
      6)  menu_stop ;;
      7)  menu_restart ;;
      8)  menu_status ;;
      9)  menu_deploy ;;
      10) menu_rollback ;;
      11) menu_tenants ;;
      12) menu_ec2 ;;
      13)
        run_doctor
        ui_msg "Doctor" "Health check complete.\n\nFix any issues above, then:\n  anvilctl start"
        ;;
      14) menu_verify ;;
      15) menu_logs ;;
      16)
        anvil_caddy_install_config
        anvil_tengine_install_config
        anvil_frankenphp_install_configs
        for unit in anvil-caddy.service anvil-tengine.service anvil-frankenphp@.service anvil-secrets.service; do
          install -m 0644 "${ANVIL_ROOT}/systemd/${unit}" "/etc/systemd/system/${unit}"
        done
        install -d -m 0755 /opt/anvil/bin
        install -m 0755 "${ANVIL_ROOT}/bin/fetch-secrets.sh" /opt/anvil/bin/fetch-secrets.sh
        systemctl daemon-reload
        ui_msg "Units Reinstalled" "Systemd units + configs reinstalled.\nRun 'Start stack' to boot."
        ;;
      17)
        anvil_build_assets
        ui_msg "Assets Built" "Frontend assets compiled via dart-sass."
        ;;
      18)
        exec "${ANVIL_ROOT}/uninstall.sh" --yes
        ;;
      19|"") clear; exit 0 ;;
    esac
  done
}

# =========================================================================
# MAIN
# =========================================================================
case "$MODE" in
  bootstrap)
    echo "Anvil bootstrap installer"; run_bootstrap
    echo "Bootstrap complete. Run trio install next:  sudo ./install.sh --trio"
    ;;
  trio)
    run_trio --noninteractive ${ANVIL_INSTALL_ENV:+--env $ANVIL_INSTALL_ENV}
    ;;
  full)
    echo "Anvil full installer (bootstrap + trio)"; run_bootstrap
    run_trio --noninteractive ${ANVIL_INSTALL_ENV:+--env $ANVIL_INSTALL_ENV}
    ;;
  uninstall)
    exec "${ANVIL_ROOT}/uninstall.sh" --yes
    ;;
  doctor)
    run_doctor
    ;;
  delegate)
    # Fall through to anvilctl for any other subcommand.
    exec "${ANVIL_ROOT}/bin/anvilctl" "$@"
    ;;
  menu)
    menu_main
    ;;
esac
