#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/lib/install-trio.sh
#
# Anvil v3 trio installer — Caddy + Tengine + FrankenPHP.
#
# INTERNAL MODULE — not for direct execution. Called by install.sh or
# anvilctl provision install-trio. ANVIL_ROOT must be set before sourcing.
#
# Installs:
#   * Pinned binaries (from config/versions.env) to:
#       /usr/local/bin/caddy
#       /usr/local/bin/frankenphp
#       /usr/local/tengine/sbin/nginx   (Tengine; package-or-source)
#   * System users: caddy, tengine, anvil
#   * Runtime dirs: /etc/anvil/, /opt/anvil/, /var/log/anvil/, /var/lib/anvil/, /run/anvil/
#   * Systemd units (systemd/*.service) → /etc/systemd/system/
#   * Sysctl + open-files limits (99-anvil.conf)
#   * Firewall baseline (ufw OR nftables; RULE T1 enforcement, §7.2 of v3 doc)
#   * Configured Caddyfile + tengine.conf + Caddyfile.blue/green (rendered from templates)
#
# Exit codes: 0 success; 2 missing required tool; 3 binary download failed.
#   (Caller handles root check.)

set -euo pipefail

# ANVIL_ROOT must be exported by the caller (install.sh or anvilctl).
# shellcheck source=../config/anvil.conf
source "${ANVIL_ROOT}/config/anvil.conf"
# shellcheck source=lib/core.sh
source "${ANVIL_ROOT}/lib/core.sh"
# shellcheck source=lib/caddy.sh
source "${ANVIL_ROOT}/lib/caddy.sh"
# shellcheck source=lib/tengine.sh
source "${ANVIL_ROOT}/lib/tengine.sh"
# shellcheck source=lib/frankenphp.sh
source "${ANVIL_ROOT}/lib/frankenphp.sh"

# ---------------------------------------------------------------------------
# anvil_install_trio [--env staging|production] [--noninteractive]
#
# Function wrapper — call this instead of sourcing the file directly.
# ANVIL_ROOT must be exported. Caller handles root check.
# ---------------------------------------------------------------------------
anvil_install_trio() {
  local ANVIL_INSTALL_ENV="production"
  local NONINTERACTIVE=0
  for arg in "$@"; do
    case "$arg" in
      --env) ANVIL_INSTALL_ENV="${2:-production}"; shift 2 ;;
      --noninteractive|--yes) NONINTERACTIVE=1 ;;
    esac
  done

  # Propagate env into the config vars used by the lib renderers.
  export ANVIL_ENV="$ANVIL_INSTALL_ENV"
  if [[ "$ANVIL_INSTALL_ENV" == "staging" ]]; then
    export ACME_CA="https://acme-staging-v02.api.letsencrypt.org/directory"
  fi

  anvil_info "Anvil v3 trio install — env=${ANVIL_INSTALL_ENV} noninteractive=${NONINTERACTIVE}"
  anvil_info "version floors: $(awk -F= '/^[A-Z]/ {printf "%s=%s ", $1, $2}' "$ANVIL_VERSIONS_ENV")"

  if [[ $NONINTERACTIVE -eq 0 ]]; then
    read -r -p "Proceed with trio install? [y/N] " yn
    case "$yn" in
      y|Y|yes|YES) ;;
      *) anvil_die 1 "aborted by user" ;;
    esac
  fi

# ---------------------------------------------------------------------------
# Step 1: Create system users + groups.
# ---------------------------------------------------------------------------
anvil_info "[1/7] System users + groups"
create_user() {
  local user="$1" group="$2" home="$3"
  if ! getent group "$group" >/dev/null 2>&1; then
    groupadd --system "$group"
  fi
  if ! id -u "$user" >/dev/null 2>&1; then
    useradd --system --gid "$group" --home-dir "$home" --no-create-home --shell /usr/sbin/nologin "$user"
  fi
}
create_user caddy   caddy   /var/lib/caddy
create_user tengine tengine /var/lib/tengine
create_user anvil   anvil   /opt/anvil

# ---------------------------------------------------------------------------
# Step 2: Runtime directories.
# ---------------------------------------------------------------------------
anvil_info "[2/7] Runtime directories"
install -d -m 0755 -o root  -g root   /etc/anvil/edge
install -d -m 0755 -o root  -g root   /etc/anvil/lb
install -d -m 0750 -o root  -g anvil  /etc/anvil/app
install -d -m 0750 -o root  -g anvil  /etc/anvil  # secrets.env lives here
install -d -m 0755 -o anvil -g anvil  /opt/anvil/releases
install -d -m 0750 -o tengine -g tengine /var/log/anvil /var/lib/anvil/dyups /run/anvil

