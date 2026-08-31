#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/migrate.sh
#
# Migration script: uninstalls the previous anvil (all phases) and any
# manually installed servers that conflict with the new anvil trio
# (Caddy + Tengine + FrankenPHP).
#
# What it removes:
#   +---------------------------------------------------------------------+
#   | 1. Anvil v3 trio services (systemd)                                |
#   | 2. Anvil v1/v2 Docker stack (containers + volumes)                 |
#   | 3. Anvil binaries  (caddy, frankenphp, tengine, mkcert, sass)       |
#   | 4. Anvil configs   (/etc/anvil/, /etc/dnsmasq.d/anvil.conf, etc.)  |
#   | 5. Anvil runtime  (/opt/anvil/, /var/log/anvil/, /var/lib/anvil/) |
#   | 6. Anvil users    (caddy, tengine, anvil system accounts)          |
#   | 7. Conflicting web servers manually installed:                     |
#   |      - Apache httpd (apache2 / httpd)                               |
#   |      - Nginx (system package, not Docker)                           |
#   |      - PHP-FPM (system package, not Docker)                         |
#   |      - Caddy (if installed independently of anvil)                 |
#   |      - FrankenPHP (if installed independently of anvil)             |
#   |      - Tengine / OpenResty (if installed independently of anvil)   |
#   | 8. Certbot (superseded by Caddy ACME)                               |
#   | 9. Obsolete firewall / sysctl / limits config                       |
#   | 10. DNS reversion (restore systemd-resolved if anvil broke it)     |
#   +---------------------------------------------------------------------+
#
# What it does NOT remove:
#   - Docker Engine itself (other workflows may depend on it)
#   - The anvil/ source directory (git repo) -- only the installed artefacts
#   - User data in $WWW_DIR (project source code)
#   - Host-level MySQL / Redis (installed outside anvil)
#   - SSH keys or AWS credentials
#
# Usage:
#   sudo ./migrate.sh              # interactive -- confirm each phase
#   sudo ./migrate.sh --yes        # non-interactive -- run all phases
#   sudo ./migrate.sh --phase N    # run only phase N (1-6)
#   sudo ./migrate.sh --dry-run   # show what would be removed, don't act
#
# Exit codes: 0 ok, 1 user-abort, 2 not-root, 3 phase error
#

set -euo pipefail

# ---------------------------------------------------------------------------
# Script location (used for .anvil-web.pid cleanup regardless of phase)
# ---------------------------------------------------------------------------
ANVIL_ROOT_MIGRATE="$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")"
export ANVIL_ROOT_MIGRATE

# ---------------------------------------------------------------------------
# Colours and UI helpers
# ---------------------------------------------------------------------------
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m';  RST='\033[0m'

info()  { printf "${GREEN}[INFO]${RST}  %s\n" "${*}"; }
warn()  { printf "${YELLOW}[WARN]${RST}  %s\n" "${*}" >&2; }
error() { printf "${RED}[FAIL]${RST}  %s\n" "${*}" >&2; }
phase() { printf "\n${BOLD}${CYAN}=== Phase %s: %s ===${RST}\n" "${1}" "${2}"; }
step()  { printf "  ${BOLD}->${RST} %s ... " "${*}"; }
ok()    { printf "${GREEN}done${RST}\n"; }
skip() { printf "${YELLOW}skipped (not present)${RST}\n"; }

# ---------------------------------------------------------------------------
# Parse arguments
# ---------------------------------------------------------------------------
NONINTERACTIVE=0
DRY_RUN=0
TARGET_PHASE=""

for arg in "$@"; do
  case "$arg" in
    --yes|--noninteractive) NONINTERACTIVE=1 ;;
    --dry-run)           DRY_RUN=1 ;;
    --phase)             TARGET_PHASE="${2:-}"; shift 2 ;;
    -h|--help)
      cat <<'EOF'
Anvil migration script -- removes old anvil + conflicting servers.

Usage:
  sudo ./migrate.sh              interactive
  sudo ./migrate.sh --yes        non-interactive
  sudo ./migrate.sh --phase N    run only phase N (1-6)
  sudo ./migrate.sh --dry-run    show what would be removed

