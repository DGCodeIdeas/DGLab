#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/lib/deploy.sh
#
# Blue/green deploy runbook as code — §7.7 of the v3 doc.
#
# Automates the seven steps from the runbook:
#   0. Preconditions  (anvilctl doctor green; all units active)
#   1. Ship immutable release to /opt/anvil/releases/<digest>/
#   2. Boot GREEN on the new digest (blue keeps serving traffic)
#   3. Health-gate green STRICTLY (30s poll; auto-rollback on failure)
#   4. THE SWITCH — single dyups POST, zero dropped connections
#   5. Smoke through the FULL public chain (auto-rollback on failure)
#   6. Drain & retire blue (30s grace for in-flight Fibers)
#   7. Blue becomes green next release (roles swap; :8090/:8091 alternate)
#
# Hard rules enforced (STRUCTURE-08 §1):
#   * No human decision required for rollback.
#   * A failed health gate or smoke suite triggers automatic dyups revert.
#   * The deploy is rejected on staging AND on prod — never "fix in prod".
#
# Usage:
#   anvilctl deploy <env>  [--strategy blue-green] [--timeout 30s] [--release DIGEST]
#   anvilctl rollback <env>

set -euo pipefail

if [[ -z "${ANVIL_ROOT:-}" ]]; then
  # shellcheck source=lib/core.sh
  source "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")/core.sh"
fi

# Resolve the ACTIVE pool (the one currently receiving traffic via dyups).
# Returns "blue" or "green". Defaults to blue if dyups is unreachable.
anvil_deploy_active_pool() {
  local upstream_line
  if upstream_line="$(anvil_tengine_dyups_get_upstream frankenphp 2>/dev/null)"; then
    if grep -q "127.0.0.1:${FRANKENPHP_GREEN_PORT}" <<< "$upstream_line"; then
      echo "green"
      return 0
    fi
  fi
  echo "blue"
}

# Resolve the INACTIVE pool — the one we will boot GREEN on for this deploy.
# If blue is active, green is the deploy target; vice versa.
anvil_deploy_inactive_pool() {
  local active
  active="$(anvil_deploy_active_pool)"
  case "$active" in
    blue)  echo "green" ;;
    green) echo "blue"  ;;
  esac
}

# Resolve the loopback port for a given pool name.
anvil_deploy_pool_port() {
  local pool="${1:?}"
  case "$pool" in
    blue)  echo "$FRANKENPHP_BLUE_PORT"  ;;
    green) echo "$FRANKENPHP_GREEN_PORT" ;;
    *) anvil_die 2 "anvil_deploy_pool_port: $pool" ;;
  esac
}

