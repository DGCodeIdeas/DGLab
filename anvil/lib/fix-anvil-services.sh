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

# Reset the failure counter
systemctl reset-failed anvil-tengine 2>/dev/null || true

# Patch the systemd unit: increase rate-limit tolerance + run ExecStartPre as root
UNIT_FILE="/etc/systemd/system/anvil-tengine.service"
if [[ -f "$UNIT_FILE" ]]; then
    # Add StartLimitBurst/StartLimitIntervalSec if not present
    if ! grep -q 'StartLimitBurst' "$UNIT_FILE"; then
        sed -i '/^\[Service\]/i StartLimitBurst=10\nStartLimitIntervalSec=30' "$UNIT_FILE"
        echo "  ✅ Patched: StartLimitBurst=10, StartLimitIntervalSec=30"
    fi
    # Run ExecStartPre as root (+ prefix) — tengine user can't write logs during config test
    if ! grep -q 'ExecStartPre=+/usr/local/tengine/sbin/nginx -t' "$UNIT_FILE"; then
        sed -i 's|ExecStartPre=/usr/local/tengine/sbin/nginx -t|ExecStartPre=+/usr/local/tengine/sbin/nginx -t|' "$UNIT_FILE"
        echo "  ✅ Patched: ExecStartPre runs as root (+prefix)"
    fi
    # Also run ExecStart as root — Tengine as tengine user fails on PID/log writes
    # under ProtectSystem=strict. Running as root + dropping privileges inside
    # nginx.conf (user tengine;) is the correct pattern.
    if ! grep -q 'ExecStart=+/usr/local/tengine/sbin/nginx' "$UNIT_FILE"; then
        sed -i 's|ExecStart=/usr/local/tengine/sbin/nginx|ExecStart=+/usr/local/tengine/sbin/nginx|' "$UNIT_FILE"
        echo "  ✅ Patched: ExecStart runs as root (+prefix)"
    fi
    systemctl daemon-reload
fi

# Clear any stale PID file
rm -f /run/anvil/tengine.pid 2>/dev/null || true

# Try starting
systemctl start anvil-tengine 2>/dev/null || true
sleep 2
if systemctl is-active --quiet anvil-tengine; then
    echo "  ✅ anvil-tengine: active"
else
    echo "  ❌ anvil-tengine: still failing"
    echo ""
    echo "  --- Tengine error log (last 10 lines) ---"
    tail -10 /var/log/anvil/tengine-error.log 2>/dev/null || echo "  (no error log found)"
    echo ""
    echo "  --- Running nginx directly (as root) for diagnostics ---"
    /usr/local/tengine/sbin/nginx -c "$ANVIL_LB_TENGINE_CONF" 2>&1 || true
    sleep 1
    /usr/local/tengine/sbin/nginx -s stop -c "$ANVIL_LB_TENGINE_CONF" 2>/dev/null || true
    echo ""
    echo "  --- systemctl status ---"
    systemctl status anvil-tengine --no-pager -l 2>&1 | tail -10
    echo ""
    echo "  If nginx starts fine as root but systemd still fails:"
    echo "    The issue is likely User=tengine in the unit file. The + prefix"
    echo "    on ExecStart should make it run as root. Verify with:"
    echo "      systemctl cat anvil-tengine"
fi

# --- 7. Reset FrankenPHP failure counter + restart ---
echo ""
echo ">>> Step 7: Reset and restart FrankenPHP"

# Stop first to clear the rate-limit
systemctl stop anvil-frankenphp@blue 2>/dev/null || true
systemctl reset-failed anvil-frankenphp@blue 2>/dev/null || true

# The systemd unit file is mangled from multiple sed patches. Write it
# cleanly from scratch instead of patching.
FRANKENPHP_UNIT="/etc/systemd/system/anvil-frankenphp@.service"
echo "  Writing clean systemd unit for dev mode..."
cat > "$FRANKENPHP_UNIT" << FRANKENPHP_EOF
# /etc/systemd/system/anvil-frankenphp@.service — Anvil v3 app server (FrankenPHP)
# Rewritten by fix-anvil-services.sh for dev mode.
[Unit]
Description=Anvil v3 app server (FrankenPHP %i)
After=network-online.target
StartLimitBurst=10
StartLimitIntervalSec=30

[Service]
Type=notify
User=anvil
Group=anvil
WorkingDirectory=${RESOLVED}
EnvironmentFile=/etc/anvil/secrets.env
ExecStart=/usr/local/bin/frankenphp run --config /etc/anvil/app/Caddyfile.%i
ExecReload=/usr/local/bin/frankenphp reload --config /etc/anvil/app/Caddyfile.%i
TimeoutStopSec=30s
KillSignal=SIGTERM
Restart=on-failure
RestartSec=2s
LimitNOFILE=65535
# Dev mode: no hardening — the app lives in /home/dgi/www/DGLab
# which requires home access and write access to the repo tree.
FRANKENPHP_EOF

systemctl daemon-reload
echo "  ✅ Clean unit written: WorkingDirectory=${RESOLVED}"

# Ensure the var/ directory exists
install -d -m 0755 -o anvil -g anvil "${RESOLVED}/var/cache" 2>/dev/null || true
install -d -m 0755 -o anvil -g anvil "${RESOLVED}/var/log" 2>/dev/null || true

# Fix permissions: anvil user needs execute (traverse) on the repo path.
# /home/dgi may be 0700 — anvil can't traverse it.
chmod o+x /home/dgi 2>/dev/null || true
chmod o+x /home/dgi/www 2>/dev/null || true
chmod -R o+rX "${RESOLVED}" 2>/dev/null || true
echo "  ✅ Fixed directory permissions for anvil user traversal"