Phases:
  1  Stop anvil services + Docker containers
  2  Remove anvil binaries + system packages (Docker, dnsmasq, inotify-tools)
  3  Remove anvil configs, runtime dirs, systemd units
  4  Remove conflicting servers (Apache, Nginx, PHP-FPM, Caddy, FrankenPHP, Tengine, Certbot)
  5  Remove anvil system users
  6  Restore DNS / cleanup firewall + sysctl
EOF
      exit 0 ;;
    *) error "unknown option: $arg"; exit 2 ;;
  esac
done

# ---------------------------------------------------------------------------
# Privilege check
# ---------------------------------------------------------------------------
if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
  error "this script must run as root. Re-executing with sudo..."
  exec sudo "${BASH_SOURCE[0]}" "$@"
fi

if [[ $DRY_RUN -eq 1 ]]; then
  warn "DRY-RUN MODE -- nothing will be changed"
fi

# ---------------------------------------------------------------------------
# Confirm gateway (interactive only)
# ---------------------------------------------------------------------------
if [[ $NONINTERACTIVE -eq 0 && $DRY_RUN -eq 0 ]]; then
  echo
  printf "${BOLD}This will remove:${RST}\n"
  echo "  - All anvil services, binaries, configs, runtime directories"
  echo "  - Docker containers/volumes from anvil compose stacks"
  echo "  - Conflicting servers: Apache, Nginx, PHP-FPM, Caddy, FrankenPHP, Tengine, Certbot"
  echo "  - Anvil system users (caddy, tengine, anvil)"
  echo
  echo "${YELLOW}It will NOT remove:${RST}"
  echo "  - Docker Engine itself"
  echo "  - Your anvil/ source directory (git repo)"
  echo "  - Project source code in www/"
  echo "  - Host MySQL / Redis / SSH keys / AWS credentials"
  echo
  read -r -p "Proceed? [y/N] " yn
  case "$yn" in
    y|Y|yes|YES) ;;
    *) echo "Aborted."; exit 1 ;;
  esac
fi

# ---------------------------------------------------------------------------
# Helpers: safe remove
# ---------------------------------------------------------------------------
_rm() {
  if [[ $DRY_RUN -eq 1 ]]; then
    echo "  [dry-run] rm $*"
    return 0
  fi
  rm -f "$@"
}

_rm_rf() {
  if [[ $DRY_RUN -eq 1 ]]; then
    echo "  [dry-run] rm -rf $*"
    return 0
  fi
  rm -rf "$@"
}

_apt_purge() {
  if [[ $DRY_RUN -eq 1 ]]; then
    echo "  [dry-run] apt-get purge -y $*"
    return 0
  fi
  apt-get purge -y "$@" 2>/dev/null || true
  apt-get autoremove -y 2>/dev/null || true
}

_stop_disable() {
  local unit="$1"
  if systemctl is-enabled "$unit" >/dev/null 2>&1 || systemctl is-active "$unit" >/dev/null 2>&1; then
    if [[ $DRY_RUN -eq 0 ]]; then
      systemctl stop "$unit" 2>/dev/null || true
      systemctl disable "$unit" 2>/dev/null || true
    else
      echo "  [dry-run] systemctl stop/disable $unit"
    fi
  fi
}

# Run a phase only if no --phase filter was given, or it matches.
should_run() {
  local num="$1"
  [[ -z "$TARGET_PHASE" || "$TARGET_PHASE" == "$num" ]]
}