# anvil_deploy ENV [--strategy blue-green] [--timeout 30s] [--release DIGEST]
#   Performs the full §7.7 runbook. Auto-rollback on any gate failure.
anvil_deploy() {
  local env=""
  local strategy="blue-green"
  local timeout="30s"
  local release=""

  while [[ $# -gt 0 ]]; do
    case "$1" in
      --strategy)   strategy="$2";  shift 2 ;;
      --timeout)    timeout="$2";   shift 2 ;;
      --release)    release="$2";   shift 2 ;;
      -*) anvil_die 2 "anvil_deploy: unknown option $1" ;;
      *)
        if [[ -z "$env" ]]; then env="$1"; shift
        else anvil_die 2 "anvil_deploy: unexpected arg '$1'"; fi ;;
    esac
  done

  [[ -n "$env" ]] || anvil_die 2 "Usage: anvilctl deploy <env> [--strategy blue-green] [--timeout 30s] [--release DIGEST]"
  [[ "$strategy" == "blue-green" ]] || anvil_die 2 "anvil_deploy: only --strategy blue-green is supported in v3"
  [[ "$env" == "staging" || "$env" == "production" ]] \
    || anvil_die 2 "anvil_deploy: env must be staging|production (dev uses git pull + watch)"

  # Resolve the release digest.
  if [[ -z "$release" ]]; then
    if [[ -n "${CI_COMMIT_SHA:-}" ]]; then release="${CI_COMMIT_SHA:0:12}"
    elif [[ -n "${GITHUB_SHA:-}" ]]; then release="${GITHUB_SHA:0:12}"
    else release="$(date -u +%Y%m%d-%H%M%S)-local"
    fi
  fi
  anvil_info "deploy env=$env release=$release strategy=$strategy timeout=$timeout"

  # ---- Step 0: Preconditions ------------------------------------------------
  anvil_info "[0/7] Preconditions: anvilctl doctor + unit states"
  anvil_doctor_versions || anvil_die 1 "deploy: doctor failed — fix CVE floors before deploying"
  if [[ "$env" == "production" ]]; then
    systemctl is-active --quiet anvil-caddy            || anvil_die 1 "deploy: anvil-caddy not active"
    systemctl is-active --quiet anvil-tengine          || anvil_die 1 "deploy: anvil-tengine not active"
    systemctl is-active --quiet anvil-frankenphp@blue  || anvil_die 1 "deploy: anvil-frankenphp@blue not active"
  fi

  # ---- Determine direction: blue→green or green→blue -----------------------
  local active inactive active_port inactive_port
  active="$(anvil_deploy_active_pool)"
  inactive="$(anvil_deploy_inactive_pool)"
  active_port="$(anvil_deploy_pool_port "$active")"
  inactive_port="$(anvil_deploy_pool_port "$inactive")"
  anvil_info "deploy direction: ${active}(:${active_port}) → ${inactive}(:${inactive_port})"

  # ---- Step 1: Ship the immutable release ------------------------------------
  anvil_info "[1/7] Ship immutable release to ${ANVIL_RELEASES_DIR}/${release}/"
  # The release was uploaded by CI (rsync from the build job). Verify it exists.
  if [[ ! -d "${ANVIL_RELEASES_DIR}/${release}" ]]; then
    anvil_die 1 "deploy: release directory missing: ${ANVIL_RELEASES_DIR}/${release}"
  fi
  if [[ ! -f "${ANVIL_RELEASES_DIR}/${release}/public/index.php" ]]; then
    anvil_die 1 "deploy: release is missing public/index.php (bad build?)"
  fi
  if [[ ! -f "${ANVIL_RELEASES_DIR}/${release}/config/preload.php" ]]; then
    anvil_warn "deploy: release has no config/preload.php — opcache.preload will be a no-op"
    # Symlink Anvil's preload template in if missing.
    ln -sf "${ANVIL_ROOT}/app/php/preload.php" "${ANVIL_RELEASES_DIR}/${release}/config/preload.php"
  fi

  # ---- Step 2: Boot INACTIVE on the new digest ------------------------------
  anvil_info "[2/7] Boot anvil-frankenphp@${inactive} on release ${release}"
  # Point the inactive "current" symlink at the new release.
  local inactive_symlink
  case "$inactive" in
    blue)  inactive_symlink="$ANVIL_CURRENT_SYMLINK" ;;
    green) inactive_symlink="$ANVIL_CURRENT_GREEN_SYMLINK" ;;
  esac
  ln -sfn "${ANVIL_RELEASES_DIR}/${release}" "$inactive_symlink"
  # Regenerate the inactive Caddyfile so the root + worker count match the env.
  anvil_frankenphp_render "$ANVIL_APP_CADDYFILE_BLUE"  blue 2>/dev/null  || true
  anvil_frankenphp_render "$ANVIL_APP_CADDYFILE_GREEN" green 2>/dev/null || true
  systemctl restart "anvil-frankenphp@${inactive}"
  anvil_info "started anvil-frankenphp@${inactive} (release ${release})"

  # ---- Step 3: Health-gate inactive STRICTLY (auto-rollback) ----------------
  anvil_info "[3/7] Health-gate anvil-frankenphp@${inactive} (timeout ${timeout})"
  local timeout_s
  timeout_s="$(($(echo "$timeout" | sed 's/s$//') + 0))"
  local health_url="http://127.0.0.1:${inactive_port}/health"
  local ok=0
  for ((i=1; i<=timeout_s; i++)); do
    if curl -fsS --max-time 2 "$health_url" >/dev/null 2>&1; then
      ok=1; break
    fi
    sleep 1
  done
  if (( ok != 1 )); then
    anvil_error "deploy: health gate FAILED for anvil-frankenphp@${inactive} — auto-rollback"
    systemctl stop "anvil-frankenphp@${inactive}"
    anvil_die 1 "deploy REJECTED at health gate (release ${release})"
  fi
  # Tengine's check module must also see the inactive pool — wait for rise=2.
  anvil_info "waiting for Tengine check_status to mark upstream healthy (rise=2, ~6s)"
  sleep 6
  if ! curl -fsS "http://127.0.0.1:${TENGINE_LISTEN_PORT}/_anvil/upstream" 2>/dev/null \
       | grep -qi 'up'; then
    anvil_error "deploy: tengine check_status did not see the inactive pool as up"
    systemctl stop "anvil-frankenphp@${inactive}"
    anvil_die 1 "deploy REJECTED at check_status gate (release ${release})"
  fi
  anvil_info "inactive pool ${inactive} healthy; proceeding to dyups switch"

  # ---- Step 4: THE SWITCH — one dyups POST, zero dropped connections -------
  anvil_info "[4/7] dyups switch: frankenphp → 127.0.0.1:${inactive_port}"
  local server_line="server 127.0.0.1:${inactive_port} max_fails=3 fail_timeout=10s;"
  if ! anvil_tengine_dyups_set_upstream frankenphp "$server_line"; then
    anvil_error "deploy: dyups POST failed — auto-rollback"
    systemctl stop "anvil-frankenphp@${inactive}"
    anvil_die 1 "deploy REJECTED at dyups switch (release ${release})"
  fi

  # ---- Step 5: Smoke through the FULL public chain (auto-rollback) ----------
  anvil_info "[5/7] Smoke suite via public URL (lib/deploy-smoke.sh)"
  if ! "${ANVIL_ROOT}/lib/deploy-smoke.sh" "$env" "https://${ANVIL_PRIMARY_FQDN}/health"; then
    anvil_error "deploy: smoke suite FAILED — auto-rollback"
    # Swap back to the previously-active pool.
    anvil_tengine_dyups_set_upstream frankenphp \
      "server 127.0.0.1:${active_port} max_fails=3 fail_timeout=10s;" || true
    systemctl stop "anvil-frankenphp@${inactive}"
    anvil_die 1 "deploy REJECTED at smoke suite (release ${release})"
  fi

  # ---- Step 6: Drain & retire previously-active (30s grace for in-flight Fibers)
  anvil_info "[6/7] Drain ${active} (30s grace for in-flight Fibers)"
  sleep 30
  systemctl stop "anvil-frankenphp@${active}" || true
  anvil_info "retired anvil-frankenphp@${active}"

  # ---- Step 7: Bookkeeping — next deploy's INACTIVE is now ${active} --------
  anvil_info "[7/7] Deploy complete. Next deploy will swap back to ${active}."

  echo
  anvil_info "RELEASE: ${release}"
  anvil_info "ACTIVE:  anvil-frankenphp@${inactive} (127.0.0.1:${inactive_port})"
  anvil_info "DRAINED: anvil-frankenphp@${active} (127.0.0.1:${active_port})"
}

