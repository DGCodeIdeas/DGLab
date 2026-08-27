#!/usr/bin/env bash
# anvil/lb/tengine.build.sh
#
# Build Tengine 3.2.0 from source with the modules Anvil v3 depends on:
#   * dyups              — dynamic upstream mutation (blue/green deploys, §7.7)
#   * ngx_http_upstream_check_module — active health checks (§7.4)
#   * ngx_http_concat_module         — asset concatenation (ISPOKE-05)
#
# WHEN TO USE THIS SCRIPT:
#   1. Your distro does not ship Tengine 3.2.0 packages yet (most don't, as
#      of 2026-08-28 — 3.2.0 final is still shipping). The official Tengine
#      release publishes x86_64 + aarch64 packages; install.sh prefers those
#      and falls back to this builder.
#   2. You need a non-default module set (e.g. adding the GeoIP module).
#
# CVE NOTE (§2.2 of v3 doc):
#   Tengine 3.1.0 (Oct 2023) is VULNERABLE to CVE-2026-42945 ("NGINX Rift",
#   heap overflow in ngx_http_rewrite_module, RCE-class). It was fixed in
#   3.2.0-rc1 (2026-08-01) and ships in 3.2.0 final. NEVER build an older
#   version. This script refuses to.
#
# Usage:
#   sudo anvil/lb/tengine.build.sh [--prefix /usr/local/tengine] [--version 3.2.0]
#
# Output:
#   - Tengine installed at --prefix (default: /usr/local/tengine)
#   - Binary: <prefix>/sbin/nginx  (Anvil's $ANVIL_TENGINE_BIN)
#   - Config: <prefix>/conf/       (not used by Anvil — config lives in /etc/anvil/lb/)

set -euo pipefail

PREFIX="/usr/local/tengine"
VERSION="3.2.0"
WORKDIR="${TMPDIR:-/tmp}/anvil-tengine-build"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --prefix)  PREFIX="$2";  shift 2 ;;
    --version) VERSION="$2"; shift 2 ;;
    -h|--help)
      cat <<EOF
Usage: $0 [--prefix /usr/local/tengine] [--version 3.2.0]
Builds Tengine ${VERSION} with dyups + check + concat modules.
EOF
      exit 0 ;;
    *) echo "Unknown option: $1" >&2; exit 2 ;;
  esac
done

# CVE guard: refuse to build anything older than 3.2.0.
if [[ "$(printf '%s\n' "3.2.0" "$VERSION" | sort -V | head -1)" != "3.2.0" ]]; then
  echo "REFUSING to build Tengine $VERSION — CVE-2026-42945 ('NGINX Rift') is unfixed below 3.2.0." >&2
  echo "Run with --version 3.2.0 (or later, when released)." >&2
  exit 1
fi

command -v gcc              >/dev/null || { echo "missing: gcc (apt install build-essential)" >&2; exit 1; }
command -v make             >/dev/null || { echo "missing: make" >&2; exit 1; }
command -v curl             >/dev/null || { echo "missing: curl" >&2; exit 1; }
[[ -f /usr/include/pcre2.h ]]         || { echo "missing: pcre2-dev (apt install libpcre2-dev)" >&2; exit 1; }
[[ -d /usr/include/openssl ]]         || { echo "missing: libssl-dev (apt install libssl-dev)" >&2; exit 1; }
[[ -d /usr/include/zlib ]]            || { echo "missing: zlib1g-dev (apt install zlib1g-dev)" >&2; exit 1; }

echo "==> Building Tengine ${VERSION} → ${PREFIX}"
mkdir -p "$WORKDIR" && cd "$WORKDIR"

TARBALL="tengine-${VERSION}.tar.gz"
URL="https://tengine.taobao.org/download/${TARBALL}"
if [[ ! -f "$TARBALL" ]]; then
  echo "==> Downloading $URL"
  curl -fsSLO "$URL"
fi
rm -rf "tengine-${VERSION}"
tar xzf "$TARBALL"
cd "tengine-${VERSION}"

# The dyups + check + concat modules are bundled with Tengine sources
# (under modules/). Add them via --add-module.
./configure \
  --prefix="$PREFIX" \
  --sbin-path="${PREFIX}/sbin/nginx" \
  --conf-path="${PREFIX}/conf/nginx.conf" \
  --pid-path="/run/anvil/tengine.pid" \
  --error-log-path="/var/log/anvil/tengine-error.log" \
  --http-log-path="/var/log/anvil/tengine-access.log" \
  --user=tengine --group=tengine \
  --with-http_ssl_module \
  --with-http_v2_module \
  --with-http_stub_status_module \
  --with-http_realip_module \
  --with-threads \
  --with-compat \
  --add-module=modules/ngx_http_upstream_check_module \
  --add-module=modules/ngx_http_upstream_dyups_module \
  --add-module=modules/ngx_http_concat_module

make -j"$(nproc)"
make install

# Smoke-test the built binary.
"${PREFIX}/sbin/nginx" -V 2>&1 | head -5
echo
echo "==> Tengine ${VERSION} installed at ${PREFIX}/sbin/nginx"
echo "==> Anvil: set ANVIL_TENGINE_BIN=${PREFIX}/sbin/nginx in anvil.conf if non-default"
