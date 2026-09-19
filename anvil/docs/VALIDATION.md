# Anvil v3 — Validation Procedures

This document is the operator-facing validation playbook for Anvil v3. Every
procedure here is also automated by `anvilctl verify` (lib/verify.sh) — this
file is the human-readable mirror, useful for audit, change-review, and
incident review.

The authoritative spec is §6.3 (staging gates) and §9 (troubleshooting) of
[ReImplementation_Instruction.md](../ReImplementation_Instruction.md).

---

## 1. Boot gate — `anvilctl verify boot`

**What it checks:** all three systemd units are `active (running)` AND
`anvilctl doctor` returns green (version floors met).

**Manual equivalent:**

```bash
systemctl is-active anvil-caddy
systemctl is-active anvil-tengine
systemctl is-active anvil-frankenphp@blue
anvilctl doctor
```

**Pass criteria:**
- All three `systemctl is-active` calls print `active`.
- `anvilctl doctor` exits 0 with `OK` lines for caddy, frankenphp, frankenphp
  embedded-caddy, and (in staging/prod) tengine.

**Common failures:**

| Symptom | Likely cause | Fix |
|---|---|---|
| `caddy not active` | Bad edge Caddyfile (token left unrendered) | `anvilctl provision install-units` to re-render; then `systemctl start anvil-caddy` |
| `tengine not active` | Config syntax error or missing `tengine` user | `anvil_tengine_validate`; check `getent passwd tengine` |
| `frankenphp@blue not active` | `EnvironmentFile=/etc/anvil/secrets.env` missing or 0644 (should be 0640 root:anvil) | `systemctl start anvil-secrets`; check `ls -l /etc/anvil/secrets.env` |
| `doctor: FAIL frankenphp embedded-caddy < floor` | FrankenPHP build vendors an old Caddy | Rebuild via official docker image or xcaddy recipe (frankenphp.dev/docs/config) |

---

## 2. Health gate — `anvilctl verify health`

**What it checks:** `/health` returns 2xx within 5 s (HUB-15 contract); Tengine
`check_status` shows the upstream as `up` with `rise >= 2`.

**Manual equivalent:**

```bash
curl -fsS --max-time 5 https://dglab.example.com/health
curl -s http://127.0.0.1:8081/_anvil/upstream | grep -i 'up'
```

**Pass criteria:**
- Public `/health` returns 2xx within 5 s.
- Tengine `/_anvil/upstream` dashboard HTML contains `Up` (case-insensitive)
  for the `frankenphp` upstream.

**Common failures:**

| Symptom | Likely cause | Fix |
|---|---|---|
| `/health` returns 502 | Tengine up but FrankenPHP blue down | `systemctl status anvil-frankenphp@blue`; check `/var/log/anvil/tengine-access.log` |
| `/health` returns 504 | FrankenPHP alive but slow (>75s read_timeout) | Check `frankenphp_busy_threads` via admin API; raise `num` or fix slow code |
| `/_anvil/upstream` shows `Down` | Active health checks failing (3 consecutive) | `curl http://127.0.0.1:8090/health` directly — if that 200s, the check_http_send format is wrong; if it 5xxs, fix the app |
| `/health` times out | Caddy → Tengine path broken | `curl http://127.0.0.1:8081/_anvil/ping` — should 200 `ok\n` |

---

## 3. Headers gate — `anvilctl verify headers`

**What it checks:** security headers (HSTS, X-Content-Type-Options,
X-Frame-Options) are present; the X-Forwarded-For chain works end-to-end
(the app's `/debug/xff` endpoint echoes `scheme=https` and the real client IP).

**Manual equivalent:**

```bash
curl -fsSI https://dglab.example.com | grep -iE 'strict-transport|x-content-type|x-frame'
curl -fsS https://dglab.example.com/debug/xff | jq .
```

**Pass criteria:**
- `strict-transport-security: max-age=31536000; includeSubDomains; preload`
- `x-content-type-options: nosniff`
- `x-frame-options: DENY`
- `/debug/xff` returns JSON with `"scheme":"https"` and a non-loopback
  `client_ip` (when tested from a real external host).

**Common failures:**

| Symptom | Likely cause | Fix |
|---|---|---|
| `x-frame-options: SAMEORIGIN` | FrankenPHP's `file_server` adding its own header | Remove the `php_server` extra header or override in Caddyfile.blue |
| `scheme=http` from /debug/xff | Tengine's `proxy_set_header X-Forwarded-Proto https;` removed or commented | Restore the line in `lb/tengine.conf` template |
| `/debug/xff` 404 | Hub not deployed (HUB-15 contract pending) | WARN, not FAIL — the gate degrades to a security-headers-only check |

---

## 4. Ports gate — `anvilctl verify ports`

**What it checks:** no port-registry collisions (§3.4 of v3 doc). Every entry
in the registry is held by the process the registry says should hold it.

**Manual equivalent:**

```bash
anvilctl status | grep -A30 LIVE LISTENERS
```

**Pass criteria:**
- `127.0.0.1:2020`  held by `caddy`
- `127.0.0.1:8081`  held by `nginx` (Tengine) or `tengine`
- `127.0.0.1:8090`  held by `frankenphp`
- `127.0.0.1:8091`  held by `frankenphp` (only during deploys/rehearsals)
- `127.0.0.1:2019`  held by `frankenphp`
- `127.0.0.1:2018`  held by `frankenphp` (only during deploys/rehearsals)
- `:80` and `:443`  held by `caddy`

**Common failures:**

| Symptom | Likely cause | Fix |
|---|---|---|
| `127.0.0.1:8081 held by 'nginx' but registry owner is 'tengine'` | Tengine's binary is named `nginx` (it's an nginx fork) — the check accepts both `nginx` and `tengine` | None — false positive, verify with `nginx -v` showing `Tengine/3.2.0` |
| `:80 held by 'nginx'` | Stale v1 nginx container still running | `docker stop anvil-nginx` (v1); `anvilctl migrate cleanup-legacy-nginx` (when it lands) |
| `127.0.0.1:2019 held by 'caddy'` | FrankenPHP not started; Caddy edge has not been started; the admin port collision is the canary | Boot order is wrong — `systemctl start anvil-frankenphp@blue` BEFORE `anvil-caddy` |

