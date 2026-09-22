# Anvil v3

> Caddy edge + Tengine internal LB + FrankenPHP app — for development on Linux
> laptops, staging VMs, and production servers / edge nodes.

Anvil v3 is the deployment stack for DGLab. It runs the three-tier proxy chain
mandated by ADR-017 (Fiber-based cooperative runtime) and the v3
ReImplementation_Instruction:

| Layer | Responsibility | Technology | Exposure |
|---|---|---|---|
| **Edge** | Public TLS (ACME), HTTP/3, dynamic vhost certs, redirects, the only public listener | **Caddy** 2.11.x | Internet (80/443 TCP+UDP) |
| **Internal LB** | Keepalive pools, response buffering (slow-client shielding), active health checks, blue/green upstream switching (`dyups`), rate limiting | **Tengine** 3.2.0 | Loopback only |
| **App server** | PHP 8.5 execution, worker lifecycle, Fiber/Pulse scheduling (ADR-017), tenant isolation | **FrankenPHP** 1.12.x | Loopback only |

**RULE T1 (Zero-Exposure Edge):** Caddy is the *only* process that may bind a
public socket, in every environment. Tengine listens on loopback. FrankenPHP
listens on loopback. This is the infrastructure-level expression of DEPLOY-03's
Zero-Exposure Test, and it is the correct risk posture in 2026 because
Tengine's release cadence is the weak link of the trio (CVE-2026-42945
"NGINX Rift" was unfixed in Tengine 3.1.0 for ~3 months after nginx shipped
the upstream fix — §2.2 of the v3 doc).

The full design rationale, version floors, CVE ledger, port registry, and
per-environment runbooks live in **[ReImplementation_Instruction.md](./ReImplementation_Instruction.md)**.
This README is the operator's quick-start; the v3 doc is the source of truth.

---

## What's new in v3

| | v1 (legacy) | v3 (this) |
|---|---|---|
| Runtime | PHP-FPM 8.3 (Docker) | FrankenPHP 1.12.x (worker mode) |
| Edge | nginx (mkcert, rendered vhosts) | Caddy 2.11.x (ACME/on-demand, stock binary) |
| Internal LB | — (nginx was both) | Tengine 3.2.0 (loopback-only, dyups/check/buffering) |
| Local dev | compose + dnsmasq + mkcert + watcher | Single FrankenPHP process + v1's dnsmasq/mkcert |
| TLS (prod) | certbot webroot + rendered vhosts | Caddy ACME; on-demand for tenants |
| Deploys | none (manual) | dyups blue/green at the LB tier, health-gated, auto-rollback |

The v1 principles that survive verbatim: one shared bash engine with thin
skins (`anvilctl` / TUI / Web UI), idempotent installer, loopback-only Web UI
(SSH tunnel on remote hosts), SSM-held RDS credentials, the t3.small cost gate,
the billing alarm, injection-safe `db create`, and the honest-limitations
register.

---

## Quick start — development on a Linux laptop

Tested on Ubuntu 24.04 LTS / Debian 13 / Fedora 42-43 / Arch Linux, x86_64 and
aarch64. See §5.1 of the v3 doc for the full distro matrix.

```bash
# 1. Install host prerequisites (Docker + dnsmasq + mkcert + dart-sass).
#    v1 installer; idempotent.
sudo ./install.sh --yes

# 2. Install the v3 runtime (FrankenPHP only — collapsed dev mode).
#    The install-trio.sh script is for staging/prod; in dev you only need
#    the FrankenPHP binary on PATH at /usr/local/bin/frankenphp:
curl -fsSL -o /usr/local/bin/frankenphp \
  https://github.com/php/frankenphp/releases/download/v1.12.7/frankenphp-linux-x86_64
sudo chmod +x /usr/local/bin/frankenphp

# 3. Verify the install.
anvilctl doctor
# Expect:
#   OK    frankenphp v1.12.7 (floor 1.12.7)
#   OK    frankenphp embedded-caddy v2.11.4 (floor 2.11.1)

# 4. Boot the dev stack (data tier via Docker + FrankenPHP collapsed mode).
anvilctl start
# → docker compose (MySQL 8.4 + Redis 7) up
# → frankenphp run --config anvil/dev/Caddyfile.dev (foreground logs)
# → https://dglab.test/  (mkcert wildcard cert, hot reload on file change)

# 5. Stop everything.
anvilctl stop
```

For full parity rehearsal on the laptop (Caddy + Tengine + FrankenPHP trio):

