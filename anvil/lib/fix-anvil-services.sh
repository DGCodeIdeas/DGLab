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

# Compute the actual repo root (parent of anvil/).
# ANVIL_ROOT is the anvil/ directory; the repo root is its parent.
REPO_ROOT="$(dirname "$ANVIL_ROOT")"
echo "  ANVIL_ROOT: $ANVIL_ROOT"
echo "  REPO_ROOT:  $REPO_ROOT"

# Create /opt/anvil/ if it doesn't exist
install -d -m 0755 "$(dirname "$ANVIL_CURRENT_SYMLINK")"

# Remove existing file/dir/symlink at the symlink path — ln -sfn fails
# silently if the target is a directory (not a symlink).
if [[ -e "$ANVIL_CURRENT_SYMLINK" || -L "$ANVIL_CURRENT_SYMLINK" ]]; then
    if [[ -L "$ANVIL_CURRENT_SYMLINK" ]]; then
        echo "  Existing symlink found, replacing..."
        rm -f "$ANVIL_CURRENT_SYMLINK"
    elif [[ -d "$ANVIL_CURRENT_SYMLINK" ]]; then
        echo "  ⚠️  $ANVIL_CURRENT_SYMLINK is a directory (not a symlink). Removing..."
        rm -rf "$ANVIL_CURRENT_SYMLINK"
    else
        echo "  ⚠️  $ANVIL_CURRENT_SYMLINK is a file. Removing..."
        rm -f "$ANVIL_CURRENT_SYMLINK"
    fi
fi

ln -s "$REPO_ROOT" "$ANVIL_CURRENT_SYMLINK"
if [[ -L "$ANVIL_CURRENT_SYMLINK" ]]; then
    echo "  ✅ Symlinked $ANVIL_CURRENT_SYMLINK → $REPO_ROOT"
else
    echo "  ❌ Failed to create symlink. Trying with ln -sf..."
    ln -sf "$REPO_ROOT" "$ANVIL_CURRENT_SYMLINK"
fi

# Verify: readlink (not readlink -f) shows the target
SYMLINK_TARGET=$(readlink "$ANVIL_CURRENT_SYMLINK" 2>/dev/null || echo "")
echo "  Symlink target: $SYMLINK_TARGET"

# RESOLVED = the actual real path (follows symlinks)
RESOLVED=$(readlink -f "$ANVIL_CURRENT_SYMLINK" 2>/dev/null || echo "")
echo "  Resolved: $RESOLVED"

# If readlink -f returned the symlink path itself, the symlink is broken
if [[ "$RESOLVED" == "$ANVIL_CURRENT_SYMLINK" || -z "$RESOLVED" ]]; then
    echo "  ⚠️  Symlink not resolving. Using REPO_ROOT directly."
    RESOLVED="$REPO_ROOT"
fi

if [[ -f "${RESOLVED}/composer.json" ]]; then
    echo "  ✅ composer.json found at ${RESOLVED}/composer.json"
else
    echo "  ❌ composer.json NOT found at ${RESOLVED}/composer.json"
    echo "  Expected at: ${REPO_ROOT}/composer.json"
    ls -la "$REPO_ROOT"/composer.json 2>/dev/null || echo "  File does not exist."
fi

# --- 5. Ensure PHP CLI is available + composer autoload ---
echo ""
echo ">>> Step 5: Ensure PHP CLI + composer autoload"

# Check if php is in PATH
if ! command -v php &>/dev/null; then
    echo "  ⚠️  PHP CLI not found in PATH. Installing php8.3-cli..."
    apt-get update -qq && apt-get install -y -qq php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl 2>/dev/null || {
        echo "  ❌ Failed to install php8.3-cli. Trying php8.2..."
        apt-get install -y -qq php8.2-cli php8.2-mbstring php8.2-xml php8.2-curl 2>/dev/null || {
            echo "  ❌ Could not install PHP CLI automatically."
            echo "  Run: sudo apt install php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl"
            echo "  Then re-run this script."
            exit 1
        }
    }
    echo "  ✅ PHP CLI installed: $(php -v | head -1)"
