#!/usr/bin/env bash
# anvil/uninstall.sh — Remove all Anvil-installed components
#
# Six-phase removal. Safe to re-run; idempotent.
#
# Usage:
#   sudo ./uninstall.sh              # interactive — confirm each phase
#   sudo ./uninstall.sh --yes        # non-interactive — run all phases
#   sudo ./uninstall.sh --phase N    # run only phase N (1-6)

set -euo pipefail

ANVIL_ROOT="$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")"
export ANVIL_ROOT

# shellcheck source=lib/core.sh
source "${ANVIL_ROOT}/lib/core.sh"

# ---------------------------------------------------------------------------
# Parse arguments
# ---------------------------------------------------------------------------
PHASE=""
DRY_RUN=0
YES=0

for arg in "$@"; do
  case "$arg" in
    --yes|--noninteractive) YES=1 ;;
    --dry-run) DRY_RUN=1 ;;
    --phase) PHASE="${2:-}"; shift 2 ;;
    -h|--help)
      cat <<'EOF'
Anvil Uninstaller

Usage:
  sudo ./uninstall.sh              # interactive
  sudo ./uninstall.sh --yes        # non-interactive
  sudo ./uninstall.sh --phase N    # phase 1-6 only
  sudo ./uninstall.sh --dry-run    # show what would be removed

Phases:
  1. Stop and disable systemd services
  2. Remove systemd unit files
  3. Remove binaries (caddy, frankenphp, tengine, anvilctl)
  4. Remove runtime directories (/etc/anvil, /opt/anvil, /var/log/anvil)
  5. Remove conflicting web servers (apache2, nginx, php-fpm)
  6. Restore DNS / hosts configuration
EOF
      exit 0 ;;
  esac
done

run_phase() {
    local num="$1" desc="$2" cmd="$3"
    echo
    anvil_info "Phase $num: $desc"
    if [[ "$DRY_RUN" -eq 1 ]]; then
        anvil_info "[DRY-RUN] Would execute: $cmd"
        return 0
    fi
    if [[ "$YES" -eq 0 ]]; then
        read -rp "  Execute phase $num? [Y/n] " confirm
        [[ "$confirm" =~ ^[Nn]$ ]] && { anvil_info "  Skipped."; return 0; }
    fi
    eval "$cmd" || anvil_warn "  Phase $num completed with warnings."
}

# ---------------------------------------------------------------------------
# Phase 1: Stop and disable services
# ---------------------------------------------------------------------------
phase1() {
    run_phase 1 "Stop and disable Anvil services"         "systemctl stop anvil-caddy anvil-tengine 'anvil-frankenphp@*' anvil-secrets 2>/dev/null || true; \
         systemctl disable anvil-caddy anvil-tengine 'anvil-frankenphp@*' anvil-secrets 2>/dev/null || true"
}

# ---------------------------------------------------------------------------
# Phase 2: Remove systemd units
# ---------------------------------------------------------------------------
phase2() {
    run_phase 2 "Remove systemd unit files"         "rm -f /etc/systemd/system/anvil-*.service /etc/systemd/system/anvil-*.timer; \
         systemctl daemon-reload"
}

# ---------------------------------------------------------------------------
# Phase 3: Remove binaries
# ---------------------------------------------------------------------------
phase3() {
    run_phase 3 "Remove Anvil binaries"         "rm -f /usr/local/bin/caddy /usr/local/bin/frankenphp /usr/local/bin/anvilctl; \
         rm -rf /usr/local/tengine /usr/local/bin/mkcert /usr/local/bin/sass"
}

# ---------------------------------------------------------------------------
# Phase 4: Remove runtime directories
# ---------------------------------------------------------------------------
phase4() {
    run_phase 4 "Remove runtime directories"         "rm -rf /etc/anvil /opt/anvil /var/log/anvil /var/lib/anvil /run/anvil /etc/dnsmasq.d/anvil.conf"
}

# ---------------------------------------------------------------------------
# Phase 5: Remove conflicting web servers
# ---------------------------------------------------------------------------
phase5() {
    if [[ "$YES" -eq 0 && "$DRY_RUN" -eq 0 ]]; then
        echo
        read -rp "Remove conflicting web servers (apache2, nginx, php-fpm)? [y/N] " confirm
        [[ "$confirm" =~ ^[Yy]$ ]] || { anvil_info "Phase 5 skipped."; return 0; }
    fi
    run_phase 5 "Remove conflicting web servers"         "apt-get remove -y apache2 nginx php-fpm 2>/dev/null || true; \
         apt-get autoremove -y 2>/dev/null || true"
}

# ---------------------------------------------------------------------------
# Phase 6: Restore DNS
# ---------------------------------------------------------------------------
phase6() {
    run_phase 6 "Restore DNS configuration"         "systemctl enable systemd-resolved 2>/dev/null || true; \
         systemctl restart systemd-resolved 2>/dev/null || true; \
         sed -i '/# anvil-managed/d' /etc/hosts 2>/dev/null || true"
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
anvil_info "Anvil Uninstaller"
[[ "$DRY_RUN" -eq 1 ]] && anvil_info "DRY-RUN mode — no changes will be made."

if [[ -n "$PHASE" ]]; then
    case "$PHASE" in
        1) phase1 ;;
        2) phase2 ;;
        3) phase3 ;;
        4) phase4 ;;
        5) phase5 ;;
        6) phase6 ;;
        *) anvil_error "Unknown phase: $PHASE"; exit 1 ;;
    esac
else
    phase1
    phase2
    phase3
    phase4
    phase5
    phase6
fi

anvil_info "Uninstall complete."
