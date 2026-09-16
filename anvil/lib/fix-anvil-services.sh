#!/usr/bin/env bash
# anvil/lib/fix-anvil-services.sh — fix Tengine + FrankenPHP + Caddy startup
#
# Root cause of all three failures:
#   1. Tengine: /etc/anvil/lb/tengine.conf has unsubstituted __TOKENS__ or
#      missing runtime dirs (/var/log/anvil, /run/anvil, /var/lib/anvil/dyups)
#   2. FrankenPHP: public/index.php didn't use frankenphp_handle_request()
#      for worker mode — the worker exited after one request, triggering
#      "too many consecutive failures" (fixed in PR #173)
#   3. Caddy: stopped cleanly (exit 0) — just needs restart after 1+2 are fixed
#
# Usage: sudo bash anvil/lib/fix-anvil-services.sh
set -euo pipefail

echo "=== Anvil Service Fix ==="
echo ""

# --- 0. Source anvil config ---
ANVIL_ROOT="${ANVIL_ROOT:-$(dirname "$(dirname "$(readlink -f "$0")")")}"
# shellcheck source=/dev/null
source "${ANVIL_ROOT}/config/anvil.conf" 2>/dev/null || true

# Defaults if not set
: "${ANVIL_LB_TENGINE_CONF:=/etc/anvil/lb/tengine.conf}"
: "${ANVIL_LOG_DIR:=/var/log/anvil}"
: "${ANVIL_RUN_DIR:=/run/anvil}"
: "${ANVIL_LIB_DIR:=/var/lib/anvil}"
: "${ANVIL_DYUPS_STATE:=/var/lib/anvil/dyups}"
: "${ANVIL_CURRENT_SYMLINK:=/opt/anvil/current}"
: "${TENGINE_LISTEN_PORT:=8081}"
: "${FRANKENPHP_BLUE_PORT:=8090}"

# --- 1. Create runtime directories ---
echo ">>> Step 1: Create runtime directories"
install -d -m 0750 -o tengine -g tengine "$ANVIL_LOG_DIR"
install -d -m 0750 -o tengine -g tengine "$ANVIL_RUN_DIR"
install -d -m 0750 -o tengine -g tengine "$ANVIL_DYUPS_STATE"
install -d -m 0750 -o anvil  -g anvil   "$ANVIL_LOG_DIR"
install -d -m 0750 -o anvil  -g anvil   "$ANVIL_RUN_DIR"
echo "  ✅ Runtime dirs created: $ANVIL_LOG_DIR, $ANVIL_RUN_DIR, $ANVIL_DYUPS_STATE"

# --- 2. Re-render Tengine config (substitute tokens) ---
echo ""
echo ">>> Step 2: Re-render Tengine config"
TEMPLATE="${ANVIL_ROOT}/lb/tengine.conf"
if [[ ! -f "$TEMPLATE" ]]; then
    echo "  ❌ Missing template: $TEMPLATE"
    exit 1
fi

install -d -m 0755 "$(dirname "$ANVIL_LB_TENGINE_CONF")"

sed -e "s|__TENGINE_LISTEN_PORT__|${TENGINE_LISTEN_PORT}|g" \
    -e "s|__FRANKENPHP_BLUE_PORT__|${FRANKENPHP_BLUE_PORT}|g" \
    -e "s|__ANVIL_DYUPS_STATE__|${ANVIL_DYUPS_STATE}|g" \
    -e "s|__ANVIL_LOG_DIR__|${ANVIL_LOG_DIR}|g" \
    -e "s|__ANVIL_RUN_DIR__|${ANVIL_RUN_DIR}|g" \
    -e "s|__ANVIL_CURRENT_SYMLINK__|${ANVIL_CURRENT_SYMLINK}|g" \
    "$TEMPLATE" > "$ANVIL_LB_TENGINE_CONF"
chmod 0644 "$ANVIL_LB_TENGINE_CONF"
echo "  ✅ Config rendered: $ANVIL_LB_TENGINE_CONF"

# --- 3. Test Tengine config ---
echo ""
echo ">>> Step 3: Test Tengine config"
if /usr/local/tengine/sbin/nginx -t -c "$ANVIL_LB_TENGINE_CONF"; then
    echo "  ✅ Tengine config test passed"
else
    echo "  ❌ Tengine config test FAILED"
    echo "  Check: /usr/local/tengine/sbin/nginx -T -c $ANVIL_LB_TENGINE_CONF 2>&1 | tail -20"
    exit 1
