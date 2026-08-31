#!/usr/bin/env bash
# anvil/uninstall.sh — Remove all Anvil-installed components
#
# Stops services, removes systemd units, deletes binaries and runtime dirs.
# Safe to re-run; idempotent.
#
# Usage:
#   sudo ./uninstall.sh [--yes|--noninteractive]

set -euo pipefail

ANVIL_ROOT="$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")"
export ANVIL_ROOT

# shellcheck source=lib/core.sh
source "${ANVIL_ROOT}/lib/core.sh"

# ---------------------------------------------------------------------------
# Non-interactive guard.
# ---------------------------------------------------------------------------
if [[ "${1:-}" != "--yes" && "${1:-}" != "--noninteractive" ]]; then
    read -rp "This will remove all Anvil services, binaries, and configuration. Continue? [y/N] " confirm
    [[ "$confirm" =~ ^[Yy]$ ]] || { anvil_info "Aborted."; exit 0; }
fi

anvil_info "Stopping Anvil services..."
systemctl stop anvil-caddy anvil-tengine 'anvil-frankenphp@*' anvil-secrets 2>/dev/null || true

anvil_info "Disabling Anvil services..."
systemctl disable anvil-caddy anvil-tengine 'anvil-frankenphp@*' anvil-secrets 2>/dev/null || true

anvil_info "Removing systemd units..."
rm -f /etc/systemd/system/anvil-*.service /etc/systemd/system/anvil-*.timer

anvil_info "Removing binaries..."
rm -f /usr/local/bin/caddy /usr/local/bin/frankenphp /usr/local/bin/anvilctl
rm -rf /usr/local/tengine

anvil_info "Removing runtime directories..."
rm -rf /etc/anvil /opt/anvil /var/log/anvil /var/lib/anvil /run/anvil

anvil_info "Reloading systemd..."
systemctl daemon-reload

anvil_info "Anvil uninstalled."