```bash
anvilctl stack full
anvilctl start   # boots all three units; same configs as staging/prod
```

---

## Quick start — staging VM

Staging runs the **exact production configs** with three knobs: Let's Encrypt
staging CA, `num 4` workers, and the staging deploy pool. See §6 of the v3 doc.

```bash
# 1. Provision (Hetzner CX22 / AWS t3.medium / DO s-2vcpu-4gb).
#    The cloud-init.yaml provisions the trio + systemd units + first-boot gate.
anvilctl ec2 provision --env staging --tls staging --workers 4

# 2. SSH in and verify the gates.
ssh anvil@staging.dglab.example.com
anvilctl verify all
# Expect:
#   verify/boot:    PASSED (all units active, doctor green)
#   verify/health:  PASSED (/health 2xx within 5s, check_status rise>=2)
#   verify/headers: PASSED (security headers + XFF chain https)
#   verify/ports:   PASSED (no port-registry collisions)

# 3. Deploy a release candidate.
anvilctl deploy staging --release 3x4mpl3d1g3s7
# → blue→green swap via dyups, health-gated, smoke-tested, auto-rollback on fail
```

---

## Quick start — production / edge node

Production adds on-demand TLS (per-tenant certificates via the `ask` endpoint)
and the weekly CVE ritual. See §7 of the v3 doc for the full runbook.

```bash
# 1. Provision (cloud-init.yaml installs the trio + first-boot health gate).
anvilctl ec2 provision --env production --confirm-t3-small

# 2. First-boot health gate refused to mark provisioned if /health failed.
#    Verify it is up:
anvilctl status
# caddy[edge]:     active (admin 127.0.0.1:2020)
# tengine[lb]:     active (127.0.0.1:8081)
# frankenphp[blue]: active (admin 127.0.0.1:2019, threads: total=8 busy=2 idle=6)

# 3. Register tenants (presence IS registration — no vhost render step).
anvilctl register acme-corp
# → mkdir www/acme-corp/
# → Caddy's on-demand TLS ask endpoint will now return 200 for acme-corp.dglab.example.com

# 4. Deploy a release (blue/green via dyups, §7.7 of v3 doc).
anvilctl deploy production --release $(git rev-parse --short HEAD)
# Steps 0–7 run automatically; auto-rollback on any gate failure.

# 5. Weekly CVE ritual (15 minutes, calendarized).
anvilctl doctor
# → compares installed binaries against config/versions.env floors
# → on advisory: bump the floor, restart the affected unit (blue/green for
#   frankenphp/caddy; HUP for tengine), verify with `anvilctl verify all`
```

---

## Layout

```
anvil/
├── ReImplementation_Instruction.md   # v3 source of truth (1220 lines)
├── README.md                         # this file (operator's quick-start)
├── install.sh                        # v1 host prereqs (Docker + dnsmasq + mkcert)
├── install-trio.sh                   # v3 trio installer (Caddy + Tengine + FrankenPHP)
├── bin/
│   ├── anvilctl                      # CLI dispatcher (v3 surface)
│   └── fetch-secrets.sh              # SSM → /etc/anvil/secrets.env
├── config/
│   ├── anvil.conf                    # bash-sourcable engine config (v1 + v3 vars)
│   └── versions.env                  # PINNED FLOORS — single source of truth
├── lib/                              # THE ENGINE (one engine, three skins)
│   ├── core.sh                       # logging, doctor, port-registry
│   ├── caddy.sh                      # edge lifecycle, validate/reload, ACME
│   ├── tengine.sh                    # LB lifecycle, dyups, check_status
│   ├── frankenphp.sh                 # app lifecycle, blue/green, admin API
│   ├── deploy.sh                     # §7.7 runbook as code (auto-rollback)
│   ├── verify.sh                     # §6.3 staging gates as code
│   ├── registry.sh                   # tenant registry (TLS-ask data source)
│   ├── docker.sh, db.sh, ec2.sh      # v1 (carried forward)
│   ├── ssl.sh, vhost.sh, project.sh  # v1 (kept for legacy escape hatch)
│   └── web.sh                        # v1 (Web UI server control)
├── dev/
│   ├── Caddyfile.dev                 # §5.5 collapsed-mode dev config
│   ├── compose.data.yml              # MySQL 8.4 + Redis 7 (loopback only)
│   └── .env.example
├── edge/Caddyfile                    # §7.3 edge template (staging+prod)
├── lb/
│   ├── tengine.conf                  # §7.4 internal LB template
│   └── tengine.build.sh              # source build w/ dyups+check+concat
├── app/
│   ├── Caddyfile.blue                # §7.5 blue/green template
│   └── php/preload.php               # ADR-010 opcache preload template
├── systemd/                          # §7.6 hardened units
│   ├── anvil-caddy.service
│   ├── anvil-tengine.service
│   ├── anvil-frankenphp@.service
│   └── anvil-secrets.service
├── scripts/
│   ├── vhost-watcher.sh              # inotify on WWW_DIR (rewired to registry)
│   └── deploy-smoke.sh               # public-URL smoke suite (used by deploy.sh)
├── lib/anvil-tui.sh                  # menu skin (v3 panels: stack, doctor, deploy, rollback)
├── web/                              # Web UI skin (v1 structure; v3 panels TBD)
├── provisioning/cloud-init.yaml      # v3 EC2 first-boot (trio + health gate)
└── www/                              # tenant roots (presence = registration)
```