# ---------------------------------------------------------------------------
# Step 3: Install pinned binaries.
# ---------------------------------------------------------------------------
anvil_info "[3/7] Pinned binaries"

# Read floors from versions.env.
declare -A FLOORS
while IFS='=' read -r k v; do FLOORS["$k"]="$v"; done < <(_anvil_parse_versions_env)

install_binary_from_github() {
  local name="$1" repo="$2" version="$3" pattern="$4" dest="$5"
  if [[ -x "$dest" ]] && "$dest" version 2>&1 | head -1 | grep -q "$version"; then
    anvil_info "  $name $version already installed at $dest"
    return 0
  fi
  local url
  # shellcheck disable=SC2059
  url="$(printf "$pattern" "$repo" "$version" "$(uname -m)" "$(uname -m)")"
  anvil_info "  downloading $name $version → $dest"
  if ! curl -fsSL -o "$dest.tmp" "$url"; then
    anvil_error "  FAILED to download $url"
    rm -f "$dest.tmp"
    return 3
  fi
  mv "$dest.tmp" "$dest"
  chmod +x "$dest"
  anvil_info "  installed: $dest"
}

# Caddy — stock binary from GitHub releases (no custom modules per §7.3 of v3 doc).
ARCH="$(uname -m)"
case "$ARCH" in
  x86_64)  CADDY_ARCH=amd64; FRANKEN_ARCH=linux-x86_64 ;;
  aarch64) CADDY_ARCH=arm64; FRANKEN_ARCH=linux-arm64  ;;
  *) anvil_die 2 "unsupported arch: $ARCH (Anvil v3 supports x86_64 and aarch64 only)" ;;
esac

install -d -m 0755 /usr/local/bin
install_binary_from_github caddy \
  "caddyserver/caddy" "${FLOORS[CADDY]}" \
  "https://github.com/%s/releases/download/v%s/caddy_%s_linux_${CADDY_ARCH}.tar.gz" \
  "$ANVIL_CADDY_BIN" \
  || anvil_die 3 "caddy install failed"

# FrankenPHP — single static binary (linux-amd64 or linux-arm64).
if [[ -x "$ANVIL_FRANKENPHP_BIN" ]] && "$ANVIL_FRANKENPHP_BIN" version 2>&1 | grep -q "${FLOORS[FRANKENPHP]}"; then
  anvil_info "  frankenphp ${FLOORS[FRANKENPHP]} already installed at $ANVIL_FRANKENPHP_BIN"
else
  anvil_info "  downloading frankenphp ${FLOORS[FRANKENPHP]} → $ANVIL_FRANKENPHP_BIN"
  curl -fsSL -o "$ANVIL_FRANKENPHP_BIN" \
    "https://github.com/php/frankenphp/releases/download/v${FLOORS[FRANKENPHP]}/frankenphp-${FRANKEN_ARCH}"
  chmod +x "$ANVIL_FRANKENPHP_BIN"
  anvil_info "  installed: $ANVIL_FRANKENPHP_BIN"
fi

# Tengine — prefer official packages, fall back to source build (lb/tengine.build.sh).
if [[ -x "$ANVIL_TENGINE_BIN" ]] && "$ANVIL_TENGINE_BIN" -v 2>&1 | head -1 | grep -q "${FLOORS[TENGINE]}"; then
  anvil_info "  tengine ${FLOORS[TENGINE]} already installed at $ANVIL_TENGINE_BIN"
else
  anvil_warn "  Tengine ${FLOORS[TENGINE]} not pre-installed — attempt source build?"
  anvil_warn "  Run:  anvilctl provision build-tengine"
  anvil_warn "  Tengine 3.2.0 packages ship x86_64+aarch64 only; on other arches Option B (Caddy-only) is the path."
  anvil_warn "  Skipping Tengine install — the Caddy + FrankenPHP pair is sufficient for Option B."
fi

# ---------------------------------------------------------------------------
# Step 4: Rendered configs.
# ---------------------------------------------------------------------------
anvil_info "[4/7] Rendered configs (edge Caddyfile, tengine.conf, app Caddyfile.blue/green)"
anvil_caddy_install_config
anvil_tengine_install_config
anvil_frankenphp_install_configs

# Install the preload template into each release (or symlink at /opt/anvil/current).
if [[ ! -f /opt/anvil/current/config/preload.php ]]; then
  install -d -m 0755 -o anvil -g anvil /opt/anvil/current/config
  cp "${ANVIL_ROOT}/app/php/preload.php" /opt/anvil/current/config/preload.php
  chown anvil:anvil /opt/anvil/current/config/preload.php
fi

# ---------------------------------------------------------------------------
# Step 5: Systemd units.
# ---------------------------------------------------------------------------
anvil_info "[5/7] Systemd units"
install -d -m 0755 /etc/systemd/system
for unit in anvil-caddy.service anvil-tengine.service anvil-frankenphp@.service anvil-secrets.service; do
  install -m 0644 "${ANVIL_ROOT}/systemd/${unit}" "/etc/systemd/system/${unit}"