# Also ensure /etc/anvil/secrets.env exists
if [[ ! -f /etc/anvil/secrets.env ]]; then
    echo "  Creating /etc/anvil/secrets.env (empty placeholder)..."
    echo "# Anvil secrets — populated by anvil-secrets.service or manually" > /etc/anvil/secrets.env
    echo "# No secrets needed for dev mode" >> /etc/anvil/secrets.env
    chmod 0640 /etc/anvil/secrets.env
    chown anvil:anvil /etc/anvil/secrets.env 2>/dev/null || true
fi

# Always re-render Caddyfile.blue from the template (ensures latest config
# with dev-mode php_ini settings — no opcache.preload, validate_timestamps=1)
echo "  Rendering Caddyfile.blue from template..."
install -d -m 0755 /etc/anvil/app
sed -e "s|__LISTEN_PORT__|${FRANKENPHP_BLUE_PORT:-8090}|g" \
    -e "s|__ADMIN_PORT__|${FRANKENPHP_BLUE_ADMIN_PORT:-2019}|g" \
    -e "s|__WORKERS__|${ANVIL_DEV_WORKERS:-2}|g" \
    -e "s|__APP_ROOT__|${RESOLVED}|g" \
    -e "s|__TRUSTED_PROXIES__|127.0.0.1|g" \
    -e "s|__APP_ENV__|dev|g" \
    "${ANVIL_ROOT}/app/Caddyfile.blue" > /etc/anvil/app/Caddyfile.blue
echo "  ✅ Caddyfile.blue rendered (APP_ROOT=${RESOLVED})"

systemctl start anvil-frankenphp@blue 2>/dev/null || true
sleep 3
if systemctl is-active --quiet anvil-frankenphp@blue; then
    echo "  ✅ anvil-frankenphp@blue: active"
else
    echo "  ❌ anvil-frankenphp@blue: still failing"
    echo ""
    echo "  --- journalctl (last 15 lines) ---"
    journalctl -u anvil-frankenphp@blue --no-pager -n 15 2>&1
    echo ""
    echo "  Common fixes:"
    echo "    1. Check /etc/anvil/app/Caddyfile.blue exists and has correct APP_ROOT"
    echo "    2. Check /etc/anvil/secrets.env exists"
    echo "    3. Check that anvil user can read ${RESOLVED}/public/index.php"
    echo "       sudo -u anvil cat ${RESOLVED}/public/index.php"
    echo "    4. Verify Caddyfile.blue APP_ROOT:"
    echo "       grep APP_ROOT /etc/anvil/app/Caddyfile.blue"
fi

# --- 8. Re-render edge Caddyfile + restart Caddy ---
echo ""
echo ">>> Step 8: Restart Caddy"

# Re-render the edge Caddyfile from the template (adds dev-mode localhost block).
EDGE_TEMPLATE="${ANVIL_ROOT}/edge/Caddyfile"
if [[ -f "$EDGE_TEMPLATE" ]]; then
    # In dev mode, add a localhost site block with tls internal (self-signed cert)
    # so curl -k https://localhost/ works. In prod, this block is empty.
    # NOTE: DEV_LOCALHOST_BLOCK is multi-line. Inlining a newline-containing
    # variable into sed's s|||g breaks the command (the shell expands the
    # variable before sed sees it, splitting one s/// across several raw
    # lines -> "unterminated `s' command"). Use the read-file+delete idiom
    # instead, which is newline-safe by construction.
    DEV_LOCALHOST_BLOCK_FILE="$(mktemp)"
    if [[ "${APP_ENV:-dev}" == "dev" ]]; then
        printf 'localhost {\n    tls internal\n    reverse_proxy 127.0.0.1:8081\n}\n' \
            > "$DEV_LOCALHOST_BLOCK_FILE"
    else
        : > "$DEV_LOCALHOST_BLOCK_FILE"
    fi

    sed -e "s|__CADDY_ADMIN_PORT__|2020|g" \
        -e "s|__TENGINE_LISTEN_PORT__|8081|g" \
        -e "s|__ACME_CA_LINE__||g" \
        -e "s|__ACME_EMAIL__|ops@dglab.example|g" \
        -e "s|__PRIMARY_FQDN__|dglab.example.com|g" \
        "$EDGE_TEMPLATE" > /tmp/anvil-edge-caddyfile.stage1

    # Anchor the pattern with ^...$ so it matches ONLY the placeholder line
    # (exactly "__DEV_LOCALHOST_BLOCK__" on its own line), NOT the comment
    # two lines above which mentions the token by name:
    #   # Token: __DEV_LOCALHOST_BLOCK__ — empty in prod, ...   ← comment, starts with #
    #   __DEV_LOCALHOST_BLOCK__                                 ← placeholder, the only line that should match
    # Without the anchor, /r and /d both match the comment too, inserting the
    # block twice → "ambiguous site definition: localhost" in Caddy.
    sed -e "/^__DEV_LOCALHOST_BLOCK__$/r ${DEV_LOCALHOST_BLOCK_FILE}" \
        -e "/^__DEV_LOCALHOST_BLOCK__$/d" \
        /tmp/anvil-edge-caddyfile.stage1 > /etc/anvil/edge/Caddyfile

    rm -f /tmp/anvil-edge-caddyfile.stage1 "$DEV_LOCALHOST_BLOCK_FILE"
    echo "  ✅ Edge Caddyfile rendered (dev_localhost=${APP_ENV:-dev})"
fi

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