---

## `anvilctl` command surface (v3)

```bash
# Lifecycle
anvilctl start [-d]        # dev: data tier + frankenphp (collapsed) | prod: three units
anvilctl stop
anvilctl status            # unit states, listener map, worker counts, tenant count
anvilctl restart <svc>     # caddy | tengine | app@blue | app@green | data

# Stack shape (dev)
anvilctl stack slim|full   # §5.6 of v3 doc

# Health & policy
anvilctl doctor            # CVE floors vs installed binaries; port-registry collision check
anvilctl verify boot|health|headers|ports|all   # §6.3 staging gates

# Deploys
anvilctl deploy <env> [--strategy blue-green] [--release DIGEST]   # §7.7 as code
anvilctl rollback <env>    # dyups swap back to the previous pool

# Tenant registry
anvilctl projects|scan|register <slug>|unregister <slug>|watch

# v1 surface (kept)
anvilctl db create DB_NAME | logs [svc] | build-assets
anvilctl ec2 provision|tunnel|billing|billing-alarm

# Provisioning helpers
anvilctl provision install-units     # (re)install systemd units + rendered configs
anvilctl provision install-trio      # run install-trio.sh (root required)
```

---

## Backward compatibility & migration (§8.5 of v3 doc)

- **Dev machines (v1):** `anvilctl stop` (old stack), `git pull`, `./install.sh`
  (idempotent — adds binaries/users/units), `anvilctl start` → collapsed mode.
  The `www/` projects and MySQL volumes carry over untouched; nginx vhost
  renders and cert files become dead artifacts, removed by
  `anvilctl migrate cleanup-legacy-nginx` (explicit, logged, reversible within
  30 days via the git history).
- **EC2 (v1):** rebuild the node via `anvil provision` (blue/green means
  migration is a new node, not an in-place surgery — STRUCTURE-08's
  immutable-swap applied to the host itself). RDS/SSM/alarm state is
  name-keyed and survives.
- **v2 draft artifacts:** none shipped to prod; the v2 doc is superseded by
  the v3 doc (§1.3 records the corrections).
- **Escape hatch:** `anvilctl stack legacy-nginx` preserves the v1
  nginx+PHP-FPM path for emergency comparison during the migration window
  only; it is removed at v3.1.

---

## Honest limitations register

The following are NOT yet implemented (tracked in §8.4 of the v3 doc):

- **Web UI panels for stack shape / doctor / deploy / rollback** — the TUI
  has them; the Web UI skin still shows the v1 panels. Tracked as T11.
- **`anvilctl migrate cleanup-legacy-nginx`** — the explicit dead-artifact
  cleanup is documented but not yet a command. Until it lands, remove
  `docker/nginx/conf.d/*.conf.anvil` and `docker/nginx/certs/*` manually
  after migrating.
- **Per-tenant worker pools (ADR-017 isolation level 2)** — the
  `worker` block in Caddyfile.blue supports it, but `anvilctl` does not yet
  render per-tenant variants. Tracked as a follow-up to T9.
- **`/debug/xff` endpoint** — referenced by `verify headers` and
  `deploy-smoke.sh`; must be implemented in the Hub (HUB-15 contract).
  Until then both scripts degrade to a WARN, not a FAIL.

For the full limitations register and the 13-task implementation plan, see
§8.4 of [ReImplementation_Instruction.md](./ReImplementation_Instruction.md).

---

## License & authorship

Same as the DGLab repo root. See `LICENSE` in the repository root.
