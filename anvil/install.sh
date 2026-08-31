#!/usr/bin/env bash
# anvil/install.sh — Unified Anvil interactive installer
#
# Presents a whiptail/dialog menu and delegates to lib/ scripts.
# This is the ONLY user-facing install entry point.
#
# Usage:
#   sudo ./install.sh [dev|trio|remove] [--yes|--noninteractive] [--help]

set -euo pipefail

ANVIL_ROOT="$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")"
export ANVIL_ROOT

# shellcheck source=lib/core.sh
source "${ANVIL_ROOT}/lib/core.sh"

# ---------------------------------------------------------------------------
# Parse command-line arguments.
# ---------------------------------------------------------------------------
MODE=""
NONINTERACTIVE=0
for arg in "$@"; do
  case "$arg" in
    dev|trio|remove)
      MODE="$arg"
      ;;
    --yes|--noninteractive)
      NONINTERACTIVE=1
      ;;
    -h|--help)
      cat <<'EOF'
Anvil Unified Installer — Caddy + Tengine + FrankenPHP

Usage:
  sudo ./install.sh [dev|trio|remove] [--yes|--noninteractive] [--help]

Modes:
  dev       Development stack (Docker, dnsmasq, mkcert, dart-sass)
  trio      Production trio (Caddy, Tengine, FrankenPHP)
  remove    Uninstall all Anvil components

Options:
  --yes, --noninteractive   Run without interactive prompts.
  -h, --help                Show this help and exit.

The installer is idempotent and safe to re-run.
EOF
      exit 0
      ;;
    *)
      echo "Unknown option: $arg" >&2
      exit 1
      ;;
  esac
done

# ---------------------------------------------------------------------------
# Non-interactive direct mode.
# ---------------------------------------------------------------------------
if [[ -n "$MODE" ]]; then
  case "$MODE" in
    dev)
      bash "${ANVIL_ROOT}/lib/install-dev.sh" "$@"
      ;;
    trio)
      bash "${ANVIL_ROOT}/lib/install-trio.sh" "$@"
      ;;
    remove)
      bash "${ANVIL_ROOT}/uninstall.sh" "$@"
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

CHOICE=$(whiptail --title "Anvil Installer" --menu "Choose installation path:" 15 60 4 \
    "dev"     "Development stack (Docker, dnsmasq, mkcert, dart-sass)" \
    "trio"    "Production trio (Caddy, Tengine, FrankenPHP)" \
    "remove"  "Uninstall Anvil" \
    "exit"    "Exit" 3>&1 1>&2 2>&3) || true

case "${CHOICE:-exit}" in
    dev)
        bash "${ANVIL_ROOT}/lib/install-dev.sh" "$@"
        ;;
    trio)
        bash "${ANVIL_ROOT}/lib/install-trio.sh" "$@"
        ;;
    remove)
        bash "${ANVIL_ROOT}/uninstall.sh" "$@"
        ;;
    *)
        exit 0
        ;;
esac
