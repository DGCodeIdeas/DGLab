#!/usr/bin/env bash
# anvil/install.sh — Unified Anvil interactive installer
#
# Three entrypoints exist in the entire project:
#   1. install.sh   — this file (interactive menu + CLI flags)
#   2. anvilctl     — CLI dispatcher for scripted/alias use
#   3. uninstall.sh — clean removal of anvil + services
#
# Everything else under lib/ is non-runnable library modules.
#
# Usage:
#   sudo ./install.sh                         # interactive menu
#   sudo ./install.sh --bootstrap             # dev stack only
#   sudo ./install.sh --trio [--env prod]     # production trio
#   sudo ./install.sh --full [--env prod]     # bootstrap + trio
#   sudo ./install.sh --uninstall             # run uninstaller
#   sudo ./install.sh --doctor                # health checks
#   sudo ./install.sh --yes                   # non-interactive (defaults to --full)
#   sudo ./install.sh <anvilctl command>      # any anvilctl subcommand

set -euo pipefail

# ---------------------------------------------------------------------------
# Root-privilege check — most operations need root (apt-get, systemctl, etc.)
# ---------------------------------------------------------------------------
if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
  echo "[INFO] This script must run as root. Re-executing with sudo..." >&2
  exec sudo "$0" "$@"
fi

ANVIL_ROOT="$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")"
export ANVIL_ROOT

# shellcheck source=lib/core.sh
source "${ANVIL_ROOT}/lib/core.sh"

# ---------------------------------------------------------------------------
# Parse arguments — install-specific flags first, then fall through to anvilctl.
#
# Uses a while+shift loop (NOT for-in) because shift must modify $@ live so
# that (a) --env's value is consumed and not re-iterated, and (b) the
# remaining $@ is correctly passed through to anvilctl in delegate mode.
# ---------------------------------------------------------------------------
MODE="menu"
NONINTERACTIVE=0
ANVIL_INSTALL_ENV="production"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --bootstrap)      MODE="bootstrap"; shift ;;
    --trio)           MODE="trio"; shift ;;
    --full)           MODE="full"; shift ;;
    --uninstall)      MODE="uninstall"; shift ;;
    --doctor)         MODE="doctor"; shift ;;
    --menu)           MODE="menu"; shift ;;
    --yes|--noninteractive) NONINTERACTIVE=1; shift ;;
    --env)
      if [[ $# -lt 2 ]]; then
        echo "ERROR: --env requires a value (e.g. --env production)" >&2
        exit 2
      fi
      ANVIL_INSTALL_ENV="$2"
      shift 2
      ;;
    --env=*)
      ANVIL_INSTALL_ENV="${1#--env=}"
      shift
      ;;
    -h|--help)
      cat <<'EOF'
Anvil — unified interactive entrypoint (Caddy + Tengine + FrankenPHP)

Interactive:  sudo ./install.sh

Install shortcuts:
  sudo ./install.sh --bootstrap            Docker, dnsmasq, mkcert, dart-sass
  sudo ./install.sh --trio [--env prod]    Caddy + Tengine + FrankenPHP
  sudo ./install.sh --full [--env prod]    Bootstrap + Trio
  sudo ./install.sh --uninstall            Remove anvil + services
  sudo ./install.sh --doctor               Health checks
  sudo ./install.sh --yes                  Non-interactive (defaults to --full --env production)

Runtime (delegates to anvilctl):
  sudo ./install.sh start                  Start the active stack
  sudo ./install.sh stop                   Stop the active stack
  sudo ./install.sh status                 Show stack status
  sudo ./install.sh restart <svc>          Restart one service
  sudo ./install.sh deploy <env>           Deploy to environment
  sudo ./install.sh logs [svc]             Tail service logs
  ... any anvilctl subcommand works
EOF
      exit 0 ;;
    *)
      MODE="delegate"
      break
      ;;
  esac
done

# ---------------------------------------------------------------------------
# Non-interactive mode: --yes without an explicit mode defaults to --full.
# ---------------------------------------------------------------------------
if [[ "$NONINTERACTIVE" -eq 1 && "$MODE" == "menu" ]]; then
  MODE="full"
fi