fi

# Check if composer is available
if ! command -v composer &>/dev/null; then
    echo "  ⚠️  Composer not found. Installing..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer 2>/dev/null || {
        echo "  ❌ Failed to install composer."
        echo "  Run: curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer"
        exit 1
    }
    echo "  ✅ Composer installed: $(composer --version 2>/dev/null | head -1)"
fi

# Run composer install at the RESOLVED path (not the symlink path)
# — composer doesn't follow symlinks reliably for finding composer.json
echo "  Running composer install at ${RESOLVED}..."
cd "$RESOLVED" || {
    echo "  ❌ Failed to cd to ${RESOLVED}"
    exit 1
}
composer install --no-interaction --prefer-dist 2>&1 | tail -10 || {
    echo "  ⚠️  composer install failed — trying with --ignore-platform-reqs..."
    composer install --no-interaction --prefer-dist --ignore-platform-reqs 2>&1 | tail -10 || {
        echo "  ❌ composer install failed. Run manually:"
        echo "    cd ${RESOLVED} && composer install"
    }
}

if [[ -f "${RESOLVED}/vendor/autoload.php" ]]; then
    echo "  ✅ Autoload exists: ${RESOLVED}/vendor/autoload.php"
else
    echo "  ❌ Still missing vendor/autoload.php at ${RESOLVED}"
fi

# --- 6. Reset Tengine failure counter + restart ---
echo ""
echo ">>> Step 6: Reset and restart Tengine"

# Stop the service first (even if already stopped) to clear the rate-limit
systemctl stop anvil-tengine 2>/dev/null || true

# Reset the failure counter — this is the key step that was missing.
# Without this, systemd remembers the 5 previous fast restarts and
# refuses to start with "Start request repeated too quickly".
systemctl reset-failed anvil-tengine 2>/dev/null || true

# Reload systemd to pick up any unit file changes
systemctl daemon-reload

# Also patch the systemd unit to run nginx -t as root (the + prefix).
# When running as the tengine user, nginx -t fails because it can't
# write to the log directory during the config test.
UNIT_FILE="/etc/systemd/system/anvil-tengine.service"
if [[ -f "$UNIT_FILE" ]] && ! grep -q 'ExecStartPre=+/usr/local/tengine/sbin/nginx -t' "$UNIT_FILE"; then
    echo "  Patching systemd unit: nginx -t runs as root (+prefix)..."
    sed -i 's|ExecStartPre=/usr/local/tengine/sbin/nginx -t|ExecStartPre=+/usr/local/tengine/sbin/nginx -t|' "$UNIT_FILE"
    systemctl daemon-reload
    echo "  ✅ Patched: ExecStartPre now runs as root"
fi

systemctl start anvil-tengine 2>/dev/null || true
sleep 2
if systemctl is-active --quiet anvil-tengine; then
    echo "  ✅ anvil-tengine: active"
else
    echo "  ❌ anvil-tengine: still failing"
    echo ""
    echo "  --- systemctl status ---"
    systemctl status anvil-tengine --no-pager -l 2>&1 | tail -15
    echo ""
    echo "  --- journalctl (last 10 lines) ---"
    journalctl -u anvil-tengine --no-pager -n 10 2>&1
    echo ""
    echo "  --- tengine config test (as tengine user) ---"
    sudo -u tengine /usr/local/tengine/sbin/nginx -t -c "$ANVIL_LB_TENGINE_CONF" 2>&1 || \
        echo "  ⚠️  Config test fails as tengine user. Try running as root:"
        echo "    /usr/local/tengine/sbin/nginx -t -c $ANVIL_LB_TENGINE_CONF"
    echo ""
    echo "  --- tengine config test (as root) ---"
    /usr/local/tengine/sbin/nginx -t -c "$ANVIL_LB_TENGINE_CONF" 2>&1
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