# ===========================================================================
# PHASE 1: Stop anvil services + Docker containers
# ===========================================================================
phase_1() {
  phase 1 "Stop anvil services + Docker containers"

  # --- 1a. v3 systemd trio ---
  local -a trio_units=(
    anvil-caddy.service
    anvil-tengine.service
    "anvil-frankenphp@blue.service"
    "anvil-frankenphp@green.service"
    anvil-secrets.service
  )
  for unit in "${trio_units[@]}"; do
    if [[ -f "/etc/systemd/system/${unit}" ]]; then
      step "Stop + disable ${unit}"
      _stop_disable "$unit"
      ok
    fi
  done

  # --- 1b. v1/v2 Docker containers (from docker-compose.local / docker-compose.ec2) ---
  # --- 1b. Docker cleanup (only if docker is installed) ---
  if ! command -v docker >/dev/null 2>&1; then
    step "Docker not installed — skipping container cleanup"
    skip
  else

    # --- 1b. v1/v2 Docker containers ---
      local -a docker_containers=(anvil_nginx anvil_php anvil_mysql anvil_phpmyadmin anvil_redis)
  for ctr in "${docker_containers[@]}"; do
    if docker ps -a --format '{{.Names}}' 2>/dev/null | grep -q "^${ctr}$"; then
      step "Remove container ${ctr}"
      if [[ $DRY_RUN -eq 0 ]]; then
        docker rm -f "$ctr" 2>/dev/null || true
      else
        echo "[dry-run] docker rm -f $ctr"
      fi
      ok
    fi
  done

  # --- 1c. Docker volumes from anvil stacks ---
  local -a docker_volumes=(mysql_data anvil_mysql anvil_redis)
  for vol in "${docker_volumes[@]}"; do
    if docker volume ls --format '{{.Name}}' 2>/dev/null | grep -q "^${vol}$"; then
      step "Remove volume ${vol}"
      if [[ $DRY_RUN -eq 0 ]]; then
        docker volume rm "$vol" 2>/dev/null || true
      else
        echo "[dry-run] docker volume rm $vol"
      fi
      ok
    fi
  done

  # --- 1d. anvil-web PID (built-in PHP server from web.sh) ---
  if [[ -f "${ANVIL_ROOT_MIGRATE}/.anvil-web.pid" ]]; then
    step "Kill anvil-web built-in server"
    if [[ $DRY_RUN -eq 0 ]]; then
      local pid
      pid="$(cat "${ANVIL_ROOT_MIGRATE}/.anvil-web.pid" 2>/dev/null || true)"
      if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
        kill "$pid" 2>/dev/null || true
      fi
      rm -f "${ANVIL_ROOT_MIGRATE}/.anvil-web.pid" "${ANVIL_ROOT_MIGRATE}/.anvil-web.log"
    else
      echo "[dry-run] kill anvil-web, rm .anvil-web.pid/.log"
    fi
    ok
  fi
}

# ===========================================================================
# PHASE 2: Remove anvil binaries + system packages
# ===========================================================================
phase_2() {
  phase 2 "Remove anvil binaries + system packages"

  # --- 2a. Anvil-installed pinned binaries (install-trio.sh) ---
  local -a anvil_bins=(
    "/usr/local/bin/caddy"
    "/usr/local/bin/frankenphp"
    "/usr/local/tengine"
  )
  for bin in "${anvil_bins[@]}"; do
    if [[ -e "$bin" ]]; then
      step "Remove ${bin}"
      _rm_rf "$bin"
      ok
    fi
  done

  # --- 2b. Anvil Phase 1 binaries (install.sh) ---
  local mkcert_path="${ANVIL_MKCERT_PATH:-/usr/local/bin/mkcert}"
  local sass_path="${ANVIL_SASS_PATH:-/usr/local/bin/sass}"

  if [[ -x "$mkcert_path" ]]; then
    step "Remove mkcert (${mkcert_path})"
    _rm "$mkcert_path"
    ok
  fi

  if [[ -x "$sass_path" ]]; then
    step "Remove dart-sass (${sass_path})"
    _rm "$sass_path"
    ok
  fi

  # Also clean up any sass extracted directory that may linger
  if [[ -d "/usr/local/bin/dart-sass" ]]; then
    step "Remove dart-sass directory /usr/local/bin/dart-sass"
    _rm_rf "/usr/local/bin/dart-sass"
    ok
  fi

  # --- 2c. System packages installed by install.sh ---
  step "Purge dnsmasq + inotify-tools (apt)"
  _apt_purge dnsmasq inotify-tools
  ok

  # Docker packages -- keep Docker Engine itself (other workflows may depend on it).
  # Only remove the anvil-specific Docker apt source list and keyring.
  step "Remove anvil Docker apt source list"
  _rm "/etc/apt/sources.list.d/docker.list"
  _rm "/etc/apt/keyrings/docker.asc"
  ok

  step "apt-get update (clean removed sources)"
  if [[ $DRY_RUN -eq 0 ]]; then
    apt-get update -qq 2>/dev/null || true
  fi
  ok
}