---

## 5. Full suite — `anvilctl verify all`

Runs gates 1–4 in order. Returns 0 only if ALL pass. This is the gate a
release candidate must clear before promotion (§6.3 of v3 doc):

> A release candidate that passes 1–5 is promoted by digest; one that fails
> any gate is rejected on staging — never "fixed in prod" (STRUCTURE-08 §1:
> rollback requires no human decision).

The fifth gate (blue/green rehearsal under load) is not automated by
`anvilctl verify all` because it requires a load generator. Run it manually:

```bash
# In one terminal: deploy a release candidate to staging.
anvilctl deploy staging --release rc-$(date +%s)

# In another: drive 50 rps for 30s during the swap.
hey -z 30s -q 50 https://staging.dglab.example.com/

# Acceptance: zero non-2xx responses during the 30s window.
hey prints a status-code histogram; check `nongzip > 0` or any 5xx count > 0
as a failure.
```

---

## 6. Failure drills (quarterly)

Per §6.3 of the v3 doc, run these once a quarter on staging:

### 6.1 Kill the active worker pool

```bash
# On staging, kill the blue FrankenPHP master.
systemctl kill anvil-frankenphp@blue --signal=SIGKILL

# Expect within 9s (fall 3 × interval 3000):
#   - Tengine check_status marks the upstream 'Down'
#   - Caddy serves a clean 502 page (never a hang)
#   - No 5xx errors leak past the rate limiter

# Restore:
systemctl start anvil-frankenphp@blue
# Within 6s (rise 2 × interval 3000), check_status shows 'Up' again.
```

### 6.2 Tengine unit crash

```bash
systemctl kill anvil-tengine --signal=SIGKILL

# Expect:
#   - Caddy's health_uri /_anvil/ping fails; Caddy stops sending traffic
#     (health-gated fail-fast, §7.3 of v3 doc)
#   - Public requests return 502 within 10s (health_interval)

# Restore:
systemctl start anvil-tengine
```

### 6.3 Caddy unit crash

```bash
systemctl kill anvil-caddy --signal=SIGKILL

# Expect:
#   - Public 80/443 listeners go dark (no connection accepted)
#   - systemd Restart=on-failure brings Caddy back within 2s (RestartSec)

# Restore (if Restart did not catch it):
systemctl start anvil-caddy
```

### 6.4 Secrets-fetch failure

```bash
# Simulate an SSM outage.
sudo systemctl stop anvil-secrets
sudo rm /etc/anvil/secrets.env
sudo systemctl restart anvil-frankenphp@blue

# Expect:
#   - anvil-frankenphp@blue fails to start (EnvironmentFile missing)
#   - Tengine marks upstream Down within 9s
#   - Caddy serves 502

# Restore:
sudo systemctl start anvil-secrets
sudo systemctl restart anvil-frankenphp@blue
```

---

## 7. CVE ritual (weekly, 15 minutes)

Per §7.9 of the v3 doc. Calendarized; do not skip.

```bash
# 1. Run the doctor — compares installed binaries against config/versions.env.
anvilctl doctor

# 2. Check the three upstream advisory feeds:
#    - nginx security advisories (Tengine inherits its CVE surface):
#      https://nginx.org/en/security_advisories.html
#    - Caddy releases: https://github.com/caddyserver/caddy/releases
#    - FrankenPHP releases: https://github.com/php/frankenphp/releases

# 3. On any advisory touching a running component:
#    a. Raise the floor in config/versions.env (commit with the advisory ID).
#    b. Roll the binary:
#       - Caddy/FrankenPHP bump → blue/green restart (§7.7 mechanics).
#       - Tengine bump → tengine -t + HUP.
#    c. Verify: anvilctl verify all

# 4. Standing posture on the next Rift-class event:
#    If the fixed Tengine release is not yet out, Option B (§3.5 of v3 doc):
#      - Stop the Tengine unit.
#      - Point Caddy's reverse_proxy directly at the FrankenPHP pools
#        (regenerate Caddyfile with APP_TRANSPORT=tcp and no Tengine).
#      - Schedule the restore.
```
