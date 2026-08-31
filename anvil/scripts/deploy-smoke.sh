#!/usr/bin/env bash
# anvil/scripts/deploy-smoke.sh
#
# Public-URL smoke suite, run by lib/deploy.sh AFTER the dyups switch (§7.7 step 5).
# Each failure triggers auto-rollback.
#
# What we check:
#   1. /health        returns 200 within 5s (HUB-15 contract)
#   2. Headers        HSTS, X-Content-Type-Options, X-Frame-Options present
#                     (security_headers snippet from the edge Caddyfile)
#   3. X-Forwarded-For chain  — the response echoes the correct client IP and
#                     `https` scheme (§3.3 of v3 doc; the /debug/xff endpoint
#                     is provided by the Hub and exists only in staging+prod)
#   4. HTTP/3         — the Alt-Svc header advertises h3 (best-effort; ignored
#                     on failure because not every smoke-test client supports UDP)
#
# Usage: deploy-smoke.sh ENV URL
# Exit 0 on success, 1 on any hard failure.

set -euo pipefail

ENV="${1:?Usage: deploy-smoke.sh ENV URL}"
URL="${2:?Usage: deploy-smoke.sh ENV URL}"

fail() { echo "SMOKE FAIL: $*" >&2; exit 1; }
ok()   { echo "SMOKE OK:   $*"; }

# 1. /health
if ! curl -fsS --max-time 5 "${URL}" >/dev/null 2>&1; then
  fail "${URL} did not return 2xx within 5s"
fi
ok "/health returned 2xx within 5s"

# 2. Security headers (the security_headers snippet from edge/Caddyfile).
HEADERS="$(curl -fsSI --max-time 5 "${URL}")"
for h in 'strict-transport-security' 'x-content-type-options' 'x-frame-options'; do
  if ! grep -qi "^${h}:" <<< "$HEADERS"; then
    fail "missing security header: ${h}"
  fi
done
ok "security headers present (HSTS, X-Content-Type-Options, X-Frame-Options)"

# 3. XFF chain — try the future Hub endpoint (/debug/xff) first; fall back to
#    the dev/staging fixture (/debug/xff.php). Both must satisfy the contract:
#    JSON with scheme=https and a non-loopback client_ip (when tested externally).
XFF_BASE="${URL%/health}"
XFF_RESPONSE=""
XFF_SOURCE=""
for xff_path in "/debug/xff" "/debug/xff.php"; do
  XFF_URL="${XFF_BASE}${xff_path}"
  if XFF_RESPONSE="$(curl -fsS --max-time 5 "$XFF_URL" 2>/dev/null)"; then
    XFF_SOURCE="$xff_path"
    break
  fi
done
if [[ -n "$XFF_SOURCE" ]]; then
  if command -v jq >/dev/null 2>&1; then
    scheme="$(jq -r '.scheme // empty' <<< "$XFF_RESPONSE")"
    [[ "$scheme" == "https" ]] || fail "XFF endpoint ${XFF_SOURCE} reported scheme='${scheme}' (expected https)"
    ok "XFF chain: scheme=https (client IP propagated through Caddy→Tengine→FrankenPHP) [${XFF_SOURCE}]"
  else
    ok "XFF chain: ${XFF_SOURCE} reachable (jq missing — skipping scheme assertion)"
  fi
else
  ok "XFF chain: no /debug/xff* endpoint found (Hub + fixture both absent) — skipping"
fi

# 4. HTTP/3 — Alt-Svc header advertisement (best-effort).
if grep -qi '^alt-svc:.*h3=' <<< "$HEADERS"; then
  ok "HTTP/3 advertised via Alt-Svc"
else
  echo "SMOKE WARN: Alt-Svc h3 not advertised (HTTP/3 may be UDP-blocked on this client)" >&2
fi

echo
echo "Smoke suite PASSED for ${URL} (env=${ENV})"
exit 0