# ===========================================================================
# PHASE 3: Remove anvil configs, runtime dirs, systemd units
# ===========================================================================
phase_3() {
  phase 3 "Remove anvil configs, runtime dirs, systemd units"

  # --- 3a. Systemd units ---
  local -a systemd_units=(
    anvil-caddy.service
    anvil-tengine.service
    anvil-frankenphp@.service
    anvil-secrets.service
  )
  for unit in "${systemd_units[@]}"; do
    if [[ -f "/etc/systemd/system/${unit}" ]]; then
      step "Remove unit ${unit}"
      if [[ $DRY_RUN -eq 0 ]]; then
        rm -f "/etc/systemd/system/${unit}"
      else
        echo "[dry-run] rm /etc/systemd/system/${unit}"
      fi
      ok
    fi
  done

  step "systemctl daemon-reload"
  if [[ $DRY_RUN -eq 0 ]]; then
    systemctl daemon-reload 2>/dev/null || true
  fi
  ok

  # --- 3b. Config files ---
  local -a config_paths=(
    "/etc/anvil"
    "/etc/dnsmasq.d/anvil.conf"
    "/etc/systemd/resolved.conf.d/anvil.conf"
    "/etc/sysctl.d/99-anvil.conf"
    "/etc/security/limits.d/99-anvil.conf"
  )
  for path in "${config_paths[@]}"; do
    if [[ -e "$path" ]]; then
      step "Remove ${path}"
      _rm_rf "$path"
      ok
    fi
  done

  # --- 3c. Runtime / data directories ---
  local -a runtime_dirs=(
    "/opt/anvil/bin"
    "/opt/anvil/releases"
    "/opt/anvil/current"
    "/opt/anvil/current-green"
    "/var/log/anvil"
    "/var/lib/anvil"
    "/run/anvil"
  )
  for dir in "${runtime_dirs[@]}"; do
    if [[ -e "$dir" ]]; then
      step "Remove ${dir}"
      _rm_rf "$dir"
      ok
    fi
  done

  # Clean up /opt/anvil itself if empty
  if [[ -d "/opt/anvil" ]]; then
    step "Remove /opt/anvil (if empty)"
    if [[ $DRY_RUN -eq 0 ]]; then
      # Only remove if it's not the anvil source repo
  # Remove anvil-installed subdirs first, regardless of git status
  _rm_rf "/opt/anvil/bin" "/opt/anvil/releases" "/opt/anvil/current" "/opt/anvil/current-green"

      if ! git -C /opt/anvil rev-parse --git-dir >/dev/null 2>&1; then
        rmdir /opt/anvil 2>/dev/null || warn "/opt/anvil not empty -- left in place"
      else
        warn "/opt/anvil is a git repo -- skipping"
      fi
    else
      echo "[dry-run] rmdir /opt/anvil (if not git repo)"
    fi
    ok
  fi
}