done
# install fetch-secrets.sh into /opt/anvil/bin/ (where anvil-secrets.service expects it).
install -d -m 0755 -o root -g root /opt/anvil/bin
install -m 0755 "${ANVIL_ROOT}/bin/fetch-secrets.sh" /opt/anvil/bin/fetch-secrets.sh
systemctl daemon-reload
anvil_info "  4 units installed; boot order: secrets → frankenphp@blue → tengine → caddy"

# ---------------------------------------------------------------------------
# Step 6: Sysctl + open-files limits.
# ---------------------------------------------------------------------------
anvil_info "[6/7] Sysctl + open-files limits"
cat >/etc/sysctl.d/99-anvil.conf <<'EOF'
# Anvil v3 — kernel tunables for high-concurrency loopback proxy chain.
# Conservative; review before raising on shared hosts.
net.core.somaxconn = 4096
net.ipv4.tcp_max_syn_backlog = 4096
net.ipv4.tcp_fin_timeout = 15
net.ipv4.ip_local_port_range = 10000 65535
fs.file-max = 1048576
EOF
sysctl --system >/dev/null

cat >/etc/security/limits.d/99-anvil.conf <<'EOF'
# Anvil v3 — open-files limits for caddy/tengine/anvil users.
caddy    soft  nofile  65535
caddy    hard  nofile  65535
tengine  soft  nofile  65535
tengine  hard  nofile  65535
anvil    soft  nofile  65535
anvil    hard  nofile  65535
EOF

# ---------------------------------------------------------------------------
# Step 7: Firewall baseline (RULE T1 enforcement, §7.2 of v3 doc).
# ---------------------------------------------------------------------------
anvil_info "[7/7] Firewall baseline (RULE T1: only Caddy binds public)"
if command -v ufw >/dev/null 2>&1; then
  ufw allow 22/tcp     comment 'ssh (ops CIDR — restrict manually)' || true
  ufw allow 80/tcp     comment 'caddy edge (http redirect)' || true
  ufw allow 443/tcp    comment 'caddy edge (https/h2)' || true
  ufw allow 443/udp    comment 'caddy edge (http/3)' || true
  # Internal ports — NO ufw rule (loopback only by default).
  ufw --force enable || true
  anvil_info "  ufw configured: 22/tcp + 80/tcp + 443/tcp + 443/udp allowed; internals loopback-only"
elif command -v nft >/dev/null 2>&1; then
  cat >/etc/nftables.conf <<'EOF'
#!/usr/sbin/nft -f
flush ruleset
table inet anvil {
    chain inbound {
        type filter hook input priority 0; policy drop;
        # Loopback is unrestricted.
        iif "lo" accept
        # Established.
        ct state established,related accept
        # Public: ssh (RESTRICT MANUALLY to ops CIDR!), http, https/h2, http/3.
        tcp dport 22 accept
        tcp dport { 80, 443 } accept
        udp dport 443 accept
        # ICMP.
        icmp type { echo-request, destination-unreachable, time-exceeded } accept
        icmpv6 type { echo-request, dest-unreach, time-exceeded, nd-neighbor-solicit, nd-neighbor-advert, packet-too-big } accept
        # Drop everything else.
    }
    chain forward { type filter hook forward priority 0; policy drop; }
    chain output  { type filter hook output  priority 0; policy accept; }
}
EOF
  systemctl enable --now nftables
  anvil_info "  nftables configured: 22/tcp + 80/tcp + 443/tcp + 443/udp allowed; internals loopback-only"
else
  anvil_warn "  no firewall tooling detected (ufw OR nftables) — install one and run install.sh again"
fi

# ---------------------------------------------------------------------------
# Done.
# ---------------------------------------------------------------------------
echo
anvil_info "Anvil v3 trio install complete."
echo
echo "Next steps:"
echo "  1. Verify the install:        anvilctl doctor"
echo "  2. (staging/prod only) Fetch secrets from SSM:"
echo "       sudo systemctl start anvil-secrets"
echo "  3. Start the trio:"
echo "       sudo systemctl enable --now anvil-frankenphp@blue"
echo "       sudo systemctl enable --now anvil-tengine"
echo "       sudo systemctl enable --now anvil-caddy"
echo "  4. Run the staging validation gates:"
echo "       anvilctl verify all"
echo
echo "Tengine note: if the source build was skipped above, run:"
echo "  anvilctl provision build-tengine"
echo "or adopt Option B (Caddy-only) per 3.5 of the v3 doc until 3.2.0 packages are available."
}  # end anvil_install_trio