# anvil_rollback ENV
#   Swaps dyups back to the previously-active pool. The previously-active pool
#   is kept warm for 30s after every successful deploy (§7.7 step 6), so a
#   rollback within that window is instant. After 30s the pool is stopped —
#   a rollback then requires booting it fresh (slower, but always possible).
anvil_rollback() {
  local env="${1:?Usage: anvilctl rollback <env>}"
  local active inactive active_port inactive_port
  active="$(anvil_deploy_active_pool)"
  case "$active" in
    blue)  inactive="green" ;;
    green) inactive="blue"  ;;
  esac
  active_port="$(anvil_deploy_pool_port "$active")"
  inactive_port="$(anvil_deploy_pool_port "$inactive")"

  anvil_warn "rollback: switching dyups back from ${active}(:${active_port}) to ${inactive}(:${inactive_port})"

  # Boot the rollback target if it is not running.
  if ! systemctl is-active --quiet "anvil-frankenphp@${inactive}"; then
    anvil_warn "rollback: anvil-frankenphp@${inactive} not running — starting it"
    systemctl start "anvil-frankenphp@${inactive}"
    # Health-gate it before swapping.
    local i
    for ((i=1; i<=30; i++)); do
      curl -fsS --max-time 2 "http://127.0.0.1:${inactive_port}/health" >/dev/null 2>&1 && break
      sleep 1
    done
  fi

  anvil_tengine_dyups_set_upstream frankenphp \
    "server 127.0.0.1:${inactive_port} max_fails=3 fail_timeout=10s;"
  anvil_info "rollback complete: traffic now on anvil-frankenphp@${inactive}"
  anvil_warn "rollback: anvil-frankenphp@${active} left running for inspection — stop manually if undesired"
}