fi

# --- 4. Ensure /opt/anvil/current points to the repo (dev mode) ---
echo ""
echo ">>> Step 4: Ensure /opt/anvil/current symlink"
if [[ ! -L "$ANVIL_CURRENT_SYMLINK" ]]; then
    # Point it at the DGLab repo root (dev mode)
    REPO_ROOT="$(dirname "$ANVIL_ROOT")"
    ln -sf "$REPO_ROOT" "$ANVIL_CURRENT_SYMLINK"
    echo "  ✅ Symlinked $ANVIL_CURRENT_SYMLINK → $REPO_ROOT"
else
    CURRENT_TARGET=$(readlink -f "$ANVIL_CURRENT_SYMLINK")
    echo "  ✅ Already exists: $ANVIL_CURRENT_SYMLINK → $CURRENT_TARGET"
fi

# --- 5. Ensure composer autoload is generated ---
echo ""
echo ">>> Step 5: Ensure composer autoload"
if [[ ! -f "${ANVIL_CURRENT_SYMLINK}/vendor/autoload.php" ]]; then
    echo "  Running composer install at ${ANVIL_CURRENT_SYMLINK}..."
    cd "${ANVIL_CURRENT_SYMLINK}"
    composer install --no-interaction --prefer-dist --no-dev 2>/dev/null || \
    composer install --no-interaction --prefer-dist 2>/dev/null || \
    echo "  ⚠️  composer install failed — run manually: cd ${ANVIL_CURRENT_SYMLINK} && composer install"
fi
if [[ -f "${ANVIL_CURRENT_SYMLINK}/vendor/autoload.php" ]]; then
    echo "  ✅ Autoload exists: ${ANVIL_CURRENT_SYMLINK}/vendor/autoload.php"
else
    echo "  ❌ Missing vendor/autoload.php — run: cd ${ANVIL_CURRENT_SYMLINK} && composer install"
fi

# --- 6. Reset Tengine failure counter + restart ---
echo ""
echo ">>> Step 6: Reset and restart Tengine"
systemctl reset-failed anvil-tengine 2>/dev/null || true
systemctl restart anvil-tengine
sleep 1
if systemctl is-active --quiet anvil-tengine; then
    echo "  ✅ anvil-tengine: active"
else
    echo "  ❌ anvil-tengine: still failing"
    systemctl status anvil-tengine --no-pager -l | tail -10
fi

# --- 7. Reset FrankenPHP failure counter + restart ---
echo ""
echo ">>> Step 7: Reset and restart FrankenPHP"
systemctl reset-failed anvil-frankenphp@blue 2>/dev/null || true
systemctl restart anvil-frankenphp@blue
sleep 2
if systemctl is-active --quiet anvil-frankenphp@blue; then
    echo "  ✅ anvil-frankenphp@blue: active"
else
    echo "  ❌ anvil-frankenphp@blue: still failing"
    echo "  Check: journalctl -u anvil-frankenphp@blue --no-pager -n 20"
    echo ""
    echo "  If the error is 'failed to initialize workers: too many consecutive failures':"
    echo "    The new public/index.php uses frankenphp_handle_request() for worker mode."
    echo "    Make sure the repo at $ANVIL_CURRENT_SYMLINK has the updated public/index.php:"
    echo "      cd $ANVIL_CURRENT_SYMLINK && git pull"
    echo "      cd $ANVIL_CURRENT_SYMLINK && composer install"
    echo "      sudo systemctl restart anvil-frankenphp@blue"
fi

# --- 8. Restart Caddy ---
echo ""
echo ">>> Step 8: Restart Caddy"
systemctl reset-failed anvil-caddy 2>/dev/null || true
systemctl restart anvil-caddy
sleep 1
if systemctl is-active --quiet anvil-caddy; then
    echo "  ✅ anvil-caddy: active"
else
    echo "  ❌ anvil-caddy: still failing"
    systemctl status anvil-caddy --no-pager -l | tail -10
fi

# --- 9. Summary ---
echo ""
echo "=== Summary ==="
for svc in anvil-tengine anvil-frankenphp@blue anvil-caddy; do
    status=$(systemctl is-active "$svc" 2>/dev/null || echo "unknown")
    echo "  $svc: $status"
done

echo ""
echo "To test: curl -s http://localhost:8081/health  (Tengine → FrankenPHP)"
echo "         curl -s http://localhost/                (Caddy → Tengine → FrankenPHP)"
