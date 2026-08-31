#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/install.sh
#
# Anvil unified installer — the ONLY user-facing installer.
# All other install scripts are internal modules in lib/.
#
# Provides an interactive menu (dialog/whiptail) covering:
#   1. Bootstrap     — Docker, dnsmasq, mkcert, dart-sass, inotify-tools
#   2. Trio install  — Caddy + Tengine + FrankenPHP (pinned binaries, systemd, firewall)
#   3. Build Tengine — Compile Tengine from source (when packages unavailable)
#   4. Uninstall     — Remove old anvil + conflicting servers
#   5. Doctor        — CVE-floor version check + port collision audit
#
# Non-interactive usage:
#   sudo ./install.sh --bootstrap            Phase 1 only (Docker, DNS, mkcert, sass)
#   sudo ./install.sh --trio [--env prod]   Trio only (Caddy + Tengine + FrankenPHP)
#   sudo ./install.sh --full [--env prod]   Bootstrap + Trio (everything)
#   sudo ./install.sh --uninstall           Run the uninstaller
#   sudo ./install.sh --doctor              Run health checks
#
# Requires root; re-execs via sudo when invoked unprivileged.

set -euo pipefail

# ---------------------------------------------------------------------------
# Resolve ANVIL_ROOT
# ---------------------------------------------------------------------------
ANVIL_ROOT="$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")"
export ANVIL_ROOT

# ---------------------------------------------------------------------------
# Parse arguments
# ---------------------------------------------------------------------------
MODE="menu"  # default: interactive menu
for arg in "$@"; do
  case "$arg" in
    --bootstrap)      MODE="bootstrap" ;;
    --trio)           MODE="trio" ;;
    --full)           MODE="full" ;;
    --uninstall)      MODE="uninstall" ;;
    --doctor)         MODE="doctor" ;;
    --yes|--noninteractive) NONINTERACTIVE=1 ;;
    --env)            ANVIL_INSTALL_ENV="${2:-production}"; shift 2 ;;
    -h|--help)
      cat <<'EOF'
Anvil unified installer

Interactive:  sudo ./install.sh

Non-interactive shortcuts:
  sudo ./install.sh --bootstrap            Docker, dnsmasq, mkcert, sass
  sudo ./install.sh --trio [--env prod]   Caddy + Tengine + FrankenPHP
  sudo ./install.sh --full [--env prod]   Bootstrap + Trio
  sudo ./install.sh --uninstall           Remove old anvil + conflicts
  sudo ./install.sh --doctor              Health checks
EOF
      exit 0 ;;
    *) echo "Unknown option: $arg" >&2; exit 1 ;;
  esac
done

# ---------------------------------------------------------------------------
# Privilege check
# ---------------------------------------------------------------------------
if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
  echo "This installer must run as root. Re-executing with sudo..." >&2
  exec sudo "${ANVIL_ROOT}/install.sh" "$@"
fi

# ---------------------------------------------------------------------------
# Source config + shared libs
# ---------------------------------------------------------------------------
# shellcheck source=config/anvil.conf
source "${ANVIL_ROOT}/config/anvil.conf"
# shellcheck source=lib/core.sh
source "${ANVIL_ROOT}/lib/core.sh"

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
    # shellcheck source=lib/core.sh
    source "${ANVIL_ROOT}/lib/core.sh"
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
  # shellcheck source=lib/caddy.sh
  source "${ANVIL_ROOT}/lib/caddy.sh"
  # shellcheck source=lib/tengine.sh
  source "${ANVIL_ROOT}/lib/tengine.sh"
  # shellcheck source=lib/frankenphp.sh
  source "${ANVIL_ROOT}/lib/frankenphp.sh"

  echo "=== Anvil Doctor ==="
  anvil_doctor_versions || true
  anvil_caddy_doctor || true
  anvil_tengine_doctor || true
  anvil_frankenphp_doctor || true
  anvil_check_port_collisions || true
  echo "=== Done ==="
}

# =========================================================================
# Interactive menu
# =========================================================================
menu_main() {
  # Ensure dialog/whiptail is available for the menu.
  if [[ -z "$UI" ]]; then
    echo "ERROR: neither 'dialog' nor 'whiptail' is installed." >&2
    echo "       Install one first:  apt-get install -y dialog" >&2
    echo "       Or use non-interactive mode:  sudo ./install.sh --full" >&2
    exit 1
  fi

  while true; do
    local choice
    choice="$("$UI" --clear --title "Anvil Installer" \
      --menu "Select an action:" 22 65 12 \
      "1" "Full install (bootstrap + trio)" \
      "2" "Bootstrap only (Docker, DNS, mkcert, sass)" \
      "3" "Trio only (Caddy + Tengine + FrankenPHP)" \
      "4" "Build Tengine from source" \
      "5" "Uninstall (remove anvil + conflicts)" \
      "6" "Doctor (version + port health check)" \
      "7" "Exit" 3>&1 1>&2 2>&3)" || { clear; exit 0; }

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
      5)
        exec "${ANVIL_ROOT}/uninstall.sh" --yes
        ;;
      6)
        run_doctor
        ui_msg "Doctor" "Health check complete.\n\nFix any issues above, then:\n  anvilctl start"
        ;;
      7|"") clear; exit 0 ;;
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
  menu)
    menu_main
    ;;
esac