# ===========================================================================
# PHASE 4: Remove conflicting servers
# ===========================================================================
phase_4() {
  phase 4 "Remove conflicting servers (Apache, Nginx, PHP-FPM, Caddy, FrankenPHP, Tengine, Certbot)"

  # -- Apache / httpd --
  if command -v apachectl >/dev/null 2>&1 || command -v httpd >/dev/null 2>&1 \
     || dpkg -l apache2 2>/dev/null | grep -q '^ii' \
     || rpm -q httpd 2>/dev/null; then
    step "Purge Apache (apache2 / httpd)"
    if [[ $DRY_RUN -eq 0 ]]; then
      systemctl stop apache2 2>/dev/null || true
      systemctl stop httpd 2>/dev/null || true
      systemctl disable apache2 2>/dev/null || true
      systemctl disable httpd 2>/dev/null || true
    else
      echo "[dry-run] systemctl stop/disable apache2/httpd"
    fi
    _apt_purge apache2 apache2-bin apache2-utils apache2-data \
               libapache2-mod-php* 2>/dev/null || true
    # RPM path (Amazon Linux / RHEL / Fedora)
    if command -v rpm >/dev/null 2>&1; then
      if [[ $DRY_RUN -eq 0 ]]; then
        if command -v dnf >/dev/null 2>&1; then
          dnf remove -y httpd httpd-tools 2>/dev/null || true
        elif command -v yum >/dev/null 2>&1; then
          yum remove -y httpd httpd-tools 2>/dev/null || true
        else
          rpm -e httpd httpd-tools 2>/dev/null || true
        fi
      else
        echo "[dry-run] rpm -e httpd httpd-tools"
      fi
    fi
    _rm_rf "/etc/apache2" "/etc/httpd" 2>/dev/null || true
    ok
  else
    step "Apache"; skip
  fi

  # -- Nginx (system package, NOT the Docker container) --
  if command -v nginx >/dev/null 2>&1 \
     || dpkg -l nginx nginx-common nginx-full 2>/dev/null | grep -q '^ii' \
     || rpm -q nginx 2>/dev/null; then
    step "Stop + purge Nginx (system package)"
    if [[ $DRY_RUN -eq 0 ]]; then
      systemctl stop nginx 2>/dev/null || true
      systemctl disable nginx 2>/dev/null || true
    else
      echo "[dry-run] systemctl stop/disable nginx"
    fi
    _apt_purge nginx nginx-common nginx-full 2>/dev/null || true
    if command -v rpm >/dev/null 2>&1; then
      if [[ $DRY_RUN -eq 0 ]]; then
        if command -v dnf >/dev/null 2>&1; then
          dnf remove -y nginx 2>/dev/null || true
        elif command -v yum >/dev/null 2>&1; then
          yum remove -y nginx 2>/dev/null || true
        else
          rpm -e nginx 2>/dev/null || true
        fi
      else
        echo "[dry-run] rpm -e nginx"
      fi
    fi
    # Remove leftover nginx config dirs that aren't from Docker
    _rm_rf "/etc/nginx" 2>/dev/null || true
    ok
  else
    step "Nginx"; skip
  fi

  # -- PHP-FPM (system package) --
  # Detect any php-fpm services packages installed via apt/rpm
  local -a php_fpm_pkgs=()
  if command -v dpkg >/dev/null 2>&1; then
    while IFS= read -r pkg; do
      [[ -n "$pkg" ]] && php_fpm_pkgs+=("$pkg")
    done < <(dpkg -l 2>/dev/null | awk '/^ii.*php[0-9.]*-fpm/ {print $2}')
  fi
  if command -v rpm >/dev/null 2>&1; then
    while IFS= read -r pkg; do
      [[ -n "$pkg" ]] && php_fpm_pkgs+=("$pkg")
    done < <(rpm -qa 2>/dev/null | grep 'php.*fpm')
  fi

  if [[ ${#php_fpm_pkgs[@]} -gt 0 ]]; then
    step "Stop + purge PHP-FPM packages (${php_fpm_pkgs[*]})"
    if [[ $DRY_RUN -eq 0 ]]; then
      # Stop any php-fpm service variants
      # Stop all php-fpm service variants (systemctl does not glob)
      for php_unit in $(systemctl list-units --type=service --no-pager --no-legend 2>/dev/null | awk "/php.*fpm/ {print \$1}"); do
        systemctl stop "$php_unit" 2>/dev/null || true
        systemctl disable "$php_unit" 2>/dev/null || true
      done
    else
      echo "[dry-run] systemctl stop/disable php-fpm services"
    fi
    _apt_purge "${php_fpm_pkgs[@]}" 2>/dev/null || true
    # Also try rpm removal for each
    if command -v rpm >/dev/null 2>&1; then
      for pkg in "${php_fpm_pkgs[@]}"; do
        if [[ $DRY_RUN -eq 0 ]]; then
          if command -v dnf >/dev/null 2>&1; then
            dnf remove -y "$pkg" 2>/dev/null || true
          elif command -v yum >/dev/null 2>&1; then
            yum remove -y "$pkg" 2>/dev/null || true
          else
            rpm -e "$pkg" 2>/dev/null || true
          fi
        else
          echo "[dry-run] rpm -e $pkg"
        fi
      done
    fi
    ok
  else
    step "PHP-FPM"; skip
  fi

  # -- Caddy (system package, if installed independently of anvil) --
  # Anvil installs to /usr/local/bin/caddy. System packages go elsewhere.
  if dpkg -l caddy 2>/dev/null | grep -q '^ii' \
     || rpm -q caddy 2>/dev/null; then
    step "Purge Caddy system package"
    if [[ $DRY_RUN -eq 0 ]]; then
      systemctl stop caddy 2>/dev/null || true
      systemctl disable caddy 2>/dev/null || true
    fi
    _apt_purge caddy 2>/dev/null || true
    if command -v rpm >/dev/null 2>&1; then
      if [[ $DRY_RUN -eq 0 ]]; then
        if command -v dnf >/dev/null 2>&1; then
          dnf remove -y caddy 2>/dev/null || true
        elif command -v yum >/dev/null 2>&1; then
          yum remove -y caddy 2>/dev/null || true
        else
          rpm -e caddy 2>/dev/null || true
        fi
      else
        echo "[dry-run] rpm -e caddy"
      fi
    fi
    ok
  else
    step "Caddy package"; skip
  fi

  # -- FrankenPHP (if installed via package manager independently) --
  if dpkg -l frankenphp 2>/dev/null | grep -q '^ii' \
     || rpm -q frankenphp 2>/dev/null; then
    step "Purge FrankenPHP system package"
    _apt_purge frankenphp 2>/dev/null || true
    if command -v rpm >/dev/null 2>&1; then
      if [[ $DRY_RUN -eq 0 ]]; then
        if command -v dnf >/dev/null 2>&1; then
          dnf remove -y frankenphp 2>/dev/null || true
        elif command -v yum >/dev/null 2>&1; then
          yum remove -y frankenphp 2>/dev/null || true
        else
          rpm -e frankenphp 2>/dev/null || true
        fi
      else
        echo "[dry-run] rpm -e frankenphp"
      fi
    fi
    ok
  else
    step "FrankenPHP package"; skip
  fi

  # -- Tengine / OpenResty (source install detection) --
  for srv in tengine openresty; do
    local srv_root="/usr/local/${srv}"
    if [[ -d "$srv_root" ]]; then
      step "Remove ${srv} source install (${srv_root})"
      _rm_rf "$srv_root"
      ok
    fi
  done

  # Also check for tengine/openresty system packages
  for pkg in tengine openresty; do
    if dpkg -l "$pkg" 2>/dev/null | grep -q '^ii' \
       || rpm -q "$pkg" 2>/dev/null; then
      step "Purge ${pkg} system package"
      _apt_purge "$pkg" 2>/dev/null || true
      if command -v rpm >/dev/null 2>&1; then
        if [[ $DRY_RUN -eq 0 ]]; then
          if command -v dnf >/dev/null 2>&1; then
            dnf remove -y "$pkg" 2>/dev/null || true
          elif command -v yum >/dev/null 2>&1; then
            yum remove -y "$pkg" 2>/dev/null || true
          else
            rpm -e "$pkg" 2>/dev/null || true
          fi
        else
          echo "[dry-run] rpm -e $pkg"
        fi
      fi
      ok
    fi
  done

  # -- Certbot (superseded by Caddy ACME) --
  if command -v certbot >/dev/null 2>&1 \
     || dpkg -l certbot 2>/dev/null | grep -q '^ii' \
     || rpm -q certbot 2>/dev/null; then
    step "Purge Certbot (superseded by Caddy ACME)"
    # Stop any certbot timers
    if [[ $DRY_RUN -eq 0 ]]; then
      systemctl stop certbot.timer 2>/dev/null || true
      systemctl disable certbot.timer 2>/dev/null || true
    fi
    _apt_purge certbot python3-certbot python3-certbot-nginx python3-certbot-apache 2>/dev/null || true
    if command -v rpm >/dev/null 2>&1; then
      if [[ $DRY_RUN -eq 0 ]]; then
        if command -v dnf >/dev/null 2>&1; then
          dnf remove -y certbot python3-certbot python3-certbot-nginx python3-certbot-apache 2>/dev/null || true
        elif command -v yum >/dev/null 2>&1; then
          yum remove -y certbot python3-certbot python3-certbot-nginx python3-certbot-apache 2>/dev/null || true
        else
          rpm -e certbot python3-certbot python3-certbot-nginx python3-certbot-apache 2>/dev/null || true
        fi
      else
        echo "[dry-run] rpm -e certbot + python3-certbot-*"
      fi
    fi
    _rm_rf "/etc/letsencrypt" 2>/dev/null || true
    ok
  else
    step "Certbot"; skip
  fi

  # -- Miscellaneous PHP CLI packages that may conflict --
  # Only remove the CLI SAPI if it was installed as a system package
  # (not from source). Keep php-common/php-mbstring/etc -- they don't conflict.
  local -a php_cli_pkgs=()
  if command -v dpkg >/dev/null 2>&1; then
    while IFS= read -r pkg; do
      [[ -n "$pkg" ]] && php_cli_pkgs+=("$pkg")
    done < <(dpkg -l 2>/dev/null | awk '/^ii.*(php[0-9.]*-cli|php-cli) / {print $2}')
  fi
  if [[ ${#php_cli_pkgs[@]} -gt 0 ]]; then
    step "Purge PHP-CLI system packages (${php_cli_pkgs[*]})"
    _apt_purge "${php_cli_pkgs[@]}" 2>/dev/null || true
    ok
  else
    step "PHP-CLI packages"; skip
  fi

  # -- Leftover php-fpm runtime artifacts --
  step "Clean up PHP-FPM runtime artifacts"
  if [[ $DRY_RUN -eq 0 ]]; then
    rm -rf /run/php /var/run/php-fpm /tmp/php-fpm-* 2>/dev/null || true
  else
    echo "[dry-run] rm -rf /run/php /var/run/php-fpm /tmp/php-fpm-*"
  fi
  ok
}

# ===========================================================================
# PHASE 5: Remove anvil system users
# ===========================================================================
phase_5() {
  phase 5 "Remove anvil system users"

  local -a users=(caddy tengine anvil)
  for u in "${users[@]}"; do
    if id -u "$u" >/dev/null 2>&1; then
      step "Remove system user '${u}'"
      if [[ $DRY_RUN -eq 0 ]]; then
        # Kill any processes owned by this user first
        pkill -u "$u" 2>/dev/null || true
        sleep 1
        # Force-remove user + home + mail spool
        userdel --force --remove "$u" 2>/dev/null || true
      else
        echo "[dry-run] userdel $u"
      fi
      ok
    fi
  done

  # Also remove groups in case they linger (e.g. if userdel didn't remove them)
  for g in "${users[@]}"; do
    if getent group "$g" >/dev/null 2>&1; then
      step "Remove system group '${g}'"
      if [[ $DRY_RUN -eq 0 ]]; then
        groupdel "$g" 2>/dev/null || true
      else
        echo "[dry-run] groupdel $g"
      fi
      ok
    fi
  done
}

# ===========================================================================
# PHASE 6: Restore DNS / cleanup firewall + sysctl
# ===========================================================================
phase_6() {
  phase 6 "Restore DNS / cleanup firewall + sysctl"

  # --- 6a. Restore /etc/resolv.conf ---
  # Anvil replaced the systemd-resolved symlink with a static file pointing
  # at 127.0.0.1 (dnsmasq). Restore the symlink if systemd-resolved is running.
  step "Restore /etc/resolv.conf"
  if [[ $DRY_RUN -eq 0 ]]; then
    if systemctl is-active systemd-resolved >/dev/null 2>&1; then
      # Re-enable the stub listener
      mkdir -p /etc/systemd/resolved.conf.d
      cat >/etc/systemd/resolved.conf.d/anvil-restore.conf <<'RESTORE_EOF'
[Resolve]
DNSStubListener=yes
RESTORE_EOF
      systemctl restart systemd-resolved || warn "failed to restart systemd-resolved"

      # Restore the symlink
      if [[ ! -L /etc/resolv.conf ]]; then
        mv /etc/resolv.conf /etc/resolv.conf.anvil-backup 2>/dev/null || true
        ln -sf /run/systemd/resolve/resolv.conf /etc/resolv.conf
        info "  /etc/resolv.conf restored to systemd-resolved stub (old file backed up as .anvil-backup)"
      fi
    else
      warn "  systemd-resolved not active -- leaving /etc/resolv.conf as-is (manual fix may be needed)"
      warn "  If DNS is broken, run: ln -sf /run/systemd/resolve/resolv.conf /etc/resolv.conf && systemctl restart systemd-resolved"
    fi
  else
    echo "[dry-run] restore /etc/resolv.conf symlink"
  fi
  ok

  # --- 6b. Firewall: remove anvil nftables ruleset if present ---
  if [[ -f "/etc/nftables.conf" ]] && grep -q 'table inet anvil' /etc/nftables.conf 2>/dev/null; then
    step "Remove anvil nftables ruleset"
    if [[ $DRY_RUN -eq 0 ]]; then
      # Check if there's a distro default to restore
      if [[ -f /etc/nftables.conf.dpkg-dist ]]; then
        mv /etc/nftables.conf.dpkg-dist /etc/nftables.conf
      else
        # Write a minimal safe ruleset
        cat >/etc/nftables.conf <<'EOF'
#!/usr/sbin/nft -f
# Minimal ruleset (anvil rules removed by migrate.sh)
flush ruleset
table inet filter {
    chain input { type filter hook input priority 0; policy accept; }
    chain forward { type filter hook forward priority 0; policy accept; }
    chain output { type filter hook output priority 0; policy accept; }
}
EOF
      fi
      systemctl reload nftables 2>/dev/null || systemctl restart nftables 2>/dev/null || true
    else
      echo "[dry-run] restore /etc/nftables.conf"
    fi
    ok
  else
    step "nftables"; skip
  fi

  # --- 6c. UFW: remove anvil rules (but don't disable ufw entirely) ---
  if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -qi "active"; then
    step "Remove anvil ufw rules (rules with 'anvil' comment)"
    if [[ $DRY_RUN -eq 0 ]]; then
      # Only remove rules that have the 'anvil' comment to avoid breaking user-added rules
    step "Remove anvil ufw rules"
    if [[ $DRY_RUN -eq 0 ]]; then
      # Delete by rule text (more robust than numbered deletion)
      ufw status 2>/dev/null | grep -i "anvil" | sed 's/^\[.*\] //' | while read -r rule; do
        [[ -n "$rule" ]] && ufw delete "$rule" 2>/dev/null || true
      done
    else
      echo "[dry-run] ufw delete anvil-commented rules"
    fi
    else
      echo "[dry-run] ufw delete anvil-commented rules"
    fi
    ok
  else
    step "ufw"; skip
  fi

  # --- 6d. mkcert local CA removal ---
  step "Remove mkcert local CA"
  if [[ $DRY_RUN -eq 0 ]]; then
    # System-level nssdb
    # NOTE: /etc/pki/nssdb is the system NSS database.
    # mkcert installs its CA to ~/.local/share/mkcert/ (handled above).
    # We do NOT touch /etc/pki/nssdb to avoid breaking system certs.
    # User-level (run for all human users with UID >= 1000)
    while IFS=: read -r _ uname _ uid _ _ home _; do
      if [[ "$uid" -ge 1000 && -d "$home" ]]; then
        rm -f "${home}/.local/share/mkcert/rootCA.pem" 2>/dev/null || true
        rm -rf "${home}/.local/share/mkcert" 2>/dev/null || true
      fi
    done < /etc/passwd
  else
    echo "[dry-run] remove mkcert CA from system + user stores"
  fi
  ok
}

# ===========================================================================
# MAIN
# ===========================================================================
main() {
  echo
  printf "${BOLD}${CYAN}  Anvil Migration Script${RST}\n"
  printf "${BOLD}${CYAN}  Uninstall old anvil + conflicting servers${RST}\n"
  echo "  $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
  echo

  if should_run 1; then phase_1; fi
  if should_run 2; then phase_2; fi
  if should_run 3; then phase_3; fi
  if should_run 4; then phase_4; fi
  if should_run 5; then phase_5; fi
  if should_run 6; then phase_6; fi

  echo
  if [[ $DRY_RUN -eq 1 ]]; then
    printf "${YELLOW}DRY-RUN COMPLETE -- no changes were made.${RST}\n"
    echo "Remove --dry-run to execute."
  else
    printf "${GREEN}Migration complete.${RST}\n"
    echo
    echo "Your system is now clean for a fresh anvil install."
    echo "Run the new installers:"
    echo "  sudo ./install.sh           # Phase 1: Docker + dnsmasq + mkcert + sass"
    echo "  sudo ./install-trio.sh      # Phase 3: Caddy + Tengine + FrankenPHP"
    echo
  fi
}

main "$@"