# ---------------------------------------------------------------------------
# Non-interactive direct mode.
# ---------------------------------------------------------------------------
if [[ "$MODE" != "menu" ]]; then
  case "$MODE" in
    bootstrap)
      bash "${ANVIL_ROOT}/lib/install-dev.sh" "$@"
      ;;
    trio)
      bash "${ANVIL_ROOT}/lib/install-trio.sh" --env "$ANVIL_INSTALL_ENV" "$@"
      ;;
    full)
      bash "${ANVIL_ROOT}/lib/install-dev.sh" "$@"
      bash "${ANVIL_ROOT}/lib/install-trio.sh" --env "$ANVIL_INSTALL_ENV" "$@"
      ;;
    uninstall)
      bash "${ANVIL_ROOT}/uninstall.sh" "$@"
      ;;
    doctor)
      bash "${ANVIL_ROOT}/anvilctl" doctor "$@"
      ;;
    delegate)
      bash "${ANVIL_ROOT}/anvilctl" "$@"
      ;;
  esac
  exit $?
fi

# ---------------------------------------------------------------------------
# Interactive menu.
# ---------------------------------------------------------------------------
if ! command -v whiptail &>/dev/null && ! command -v dialog &>/dev/null; then
    anvil_info "Installing whiptail..."
    apt-get update -qq && apt-get install -y -qq whiptail
fi

while true; do
  CHOICE=$(whiptail --title "Anvil Installer" --menu "Choose operation:" 20 70 12 \
      "install-dev"     "Install development stack (Docker, dnsmasq, mkcert)" \
      "install-trio"    "Install production trio (Caddy, Tengine, FrankenPHP)" \
      "install-full"    "Full install (dev + trio)" \
      "" "" \
      "start"           "Start all services" \
      "stop"            "Stop all services" \
      "restart"         "Restart services" \
      "status"          "View service status" \
      "logs"            "View service logs" \
      "top"             "View resource usage (top)" \
      "" "" \
      "deploy"          "Deploy to environment" \
      "smoke"           "Run smoke tests" \
      "doctor"          "Run health checks" \
      "" "" \
      "uninstall"       "Uninstall Anvil" \
      "exit"            "Exit" 3>&1 1>&2 2>&3) || true

  case "${CHOICE:-exit}" in
      install-dev)
          bash "${ANVIL_ROOT}/lib/install-dev.sh"
          read -rp "Press Enter to continue..." || true
          ;;
      install-trio)
          ENV=$(whiptail --inputbox "Environment (development/staging/production):" 8 50 "production" 3>&1 1>&2 2>&3) || true
          bash "${ANVIL_ROOT}/lib/install-trio.sh" --env "${ENV:-production}"
          read -rp "Press Enter to continue..." || true
          ;;
      install-full)
          ENV=$(whiptail --inputbox "Environment (development/staging/production):" 8 50 "production" 3>&1 1>&2 2>&3) || true
          bash "${ANVIL_ROOT}/lib/install-dev.sh"
          bash "${ANVIL_ROOT}/lib/install-trio.sh" --env "${ENV:-production}"
          read -rp "Press Enter to continue..." || true
          ;;
      start)
          bash "${ANVIL_ROOT}/anvilctl" start
          read -rp "Press Enter to continue..." || true
          ;;
      stop)
          bash "${ANVIL_ROOT}/anvilctl" stop
          read -rp "Press Enter to continue..." || true
          ;;
      restart)
          SVC=$(whiptail --inputbox "Service to restart (caddy/tengine/frankenphp/all):" 8 50 "all" 3>&1 1>&2 2>&3) || true
          bash "${ANVIL_ROOT}/anvilctl" restart "${SVC:-all}"
          read -rp "Press Enter to continue..." || true
          ;;
      status)
          bash "${ANVIL_ROOT}/anvilctl" status
          read -rp "Press Enter to continue..." || true
          ;;
      logs)
          SVC=$(whiptail --inputbox "Service logs (caddy/tengine/frankenphp):" 8 50 "frankenphp" 3>&1 1>&2 2>&3) || true
          bash "${ANVIL_ROOT}/anvilctl" logs "${SVC:-frankenphp}"
          read -rp "Press Enter to continue..." || true
          ;;
      top)
          bash "${ANVIL_ROOT}/anvilctl" top
          read -rp "Press Enter to continue..." || true
          ;;
      deploy)
          ENV=$(whiptail --inputbox "Deploy to (staging/production):" 8 50 "staging" 3>&1 1>&2 2>&3) || true
          bash "${ANVIL_ROOT}/anvilctl" deploy "${ENV:-staging}"
          read -rp "Press Enter to continue..." || true
          ;;
      smoke)
          bash "${ANVIL_ROOT}/lib/deploy-smoke.sh"
          read -rp "Press Enter to continue..." || true
          ;;
      doctor)
          bash "${ANVIL_ROOT}/anvilctl" doctor
          read -rp "Press Enter to continue..." || true
          ;;
      uninstall)
          bash "${ANVIL_ROOT}/uninstall.sh"
          break
          ;;
      *)
          break
          ;;
  esac
done
