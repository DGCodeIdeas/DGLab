# ReImplementation_Instruction.md — Anvil v3

**Status:** Draft v3 — awaiting architecture lead review
**Date:** 2026-08-28
**Supersedes:** `anvil/ReImplementation_Instruction.md` v2 (2026-08-26, Tengine-at-edge two-layer model) and, transitively, the legacy `anvil/README.md` v1 model (nginx + PHP-FPM)
**Companions:** `ADR-017` (Fiber-based cooperative runtime), `ADR-016` (library/app boundary), `ADR-010` (opcache preload), `DEPLOY-01..04`, `STRUCTURE-08` (immutable deployment), `DGLAB-AS-OS-RUNTIME.md`, `RUNBOOK-ANVIL-DNS.md`
**Scope:** Anvil re-implementation for three target environments — **development on major Linux laptops**, **staging VMs**, and **production servers / edge nodes**

---

## 1. Executive Summary

### 1.1 The Question and the Verdict

> **Can Caddy, Tengine, and FrankenPHP be combined — Caddy as the edge, Tengine as an internal load balancer, FrankenPHP as the application server — for production *and* development?**

**Yes. The trio composes cleanly, and the layering you propose is not just viable — it is the *safest possible way* to run Tengine in 2026.** All three components speak ordinary HTTP on the wire, all three handle `X-Forwarded-*` semantics correctly, and the roles do not overlap:

| Layer | Responsibility | Technology | Exposure |
|---|---|---|---|
| **Edge** | Public TLS (ACME), HTTP/3, dynamic vhost certificates, redirects, coarse rate limiting, the only public listener | **Caddy** 2.11.x | Internet (80/443 TCP+UDP) |
| **Internal LB** | Keepalive connection pools, **response buffering** (slow-client shielding), active health checks, blue/green upstream switching (`dyups`), request queueing, asset concat | **Tengine** 3.2.0 | Loopback / private network only |
| **App server** | PHP 8.5 execution, worker lifecycle, Fiber/Pulse scheduling (ADR-017), tenant isolation, admin + metrics APIs | **FrankenPHP** 1.12.x | Loopback (socket or 127.0.0.1) |

The single most important architectural fact uncovered by the research for this document (§2.2): **Tengine's release cadence is the weak link of the trio.** Tengine went nearly three years between stable releases (3.1.0, October 2023 → 3.2.0-rc1, August 2026), and during that gap every released Tengine inherited **CVE-2026-42945 ("NGINX Rift")** — a critical heap buffer overflow in `ngx_http_rewrite_module` with unauthenticated-RCE potential — for roughly three months *after* upstream nginx had shipped the fix. An internet-facing Tengine would have been exposed that whole time. **A loopback-only Tengine sitting behind Caddy converts fork-lag CVEs from "critical, internet-reachable" to "reachable only by the edge proxy itself."** Your proposed layering is therefore the correct risk posture, and this document hardens it into a rule:

> **RULE T1 (Zero-Exposure Edge):** In every DGLab environment, Caddy is the *only* process that may bind a public socket. Tengine listens on loopback (or a private bridge). FrankenPHP listens on a loopback socket. This is the infrastructure-level expression of DEPLOY-03's Zero-Exposure Test (the Vanguard is the sole ingress; no inner tier binds publicly).

**For development:** the trio collapses. FrankenPHP *is* a Caddy build (the app server embeds Caddy), so a single FrankenPHP process serves TLS + HTTP + PHP with one Caddyfile and one binary — no Tengine, no Docker requirement, ~15 MB of dependencies. The full three-layer stack remains one command away (`anvilctl stack full`) for prod-parity testing on a laptop.

### 1.2 What This Document Delivers

1. A verified version floor matrix and CVE ledger for all three components (researched 2026-08-28, sources in Appendix B).
2. The three-layer architecture: responsibilities, port/socket registry, trust chain, timeout ladder, and the documented collapse options.
3. Complete, copy-pasteable reference configurations for **dev (laptop)**, **staging (VM)**, and **production (edge node)** — Caddyfile (edge), `tengine.conf` (internal LB), Caddyfile (FrankenPHP app), systemd units, and Docker Compose where appropriate.
4. The Anvil v3 tool re-implementation plan: directory tree, `anvilctl` command surface, `lib/` modules, task table, and migration path from v1/v2.
5. Operations runbooks: zero-downtime blue/green deploys via `dyups`, monitoring, secret custody, backups, CVE watch, and a troubleshooting matrix keyed by hop.

### 1.3 Corrections Carried Forward from v2

The v2 draft (2026-08-26) was directionally right but contained four defects that v3 fixes:

| # | v2 defect | v3 correction |
|---|---|---|
| 1 | FrankenPHP pinned at "v1.2.5 / PHP 8.3" (a 2024-era version) | Floor is **v1.12.7** (2026-08-07), bundling **PHP 8.5** and an embedded Caddy ≥ 2.11 lineage (§2.1) |
| 2 | Port `:2019` used simultaneously as FrankenPHP's app listen port *and* its admin API port — a direct conflict (2019 is Caddy's default **admin** port) | Dedicated port/socket registry (§3.4): app traffic on `127.0.0.1:8090` (or unix socket), admin on `127.0.0.1:2019`, Caddy edge admin moved to `127.0.0.1:2020` |
| 3 | `max_requests` written inside the `worker {}` block | `max_requests` is a **global `frankenphp` option** (experimental), applied at the `frankenphp` level, not per-worker (§7.5, per official config docs) |
| 4 | Tengine at the edge, internet-facing, with no CVE posture | Tengine demoted to internal LB behind Caddy; **3.1.0 is forbidden** (CVE-2026-42945); floor is 3.2.0 final (§2.2) |

---

## 2. Latest-State Research (checked 2026-08-28)

### 2.1 Version Matrix — Verified Floors

| Component | Latest verified | Date | Floor for Anvil v3 | Notes |
|---|---|---|---|---|
| **Caddy** | **2.11.4** | 2026-06-03 | **≥ 2.11.1** (CSRF fix); recommend 2.11.4 | CVE-2026-27589 (admin-API CSRF) fixed in 2.11.1. 2.11.4 carries additional security-adjacent patches (path-matcher backslash normalization, rewrite placeholder re-expansion). |
| **FrankenPHP** | **v1.12.7** | 2026-08-07 | **≥ 1.12.5** (security); recommend 1.12.7 | 1.11.2 (2026-02-12): ~30% faster CGO, ~40% faster GC (Go 1.26), 3 security fixes. 1.11.3: PHP 8.5. 1.12.4 (2026-05-15): hardening/stability. 1.12.5: security release (two disclosed vulns). 1.12.7: fixes post-`fastcgi_finish_request()` output being discarded in classic mode. Project moved to `php/frankenphp` (from `dunglas/frankenphp`). |
| **Tengine** | **3.2.0-rc3** (final 3.2.0 shipping as of this writing) | 2026-08-11 (rc3) | **≥ 3.2.0 final** — 3.1.0 is FORBIDDEN | First release since 3.1.0 (2023-10-27) — a ~2.9-year gap. rc1 (2026-08-01) carries both CVE-2026-42945 fixes. Official packages: x86_64 and aarch64 only. |
| **PHP** | 8.5.x (current stable) | — | ≥ 8.1 (Fibers, ADR-017); recommend 8.5 via FrankenPHP builds | FrankenPHP ≥ 1.11.3 bundles PHP 8.5. |
| **nginx (reference)** | 1.30.4 stable / 1.31.4 mainline | 2026-07-15 | n/a (reference only) | Upstream baseline Tengine tracks; Rift fixed in 1.30.1+/1.31.0+. |
| **MySQL** | 8.4 LTS / 8.0 (v1 heritage) | — | 8.0 (RDS default) or 8.4 LTS for new stacks | ADR-013: MySQL is the primary datastore. `ANVIL_RDS_ENGINE_VERSION` stays configurable. |
| **Redis** | 7.x / 8.x available | — | 7.x floor (v1 heritage) | ADR-006. Redis 8.x or Valkey 8 are drop-in upgrades if licensing dictates; not required by this document. |

**Installation rule:** never install "whatever the distro ships" for the three core components. Pin exact versions in `anvil/config/versions.env` (§8.2) and let Anvil verify them at startup (`anvilctl doctor`, §8.3). Distro packages (e.g. distro Caddy) exist for convenience only and are acceptable **only** when they meet the floor.

### 2.2 CVE Ledger and What It Means for the Layering

| CVE | Component | Class | Fixed in | Anvil v3 impact |
|---|---|---|---|---|
| **CVE-2026-42945** ("NGINX Rift") | nginx / **Tengine** | Heap buffer overflow in `ngx_http_rewrite_module` (CWE-122); unauthenticated, RCE-capable where ASLR is disabled/bypassed; nginx 0.6.27–1.30.0 vulnerable | nginx 1.30.1+ / 1.31.0+ (May 2026); **Tengine: 3.2.0-rc1** (2026-08-01). Tengine 3.1.0 tracked as affected (alibaba/tengine#2044) | **The reason Tengine is not the edge.** Every Tengine release before 3.2.0-rc1 (i.e., all stables for ~3 years) is vulnerable. Anvil v3 forbids < 3.2.0 and keeps Tengine loopback-only regardless. |
| **CVE-2026-27589** | Caddy (and any Caddy-based build, incl. FrankenPHP's embedded server) | CSRF against the local admin API (`POST /load`, default `127.0.0.1:2019`, no `Origin` validation — "localhost is a lie": a malicious web page can drive the browser into POSTing to the victim's loopback admin port) | Caddy 2.11.1 | Two implications: (a) pin Caddy ≥ 2.11.1 at the edge; (b) **FrankenPHP's admin endpoint must be verified** — run `frankenphp version` and confirm the embedded Caddy lineage, keep admin bound to `127.0.0.1:2019`, never expose it through either proxy, and prefer `admin off` or endpoint lockdown on hardened nodes (§7.5, §7.6). |
| FrankenPHP 1.12.5 disclosures | FrankenPHP | Two disclosed vulnerabilities (details in upstream advisories) | 1.12.5 (2026-05) | Floor. Any deployment older than 1.12.5 is out of policy. |

**The strategic lesson, stated once:** a three-layer stack means three upgrade cadences. The Rift episode proves the marginal CVE risk concentrates in the *slowest-moving* component (the nginx fork), not the fastest (Caddy/FrankenPHP, which patch in days). Anvil v3 therefore does two things simultaneously: (1) puts the slowest-moving, fork-based component in the least-exposed position (internal LB), and (2) defines a weekly CVE-watch ritual with pre-agreed floors and an emergency posture (§7.9) so the next Rift has a runbook, not a fire drill.

### 2.3 What the Trio Gives DGLab That the v2 Pair Did Not

1. **Automatic HTTPS at the edge, without certbot.** Anvil v1 needed `certbot-setup.sh`, webroot validation, renewal timers, and rendered nginx vhosts referencing `/etc/letsencrypt`. Caddy's ACME client removes that entire subsystem: no certbot package, no renewal cron, no webroot dance, no `ssl_certificate` paths to template. Multi-tenant wildcard/dynamic certificates become `on_demand_tls` + an `ask` endpoint (§7.3) — the direct successor of Anvil v1's `www/` + vhost-watcher model.
2. **HTTP/3 at the edge.** Caddy ships QUIC/HTTP-3 by default. Tengine 3.2.0 also gains HTTP/3 via XQUIC — but on a release candidate as of this writing. Edge H3 now comes from the component with the fastest security cadence.
3. **Slow-client shielding for PHP workers.** Caddy's `reverse_proxy` streams responses by design; nginx-class proxies buffer by default. In a two-layer Caddy→FrankenPHP stack, a phone on a train holds a PHP worker (a Fiber + its whole heap) for the duration of a slow download. With Tengine in the middle and `proxy_buffering on` + tuned buffers, the worker finishes into Tengine's buffers in milliseconds and the slow client becomes Tengine's problem. On a 2-vCPU solo-operator edge node, this is the difference between 8 healthy workers and 8 workers all babysitting slow connections.
4. **Zero-downtime blue/green on a single box, without an external LB.** Tengine's `dyups` module mutates upstream server lists at runtime over a loopback HTTP interface. Combined with Caddy's graceful reloads, this delivers STRUCTURE-08's immutable-swap deployment (build → health-gate → switch → drain → rollback-on-fail) with zero dropped connections and no second machine.
5. **One config language at two layers.** The edge Caddyfile and the app-server Caddyfile are the same language, parsed by the same lineage (FrankenPHP *is* Caddy). Only Tengine's nginx syntax is unique to the middle — and its config is intentionally the smallest of the three (a pure traffic-shaping file, no TLS, no vhost rendering).

---

## 3. Architecture

### 3.1 Topologies

**Production / edge node (full trio):**

```
                          INTERNET
                             │
              80/tcp + 443/tcp + 443/udp (HTTP/3)
                             │
              ┌──────────────▼──────────────┐
              │  CADDY (edge)               │  systemd: anvil-caddy.service
              │  • ACME + on_demand_tls     │  admin: 127.0.0.1:2020
              │  • TLS 1.3, H2/H3           │
              │  • redirects, coarse limits │
              │  • security headers         │
              └──────────────┬──────────────┘
                             │  HTTP/1.1 keepalive, loopback
              ┌──────────────▼──────────────┐
              │  TENGINE (internal LB)      │  systemd: anvil-tengine.service
              │  listen 127.0.0.1:8081      │  dyups: 127.0.0.1:8081/_anvil/dyups/
              │  • upstream pools + active  │  check_status: /_anvil/upstream
              │    health checks (check)    │  stub_status: /_anvil/stub
              │  • response buffering       │
              │  • dyups blue/green swaps   │
              │  • limit_req, concat        │
              └───────┬──────────────┬──────┘
                      │ blue :8090   │ green :8091        (only one pool live)
              ┌───────▼──────────────▼──────┐
              │  FRANKENPHP (app server)    │  systemd: anvil-frankenphp@{blue,green}
              │  worker mode, PHP 8.5,      │  app: 127.0.0.1:8090 / :8091
              │  Fiber scheduler (ADR-017)  │  admin: 127.0.0.1:2019 (blue)
              │  num 2×vCPU, max_requests   │        127.0.0.1:2018 (green)
              └───────┬─────────────┬───────┘
                      │             │
              ┌───────▼──────┐ ┌────▼─────┐
              │ MySQL 8      │ │ Redis 7  │   DEPLOY-02: RDS/ElastiCache or
              │ (RDS / local)│ │ (managed │   local Docker, SSM-held creds
              └──────────────┘ │  / local)│
                               └──────────┘
   Loopback-only services: anvil Web UI 127.0.0.1:9999 (SSH-tunnel access),
   phpMyAdmin 127.0.0.1:8080 (v1 heritage), node_exporter 127.0.0.1:9100.
```

**Staging VM:** identical to production (same three systemd units, same config files, only the ACME CA and worker counts differ). Parity is the point — staging exists to rehearse the prod runbook.

**Development (collapsed default):**

```
   browser ──► https://<project>.test        (dnsmasq → 127.0.0.1, mkcert CA)
                     │
        ┌────────────▼─────────────┐
        │  FRANKENPHP (one process)│  frankenphp run --config anvil/dev/Caddyfile.dev
        │  embedded Caddy:         │  TLS: mkcert-issued *.test certs (or tls internal)
        │  :443 + worker mode +    │  admin: 127.0.0.1:2019
        │  watch/hot-reload        │
        └────────────┬─────────────┘
                     │
        ┌────────────▼─────────────┐
        │ MySQL 8 + Redis 7        │  docker compose -f anvil/dev/compose.data.yml
        │ (Docker, or host services)│ (inherited from Anvil v1 data tier)
        └──────────────────────────┘
   Optional: `anvilctl stack full` brings up Caddy + Tengine in front for
   prod-parity testing without leaving the laptop (§5.6).
```

### 3.2 Layer Responsibilities — Owns / Never Owns

| Layer | OWNS | NEVER OWNS |
|---|---|---|
| **Caddy (edge)** | Public TLS + ACME/on-demand certs; HTTP/3 + H2; HTTP→HTTPS redirects; coarse per-IP rate limiting (if built with the rate-limit module — see §7.3); security headers; request logging (structured); the `ask` authorization endpoint routing for on-demand certs | Application routing beyond host-level; PHP execution; response buffering policy; upstream health decisions; static docroot (kept docroot-free by design — see note) |
| **Tengine (internal)** | Upstream pools + active health checks (`check` module); connection keepalive pools to workers; **response buffering** and buffer sizing; `limit_req` zones; blue/green `dyups` swaps; `concat` for asset merging; serving built static assets from the release docroot; `real_ip` restoration of client IP from Caddy | TLS/certificates (terminated at edge); ACME; public sockets; PHP execution; business routing rules (host-level dispatch stays at Caddy, path-level dispatch stays in the app) |
| **FrankenPHP (app)** | PHP 8.5 runtime; worker lifecycle (`num`, `max_consecutive_failures`, global `max_requests`); Fiber/Pulse scheduling (ADR-017); `php_ini` tuning incl. opcache/preload (ADR-010); tenant isolation; `/health` (HUB-15 contract); admin API + metrics | TLS to the internet; static asset monopoly (Tengine serves the built docroot); load-balancing decisions; client-IP derivation (trusts Tengine's headers only) |

> **Design note — statics at Tengine, not Caddy:** Caddy could serve `/public` directly (fewer hops for bytes), but that would mount release artifacts into the edge process, coupling certificate-holding code to deployment payloads and re-introducing vhost-rendering state at the edge. Anvil v3 keeps the edge *stateless* (no docroot mount): Caddy terminates TLS and forwards; Tengine serves the immutable release docroot (`/opt/anvil/releases/<id>/public`, symlinked into `/opt/anvil/current`) and proxies the rest. Hot statics (fonts, media) can later be lifted to the edge or a CDN (STRUCTURE-08's CloudFront module) without touching the app tier.

### 3.3 The Trust Chain and the Timeout Ladder

**Client-IP and scheme derivation across two hops:**

```
client 178.1.2.3
  │
  ▼ Caddy (edge)            — knows the true remote addr (socket)
  │   sets: X-Forwarded-For: 178.1.2.3
  │         X-Forwarded-Proto: https
  │         Host: app.example.com
  ▼ Tengine (internal)       — set_real_ip_from 127.0.0.1; real_ip_header X-Forwarded-For
  │   $remote_addr is now 178.1.2.3
  │   appends itself: X-Forwarded-For: 178.1.2.3, 127.0.0.1
  ▼ FrankenPHP (app)         — servers { trusted_proxies static 127.0.0.1 }
  │   trusts ONLY the direct peer (Tengine); walks XFF right-to-left,
  │   stops at the first untrusted hop → client IP = 178.1.2.3 ✓
  ▼ application framework    — TRUSTED_PROXIES=127.0.0.1 (Symfony env / Laravel middleware)
```

Rules that make the chain safe:

1. **Each layer trusts exactly one hop** — its direct downstream peer, and nothing else. Never `trusted_proxies 0.0.0.0/0`.
2. Caddy **overwrites** (not appends) `X-Forwarded-For` with the socket peer; it is the authoritative origin of the chain.
3. Tengine restores `$remote_addr` via `real_ip` so its own `limit_req` and logs rate-limit and record the *real* client, not `127.0.0.1`.
4. The application must ALSO be told to trust its proxy (FrankenPHP-level `trusted_proxies` governs Caddy's header handling; the framework-level `TRUSTED_PROXIES` governs the PHP layer — both are required, per official production docs).

**Timeout ladder (prod defaults — always edge ≥ LB ≥ app):**

| Budget | Caddy (edge) | Tengine (LB) | FrankenPHP worker |
|---|---|---|---|
| Connect | 5s | 2s | — (loopback) |
| Read / response | 120s | 75s | 60s (PHP `max_execution_time`) |
| Idle keepalive | — | `keepalive_timeout 15s` (to workers: `keepalive 128` pool) | — |
| Total request budget | 120s hard ceiling | 75s | 60s |

Each layer's timeout exceeds its downstream's, so the innermost component always signals first and the outer layers relay a meaningful status instead of racing to 504. A request that dies at the worker surfaces as a FrankenPHP 503/log entry; at Tengine as 502/504 with upstream diagnostics; at Caddy only if the whole middle tier is down — which is exactly the failure the health-check + dyups machinery exists to prevent.

### 3.4 Port & Socket Registry (authoritative)

| Purpose | Bind | Owner | Exposure |
|---|---|---|---|
| Public HTTP (redirect) | `:80/tcp` | Caddy | Internet |
| Public HTTPS | `:443/tcp` + `:443/udp` (H3) | Caddy | Internet |
| Caddy admin API | `127.0.0.1:2020` | Caddy | Loopback (deliberately moved off 2019 — see §1.3) |
| Tengine app listener | `127.0.0.1:8081` | Tengine | Loopback |
| Tengine `dyups` interface | `127.0.0.1:8081/_anvil/dyups/` (allow 127.0.0.1 only) | Tengine | Loopback |
| Tengine `check_status` / `stub_status` | `127.0.0.1:8081/_anvil/upstream`, `/_anvil/stub` | Tengine | Loopback |
| FrankenPHP **blue** app | `127.0.0.1:8090` | frankenphp@blue | Loopback |
| FrankenPHP **green** app | `127.0.0.1:8091` | frankenphp@green | Loopback (only during deploys/rehearsals) |
| FrankenPHP blue admin | `127.0.0.1:2019` | frankenphp@blue | Loopback |
| FrankenPHP green admin | `127.0.0.1:2018` | frankenphp@green | Loopback |
| MySQL | container net / RDS endpoint | DEPLOY-02 | Private |
| Redis | container net / internal | DEPLOY-02 | Private |
| Anvil Web UI | `127.0.0.1:9999` | anvil-web | Loopback (SSH tunnel; v1 heritage) |
| phpMyAdmin | `127.0.0.1:8080` | compose | Loopback (v1 heritage) |
| node_exporter | `127.0.0.1:9100` | monitoring | Loopback |

> **Unix-socket option:** when Tengine and FrankenPHP share a host and a user, replacing `127.0.0.1:8090` with a unix socket (`/run/anvil/frankenphp-blue.sock`) removes ~30–80 µs of loopback TCP overhead per request and eliminates port collisions outright. Caddy supports unix listeners (`bind unix/<path>`) and `trusted_proxies_unix`; nginx-class `proxy_pass http://unix:/run/anvil/frankenphp-blue.sock;` is standard. Anvil v3 ships **loopback TCP as the default** (debuggable with `curl`, survives user-mismatch, works identically inside containers) and offers sockets as a `config/anvil.conf` toggle (`APP_TRANSPORT=unix`), documented in §7.5. Do not prematurely optimize: on a laptop or a 4-vCPU node the difference is noise next to PHP execution time.

### 3.5 When to Collapse Layers (documented fallbacks)

| Mode | Stack | When it is the right call | Trade-off you accept |
|---|---|---|---|
| **dev (default)** | FrankenPHP only | Daily laptop development | No buffering for slow clients (irrelevant locally), no dyups rehearsal |
| **dev parity** | Caddy + Tengine + FrankenPHP | Testing deploy mechanics, header chains, rate limits before a release | ~3 processes; full stack boot ~2s |
| **prod Option B (Caddy-only)** | Caddy edge → Caddy `reverse_proxy` → FrankenPHP | Minimal-footprint nodes (e.g. free-tier t3.micro with 1 GB RAM) where the middle tier's RAM (~20–40 MB) matters more than its features | Lose response buffering (slow clients hold workers), Tengine active health checks (Caddy's active checks partially cover), dyups blue/green (use Caddy's graceful `reload` + two upstreams instead), concat |
| **prod Option C (Tengine at edge)** | Tengine public | **Not sanctioned.** Listed only to be explicitly rejected: it re-exposes the fork-lag CVE class that §2.2 documents | Violates RULE T1 |

Option B is a *degradation mode*, not an equal alternative: Anvil v3 implements it by stopping the Tengine unit and pointing Caddy's `reverse_proxy` directly at the blue/green FrankenPHP upstreams (both Caddyfiles are generated from the same `anvil.conf` values). It exists so a constrained node still runs a supported configuration rather than an ad-hoc one.

---

## 4. Environment Matrix

| Concern | Development (Linux laptop) | Staging VM | Production / Edge |
|---|---|---|---|
| **Caddy** | Embedded in FrankenPHP (single process) | Standalone, Let's Encrypt **staging** CA | Standalone, Let's Encrypt prod + `on_demand_tls` |
| **Tengine** | Off (optional `stack full`) | On (full parity) | On (loopback-only) |
| **FrankenPHP** | Worker mode, `watch` hot reload, `num 2` | Worker mode, `num 4` | Worker mode, `num 2×vCPU`, `max_requests 500–1000` |
| **TLS** | mkcert `*.test` (+ dnsmasq) | ACME staging dir | ACME prod; on-demand per-tenant |
| **MySQL** | Docker 8.0/8.4 | Docker 8.4 | RDS (SSM-held creds) or local Docker |
| **Redis** | Docker 7 | Docker 7 | ElastiCache / managed or local |
| **Deploy** | `git pull` + hot reload | `anvil deploy staging` (blue/green rehearsal) | `anvil deploy production` (dyups swap, health-gated) |
| **Monitoring** | `loom top`, log tails | + node_exporter, smoke checks | + Prometheus scrape, HUB-31, alerts |
| **Tenants** | 1 (you) | 1–2 logical | Logical (`tenant_id`) + optional worker-per-tenant |
| **Cost** | $0 | ~$20–50/mo (VM) | t3.micro-safe default; see §7.1 cost note |
| **CVE watch** | `anvilctl doctor` on demand | Weekly, pre-deploy | Weekly ritual + on-advisory (§7.9) |

---

## 5. Development — Major Linux Laptops

### 5.1 Supported Distributions

| Distro | Versions | Package manager | Notes |
|---|---|---|---|
| **Ubuntu** | 24.04 LTS, 26.04 LTS | `apt` | Primary target. `systemd-resolved` stub-listener conflict handled by installer (RUNBOOK-ANVIL-DNS). |
| **Debian** | 13 (Trixie) | `apt` | Same engine as Ubuntu; older glibc is fine — FrankenPHP static binaries have no distro deps. |
| **Fedora** | 42, 43 | `dnf` | Secondary. SELinux: containers/daemons under `~/.local` may need `setsebool container_use_devices` / chcon, or `anvilctl doctor` will flag it. |
| **Arch Linux** | Rolling | `pacman` | Tertiary. `caddy` and `frankenphp-bin` (AUR) exist but **Anvil pins its own binaries** regardless. |
| **Architecture** | x86_64 **and** aarch64 | — | FrankenPHP publishes static linux-amd64 and linux-arm64 builds; Tengine 3.2.0 packages cover x86_64/aarch64. ARM Linux laptops (Snapdragon X class) are first-class. |

Nothing in the dev mode *requires* Docker: only the data tier (MySQL/Redis) uses it, and host-installed MySQL/Redis are equally supported (`anvil.conf: DATA_SOURCE=host|docker`).

### 5.2 Install the Runtime (FrankenPHP binary)

```bash
# Pin the version Anvil v3 mandates (floor 1.12.5; current 1.12.7 as of 2026-08-28).
# x86_64:
curl -fsSL -o /usr/local/bin/frankenphp \
  https://github.com/php/frankenphp/releases/download/v1.12.7/frankenphp-linux-x86_64
# aarch64 (ARM laptops):
curl -fsSL -o /usr/local/bin/frankenphp \
  https://github.com/php/frankenphp/releases/download/v1.12.7/frankenphp-linux-arm64
sudo chmod +x /usr/local/bin/frankenphp

# Verify BOTH the app-server version AND the embedded Caddy lineage:
frankenphp version
# Expect: FrankenPHP v1.12.7 PHP 8.5.x, Caddy 2.11.x-lineage
#   → embedded Caddy MUST be ≥ 2.11.1 (CVE-2026-27589). If your build shows
#     an older embedded Caddy, do not use that build — rebuild via the
#     official docker image or xcaddy recipe (frankenphp.dev/docs/config).
```

`anvilctl doctor` (§8.3) re-runs this check on every `anvilctl start` and refuses to boot an out-of-policy binary — the version floor is enforced, not advisory.

### 5.3 Data Tier

```bash
# MySQL 8 + Redis 7 (Anvil v1 heritage, trimmed to data-only):
docker compose -f anvil/dev/compose.data.yml up -d
# contents: mysql:8.4 (127.0.0.1:3306, volume anvil_mysql), redis:7 (internal net only)
# Credentials via .env with safe dev defaults — same contract as Anvil v1.

# Or use host-installed services (brew-less distros, podman-rootless, etc.):
#   anvil.conf: DATA_SOURCE=host ; DB_DSN / REDIS_URL point at unix sockets or localhost.
```

### 5.4 Local DNS + Trusted TLS (v1 heritage, kept deliberately)

```bash
# dnsmasq: *.test → 127.0.0.1   (installer handles systemd-resolved port-53 conflict:
#   /etc/systemd/resolved.conf.d/anvil.conf with DNSStubListener=no — see RUNBOOK-ANVIL-DNS)
echo 'address=/.test/127.0.0.1' | sudo tee /etc/dnsmasq.d/dglab-test

# mkcert: local CA trusted by browser + OS (unchanged from v1):
mkcert -install
mkcert -key-file   anvil/dev/tls/test-key.pem \
        -cert-file anvil/dev/tls/test.pem \
        "*.test" dglab.test localhost 127.0.0.1 ::1
```

Why mkcert and not `tls internal`: Firefox/Chromium on Linux trust mkcert's root (installed into NSS) with zero warnings, and the same cert file serves every `*.test` vhost — one cert, no per-project issuance, matching Anvil v1's UX. `tls internal` remains the zero-dependency fallback when mkcert is absent.

### 5.5 The Dev Caddyfile (single process — the whole stack)

```caddyfile
# anvil/dev/Caddyfile.dev — run with:
#   frankenphp run --config anvil/dev/Caddyfile.dev
{
    # Admin on the standard port in dev (nothing else claims it in collapsed mode).
    admin 127.0.0.1:2019

    # Dev conveniences: readable logs, no ACME (mkcert cert below).
    log {
        output stdout
        format console
        level DEBUG
    }

    frankenphp {
        worker {
            # DGLab front controller (ADR-016 layout: app/ + public/index.php)
            file public/index.php
            num 2                       # 2 workers — laptop-friendly
            env APP_ENV=dev
            env APP_DEBUG=true
            env DB_DSN="mysql:host=127.0.0.1;port=3306;dbname=dglab_dev"
            env REDIS_URL="redis://127.0.0.1:6379"
            # Hot reload: restart workers when sources change (dev only!)
            watch                       # defaults to watching **/*.{php,twig,yaml,yml,env}
        }
        # Recycle threads to bound leaks during long dev sessions (experimental).
        max_requests 1000
        php_ini "display_errors" "1"
        php_ini "opcache.enable" "1"
        php_ini "opcache.validate_timestamps" "1"   # dev: recompile on change
        php_ini "opcache.revalidate_freq" "0"
    }
}

https:// {
    # Wildcard dev cert issued by mkcert in §5.4:
    tls anvil/dev/tls/test.pem anvil/dev/tls/test-key.pem

    root * public/
    encode zstd gzip

    php_server {
        try_files {path} {path}/index.php index.php
    }
    file_server
}
```

Behavioral notes:

- **`watch`** restarts workers on file change — this is FrankenPHP's documented hot-reload path and replaces any custom inotify restart loop. The v1 `vhost-watcher` inotify tool survives only for `www/` project registration, not code reloads.
- **`num 2`** on a laptop keeps RAM predictable (each worker holds the full app + opcache). Raise to 4 on 16 GB+ machines with `ANVIL_DEV_WORKERS`.
- One Caddyfile serves **all** `*.test` projects simultaneously (the TLS cert is a wildcard), so DGLab Core/Hub/Bridge surfaces (`dglab.test`, `api.dglab.test`, …) coexist without per-vhost files — the v1 `lib/vhost.sh` render step disappears in dev.
- **Xdebug:** step-debugging in worker mode is constrained (the worker loop is not per-request). For breakpoint sessions, flip to classic mode temporarily: comment out the `frankenphp { worker ... }` block and run the same Caddyfile — FrankenPHP then executes per request like PHP-FPM, and `XDEBUG_SESSION` works as always. Worker mode stays the default because ADR-017's Fiber/Pulse model (and its `singleton()`/`pulse()` semantics) only behaves canonically under workers.

### 5.6 Full-Stack Parity Mode (`anvilctl stack full`)

```bash
anvilctl stack full     # boots: caddy (edge, :80/:443) + tengine (127.0.0.1:8081) + frankenphp (:8090)
anvilctl stack slim     # back to collapsed single-process dev (default)
```

Parity mode reuses the *staging* config templates (§6.2) with two substitutions: `*.test` mkcert TLS instead of ACME, and dev worker counts. It exists to rehearse header chains (`X-Forwarded-For` walks, §3.3), rate-limit zones, and blue/green swaps on the laptop before they matter on a VM.

### 5.7 Daily Workflow (dev)

```bash
anvilctl start          # data tier up + frankenphp run (foreground logs) — or `anvilctl start -d`
anvilctl stop           # stop everything (also stops its own Web UI server — known v1 behavior)
anvilctl status         # versions, floors, listener map, worker count
anvilctl doctor         # CVE-floor check (Caddy lineage, FrankenPHP, Tengine when present)
anvilctl logs [svc]     # frankenphp | caddy | tengine | data
anvilctl db create foo  # injection-safe MySQL create (v1 heritage)
anvilctl watch          # inotify on www/ → register/unregister projects (v1 heritage)
```

### 5.8 systemd User Units (optional but recommended)

```ini
# ~/.config/systemd/user/anvil-dev.service
[Unit]
Description=Anvil v3 dev runtime (FrankenPHP collapsed mode)
After=docker.service

[Service]
WorkingDirectory=%h/dev/DGLab
ExecStart=/usr/local/bin/frankenphp run --config anvil/dev/Caddyfile.dev
ExecReload=/usr/local/bin/frankenphp reload --config anvil/dev/Caddyfile.dev
Restart=on-failure

[Install]
WantedBy=default.target
```

`systemctl --user enable --now anvil-dev` gives dev the same restart semantics prod has (§7.6), so "did the unit restart it?" is never a surprise differential between environments.

---

## 6. Staging VM

### 6.1 Specification

| Resource | Minimum | Recommended |
|---|---|---|
| vCPU | 2 | 4 |
| RAM | 4 GB | 8 GB |
| Disk | 20 GB SSD | 40 GB SSD |
| OS | Ubuntu 24.04 LTS (primary) / Debian 13 | same |
| Network | Public IPv4 (+ IPv6) | same |
| Provider | AWS `t3.medium` / Hetzner CX22 / DO s-2vcpu-4gb | Hetzner for cost (v2 decision log) |

```bash
anvil provision staging \
  --provider aws|hetzner|digitalocean \
  --region eu-west-2 \
  --tls staging \
  --workers 4
# Installs pinned Caddy 2.11.4, Tengine 3.2.0 (loopback-only), FrankenPHP 1.12.7;
# writes the three systemd units (§7.6); deploys current branch; gates on /health.
```

### 6.2 Configs (staging == prod with three knobs)

Staging runs **the exact production files** of §7.3–§7.5 with only:

1. `acme_ca` pointed at the Let's Encrypt **staging** CA (in the edge Caddyfile global block — `acme_ca https://acme-staging-v02.api.letsencrypt.org/directory`) so rate limits are never burned by rehearsals;
2. `num 4` workers (instead of `2×vCPU`) and `APP_ENV=staging`;
3. `deploy` commands target the staging pool.

Everything else — port registry, trust chain, buffering, dyups, health checks — is byte-identical to prod. This is deliberate: a staging environment that differs from prod in anything but those three knobs cannot rehearse the prod runbook honestly (DEPLOY-04's same-digest-promotion principle applied to config).

### 6.3 Staging Validation Gates (per release candidate)

1. **Boot:** all three units `active (running)`; `anvilctl doctor` green (version floors).
2. **Health:** `/health` returns 200 within 5 s (HUB-15 contract); Tengine `check_status` shows upstream `up`, `rise ≥ 2`.
3. **Header chain:** a test response echoes the correct client IP and `https` scheme (§3.3 assertions scripted in `anvilctl verify headers`).
4. **Blue/green rehearsal:** `anvil deploy staging` executes a full dyups swap with zero non-2xx during a 30 s, 50 rps load pass (k6 or `hey`).
5. **Failure drills:** kill the active worker pool → Tengine `check` marks it `down` within `fall 3` (≤ 9 s at `interval 3000`) and Caddy serves a clean 502 page, never a hang; restore → pool rejoins automatically.

A release candidate that passes 1–5 is promoted by digest; one that fails any gate is rejected on staging — never "fixed in prod" (STRUCTURE-08 §1: rollback requires no human decision).

---

## 7. Production / Edge Node

### 7.1 Specification and Cost Discipline

| Resource | Minimum | Recommended |
|---|---|---|
| vCPU | 2 | 4–8 (Graviton `t4g.*` where AWS) |
| RAM | 2 GB | 8–16 GB |
| Disk | 20 GB | 40–100 GB NVMe |
| OS | Ubuntu 24.04 LTS (primary) / Debian 13 / Amazon Linux 2023 | same |
| Network | 80/tcp + 443/tcp + 443/udp public; SSH from ops CIDR only | + IPv6 |
| Arch | x86_64 | aarch64 fully supported (all three components ship ARM builds/packages) |

**Cost discipline (v1 heritage, unchanged):** the safe default remains `t3.micro` (`INSTANCE_TYPE` in `anvil.conf`); `t3.small` requires `--confirm-t3-small` with the printed ~$15/mo warning, per the account-date rule in the v1 README. A 2 GB node runs the full trio comfortably: Caddy ~20 MB, Tengine ~30–40 MB (buffers included), FrankenPHP ~80–150 MB per worker. If RAM is the constraint, Option B (§3.5) drops the middle tier — do not instead shrink workers below `num 2`, or ADR-017's scheduler loses its concurrency headroom.

### 7.2 Firewall Matrix (the enforcement of RULE T1)

| Port | Open to | Owner |
|---|---|---|
| 22/tcp | ops CIDR `/32` only (auto-detected or `--ssh-cidr`; v1 rule — never `0.0.0.0/0`) | sshd |
| 80/tcp | world (redirect only) | Caddy |
| 443/tcp + 443/udp | world (H2 + H3) | Caddy |
| 3306, 6379 | EC2 SG id / private subnet only (v1 rule; RDS never public) | data tier |
| 8081, 8090, 8091, 2018–2020, 9999, 8080, 9100 | **loopback only — no SG rule at all** | internal |

On the host itself, the loopback-only rule is double-enforced: the services bind `127.0.0.1` explicitly (configs below), and a host firewall (ufw/nftables baseline from `anvil provision`) denies forwarded access to internal ports even from the VPC if the operator misconfigures the SG later.

### 7.3 Caddy — Edge Configuration (`/etc/anvil/edge/Caddyfile`)

```caddyfile
# Anvil v3 — Caddy edge. Stock caddy 2.11.4 binary, NO custom modules
# (deliberate: plugin-free binaries keep the CVE floor auditable; rate
# limiting lives at the Tengine tier where limit_req is built in).
{
    # Admin moved OFF 2019 (reserved for FrankenPHP blue) and locked down:
    # origins+enforce_origin harden the admin CSRF class (CVE-2026-27589)
    # even on builds already carrying the 2.11.1 fix — defense in depth.
    admin 127.0.0.1:2020 {
        origins http://127.0.0.1:2020 http://localhost:2020
        enforce_origin
    }

    email ops@dglab.example

    # Staging knob (§6.2): swap the CA for rehearsals.
    # acme_ca https://acme-staging-v02.api.letsencrypt.org/directory

    # On-Demand TLS for tenant vhosts: Caddy asks the app (via Tengine)
    # whether a certificate may be issued for an incoming SNI name.
    # Required in production to prevent ACME abuse; 200 => allow, else deny.
    on_demand_tls {
        ask http://127.0.0.1:8081/_anvil/tls-allowed
    }

    servers {
        protocols h1 h2 h3
        timeouts {
            read_body 30s
            write 120s
            idle 2m
        }
    }
}

# ---------- snippets ----------
(security_headers) {
    header {
        Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
        X-Content-Type-Options "nosniff"
        X-Frame-Options "DENY"
        Referrer-Policy "strict-origin-when-cross-origin"
        -Server
    }
}

# ---------- primary platform site (fixed cert via normal ACME) ----------
dglab.example.com {
    import security_headers
    encode zstd gzip

    # Structured access logs to journald (vector/promtail ship from there):
    log {
        output stdout
        format json
    }

    # Everything to the internal LB. Caddy sets/overwrites
    # X-Forwarded-For / X-Forwarded-Proto / Host automatically.
    reverse_proxy 127.0.0.1:8081 {
        transport http {
            keepalive 30s
            keepalive_idle_conns 64
            keepalive_idle_conns_per_host 64
            response_header_timeout 90s
            dial_timeout 5s
        }
        # Health-gated fail-fast if the whole middle tier dies:
        health_uri /_anvil/ping
        health_interval 10s
        health_timeout 2s
        health_status 200
    }
}

# ---------- tenant / dynamic vhosts (on-demand certificates) ----------
# The successor of Anvil v1's www/ + vhost-watcher: any host reaching this
# catch-all gets a certificate issued iff the app's ask-endpoint approves
# (tenant registered in anvil's project registry). No vhost files, no reload.
https:// {
    import security_headers
    tls {
        on_demand
    }
    log {
        output stdout
        format json
    }
    reverse_proxy 127.0.0.1:8081 {
        transport http {
            keepalive 30s
            keepalive_idle_conns 64
        }
    }
}

# HTTP/80 exists only to redirect (and to answer ACME HTTP-01 if ever needed).
http:// {
    redir https://{host}{uri} permanent
}
```

Operational notes:

- **No docroot at the edge** (§3.2 design note) — Caddy holds certificates and nothing else.
- `caddy validate --config` runs in CI and in `anvilctl doctor`; `caddy reload` (via the admin socket) applies changes with zero dropped connections.
- The `ask` endpoint protects ACME rate limits: an SNI name nobody registered simply never gets a cert.
- Optional future: build with the `caddy-ratelimits` module for edge-level per-IP limits *in front of* Tengine's zones. Not in v3's critical path — Tengine's `limit_req` is the sanctioned rate limiter.

### 7.4 Tengine — Internal LB Configuration (`/etc/anvil/lb/tengine.conf`)

```nginx
# Anvil v3 — Tengine 3.2.0+ internal load balancer.
# Loopback-only. No TLS. No public socket. (RULE T1)
user  tengine;
worker_processes  auto;
worker_rlimit_nofile 65535;
error_log  /var/log/anvil/tengine-error.log warn;
pid        /run/anvil/tengine.pid;

events {
    worker_connections 8192;
    # reuseport: kernel-level connection sharding across workers
    # (Tengine feature; enable on the listen line below).
    multi_accept on;
}

http {
    include       mime.types;
    default_type  application/octet-stream;

    # ---- access log: real client IP (restored via real_ip below) ----
    log_format anvil '$remote_addr - "$request" $status $body_bytes_sent '
                     'rt=$request_time uct=$upstream_connect_time '
                     'urt=$upstream_response_time uid=$sent_http_x_request_id';
    access_log /var/log/anvil/tengine-access.log anvil;

    sendfile      on;
    tcp_nopush    on;
    tcp_nodelay   on;
    keepalive_timeout  15s;
    client_max_body_size 25m;
    server_tokens off;

    gzip on;
    gzip_types text/css application/javascript application/json image/svg+xml;
    gzip_min_length 1024;

    # ---- THE POINT OF THIS TIER: slow-client shielding + pooling ----
    proxy_buffering      on;
    proxy_buffer_size    16k;
    proxy_buffers        32 16k;
    proxy_busy_buffers_size 64k;
    proxy_connect_timeout 2s;
    proxy_send_timeout    60s;
    proxy_read_timeout    75s;
    proxy_http_version    1.1;

    # ---- rate limiting against the REAL client IP ----
    limit_req_zone $binary_remote_addr zone=per_ip:10m   rate=20r/s;
    limit_req_zone $http_host          zone=per_host:10m rate=100r/s;
    limit_conn_zone $binary_remote_addr zone=conn_per_ip:10m;
    limit_req_status 429;
    limit_conn_status 429;

    # ---- dyups state (persists dynamic upstreams across reloads) ----
    dyups_upstream_conf /var/lib/anvil/dyups;

    # ---- THE worker pool (mutated at runtime by `anvil deploy`, §7.7) ----
    upstream frankenphp {
        server 127.0.0.1:8090 max_fails=3 fail_timeout=10s;
        keepalive 128;

        # Active health checks (Tengine ngx_http_upstream_check_module).
        check interval=3000 rise=2 fall=3 timeout=2000 type=http;
        check_http_send "GET /health HTTP/1.0\r\nHost: health.local\r\n\r\n";
        check_http_expect_alive http_2xx http_3xx;
    }

    server {
        listen 127.0.0.1:8081 reuseport;
        server_name _;

        # Caddy is the only client this tier will ever have:
        set_real_ip_from 127.0.0.1;
        real_ip_header   X-Forwarded-For;

        # ---- internal, loopback-only management endpoints ----
        location = /_anvil/ping {
            access_log off;
            return 200 "ok\n";
        }

        # dyups runtime API — the blue/green switch (§7.7).
        location /_anvil/dyups/ {
            dyups_interface;
            allow 127.0.0.1;
            deny  all;
        }

        # Active-check dashboard (scraped by monitoring; never proxied out).
        location = /_anvil/upstream {
            check_status;
            allow 127.0.0.1;
            deny  all;
        }

        location = /_anvil/stub {
            stub_status;
            allow 127.0.0.1;
            deny  all;
        }

        # ---- on_demand_tls ask endpoint → the app decides (§7.3) ----
        location = /_anvil/tls-allowed {
            proxy_pass http://frankenphp;
            proxy_set_header Host $host;
            access_log off;
        }

        # ---- health passthrough (used by staging gates + drills) ----
        location = /health {
            proxy_pass http://frankenphp;
            proxy_set_header Host $host;
            access_log off;
        }

        # ---- built static assets from the immutable release ----
        # (concat module available here for Wheel asset merging, ISPOKE-05)
        location ~* \.(css|js|mjs|png|jpg|jpeg|gif|ico|svg|webp|avif|woff|woff2|ttf|map)$ {
            root /opt/anvil/current/public;
            expires 1y;
            add_header Cache-Control "public, immutable";
            access_log off;
            try_files $uri =404;
        }

        # ---- everything else → the worker pool ----
        location / {
            limit_req  zone=per_ip  burst=40 nodelay;
            limit_req  zone=per_host burst=200 nodelay;
            limit_conn conn_per_ip 20;

            proxy_pass http://frankenphp;
            proxy_set_header Connection "";
            proxy_set_header Host              $host;
            proxy_set_header X-Real-IP         $remote_addr;
            proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto https;
            proxy_set_header X-Request-Id      $request_id;
        }
    }
}
```

Operational notes:

- `X-Forwarded-Proto` is hardcoded `https` — at this tier, scheme is a fact established at the edge, not something to infer per-request.
- `$request_id` propagates through to the app so a single id appears in Caddy, Tengine, and FrankenPHP logs — the cross-hop debugging key (§9).
- The `check` module's active probes (`/health`, 3 s interval, `fall 3`) mean a dead blue pool is evicted in ≤ 9 s **independently of dyups** — active checks and blue/green are complementary safety layers.
- `tengine -t` validates; `kill -HUP $(cat /run/anvil/tengine.pid)` reloads gracefully. Config changes are rare by design: this file should be boring.

### 7.5 FrankenPHP — App Configuration (`/etc/anvil/app/Caddyfile.blue`)

```caddyfile
# Anvil v3 — FrankenPHP blue (active) pool. The green file is identical
# except: listen :8091, admin 127.0.0.1:2018.
{
    # CVE-2026-27589 posture: loopback bind + origin enforcement, on top of
    # the fixed embedded Caddy (verified ≥ 2.11.1 lineage by anvilctl doctor).
    admin 127.0.0.1:2019 {
        origins http://127.0.0.1:2019 http://localhost:2019
        enforce_origin
    }

    servers {
        # Trust ONLY the direct peer — Tengine on this host (§3.3).
        trusted_proxies static 127.0.0.1
        timeouts {
            read_body 30s
            write 90s
            idle 3m
        }
    }

    frankenphp {
        worker {
            # Immutable release front controller (ADR-016 layout):
            file /opt/anvil/current/public/index.php
            num 8                        # 2 × 4 vCPU; floor 2 on tiny nodes
            name dglab
            # Secrets NEVER live here — they arrive via the systemd unit's
            # EnvironmentFile (SSM-sourced, §7.8) and are inherited by workers.
            env APP_ENV=production
            env APP_URL=https://dglab.example.com
            env TRUSTED_PROXIES=127.0.0.1   # framework-side trust (§3.3)
            max_consecutive_failures 10     # auto-restart flapping workers
        }

        # Global thread recycling — bounds leaks in long-lived workers
        # (experimental option; global level, NOT inside worker{}):
        max_requests 500

        php_ini "opcache.enable" "1"
        php_ini "opcache.memory_consumption" "256"
        php_ini "opcache.max_accelerated_files" "20000"
        php_ini "opcache.validate_timestamps" "0"   # releases are immutable
        php_ini "opcache.preload" "/opt/anvil/current/config/preload.php"  # ADR-010
        php_ini "opcache.preload_user" "anvil"
        php_ini "expose_php" "0"
        php_ini "memory_limit" "256M"
    }
}

:8090 {
    # Worker-mode app entry. Static files are primarily Tengine's job (§7.4);
    # file_server stays as the correctness fallback for anything the LB's
    # regex misses (e.g. dotfiles, unusual extensions).
    root * /opt/anvil/current/public
    php_server {
        try_files {path} {path}/index.php index.php
    }
    file_server
}
```

Operational notes:

- **`validate_timestamps 0` + symlink swap** is the prod pairing: releases are immutable directories (`/opt/anvil/releases/<digest>`), `/opt/anvil/current` is a symlink, and a deploy flips the symlink *before* the dyups switch so the green pool boots from the new digest. No in-place edits ever (STRUCTURE-08).
- **Metrics:** the admin API serves Prometheus metrics (`/metrics` on the admin port) — Caddy runtime metrics plus FrankenPHP's `frankenphp_total_threads` / `frankenphp_busy_threads` — and `/frankenphp/threads` returns a JSON snapshot of every PHP thread (worker name, state, current request) for live debugging. This is the data source that makes `loom top` real (§7.9).
- **Worker count math:** `num` defaults to 2×CPU. On shared edge nodes, prefer explicit `num 2×vCPU` and let Tengine queue bursts — 416 pending connections in the keepalive pool cost kilobytes; 16 PHP workers each holding a Fiber scheduler cost hundreds of megabytes.
- **Unix-socket variant** (`APP_TRANSPORT=unix` in `anvil.conf`): replace `:8090` with a `bind unix//run/anvil/frankenphp-blue.sock` listener, set `trusted_proxies_unix`, and change Tengine to `proxy_pass http://unix:/run/anvil/frankenphp-blue.sock;`. Gains ~30–80 µs/request; adopt only after profiling shows loopback TCP on the hot path (§3.4).

### 7.6 systemd Units (hardened)

```ini
# /etc/systemd/system/anvil-caddy.service
[Unit]
Description=Anvil v3 edge (Caddy)
After=network-online.target
Wants=network-online.target

[Service]
Type=notify
User=caddy
Group=caddy
ExecStart=/usr/local/bin/caddy run --environ --config /etc/anvil/edge/Caddyfile
ExecReload=/usr/local/bin/caddy reload --config /etc/anvil/edge/Caddyfile
TimeoutStopSec=10s
Restart=on-failure
RestartSec=2s
# Cert storage is the only writable path:
StateDirectory=caddy
ReadWritePaths=/var/lib/caddy
# Bind 80/443 without running as root:
AmbientCapabilities=CAP_NET_BIND_SERVICE
CapabilityBoundingSet=CAP_NET_BIND_SERVICE
NoNewPrivileges=true
ProtectSystem=strict
ProtectHome=true
PrivateTmp=true
PrivateDevices=true
ProtectKernelTunables=true
ProtectKernelModules=true
ProtectControlGroups=true
RestrictAddressFamilies=AF_INET AF_INET6 AF_UNIX
RestrictNamespaces=true
LockPersonality=true
MemoryDenyWriteExecute=true
RestrictRealtime=true
SystemCallArchitectures=native

[Install]
WantedBy=multi-user.target
```

```ini
# /etc/systemd/system/anvil-tengine.service
[Unit]
Description=Anvil v3 internal LB (Tengine)
After=network-online.target anvil-caddy.service

[Service]
Type=forking
User=tengine
Group=tengine
PIDFile=/run/anvil/tengine.pid
ExecStartPre=/usr/local/tengine/sbin/nginx -t -c /etc/anvil/lb/tengine.conf
ExecStart=/usr/local/tengine/sbin/nginx -c /etc/anvil/lb/tengine.conf
ExecReload=/usr/local/tengine/sbin/nginx -s reload -c /etc/anvil/lb/tengine.conf
ExecStop=/usr/local/tengine/sbin/nginx -s quit -c /etc/anvil/lb/tengine.conf
Restart=on-failure
# No capabilities needed: loopback, unprivileged ports only.
NoNewPrivileges=true
ProtectSystem=strict
ReadWritePaths=/var/log/anvil /var/lib/anvil /run/anvil
ProtectHome=true
PrivateTmp=true
RestrictAddressFamilies=AF_INET AF_INET6 AF_UNIX
SystemCallArchitectures=native

[Install]
WantedBy=multi-user.target
```

```ini
# /etc/systemd/system/anvil-frankenphp@.service   (%i = blue | green)
[Unit]
Description=Anvil v3 app server (FrankenPHP %i)
After=network-online.target anvil-secrets.service
Requires=anvil-secrets.service

[Service]
Type=notify
User=anvil
Group=anvil
WorkingDirectory=/opt/anvil/current
EnvironmentFile=/etc/anvil/secrets.env
ExecStart=/usr/local/bin/frankenphp run --config /etc/anvil/app/Caddyfile.%i
ExecReload=/usr/local/bin/frankenphp reload --config /etc/anvil/app/Caddyfile.%i
TimeoutStopSec=30s          # let in-flight Fibers finish during deploys
KillSignal=SIGTERM
Restart=on-failure
RestartSec=2s
NoNewPrivileges=true
ProtectSystem=strict
ReadWritePaths=/opt/anvil/current/var   # app writes (var/cache, var/log)
ProtectHome=true
PrivateTmp=true
RestrictAddressFamilies=AF_INET AF_INET6 AF_UNIX
SystemCallArchitectures=native
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
```

Boot order: `anvil-secrets.service` (§7.8) → `anvil-frankenphp@blue` → `anvil-tengine` → `anvil-caddy`. Only blue is enabled at boot; green is a deploy-time unit (`systemctl start anvil-frankenphp@green`).

### 7.7 Zero-Downtime Deploy Runbook (blue/green via dyups)

`anvil deploy production` automates all steps; the manual equivalent:

```bash
# 0. Preconditions
anvilctl doctor                     # version floors green
systemctl is-active anvil-caddy anvil-tengine anvil-frankenphp@blue

# 1. Ship the immutable release (CI or anvil)
rsync -a --delete releases/<digest>/ user@edge:/opt/anvil/releases/<digest>/
# release contains: app/ + public/ + config/preload.php + composer deps
# (built with composer install --no-dev --classmap-authoritative)

# 2. Boot GREEN on the new digest (blue keeps serving)
ln -sfn /opt/anvil/releases/<digest> /opt/anvil/current-green
# (unit template consumes Caddyfile.green; its root points at current-green)
systemctl start anvil-frankenphp@green

# 3. Health-gate green STRICTLY (auto-rollback rule — STRUCTURE-08 §1)
for i in $(seq 1 30); do
  curl -fsS http://127.0.0.1:8091/health && break || sleep 1
done
curl -fsS http://127.0.0.1:8091/health >/dev/null || {
  systemctl stop anvil-frankenphp@green; echo "DEPLOY REJECTED at health gate"; exit 1; }
# Tengine's check module must also see the green pool — verify:
curl -s http://127.0.0.1:8081/_anvil/upstream | grep -q 'up' || exit 1

# 4. THE SWITCH — one loopback POST, zero dropped connections:
curl -s -X POST --data 'server 127.0.0.1:8091 max_fails=3 fail_timeout=10s;' \
     http://127.0.0.1:8081/_anvil/dyups/upstream/frankenphp
# dyups updates the live upstream; in-flight requests on blue complete.

# 5. Smoke through the FULL chain (public URL, H2/H3):
curl -fsS https://dglab.example.com/health || {
    # AUTO-ROLLBACK: swap back; no human decision required.
    curl -s -X POST --data 'server 127.0.0.1:8090 max_fails=3 fail_timeout=10s;' \
         http://127.0.0.1:8081/_anvil/dyups/upstream/frankenphp
    exit 1
}

# 6. Drain & retire blue (30 s grace; SIGTERM lets Fibers finish)
sleep 30 && systemctl stop anvil-frankenphp@blue

# 7. Blue becomes green next release (roles swap; :8090/:8091 alternate)
```

Why dyups rather than a Tengine reload for the switch: `nginx -s reload` re-reads the whole config and re-forks workers (a heavier, all-config event), while a dyups POST mutates exactly one upstream in the running master — minimal blast radius, scriptable, and observable via `/_anvil/upstream`. Tengine's active `check` probes continue guarding the new pool independently of the switch.

### 7.8 Secrets, Backups, Logs

**Secrets (v1 contract, restated for v3):** RDS/host credentials live in SSM Parameter Store `SecureString` paths (`/anvil/rds/{host,user,password,database}`), never in the repo, never in a Caddyfile. A tiny generator unit runs before the app boots:

```ini
# /etc/systemd/system/anvil-secrets.service
[Unit]
Description=Anvil v3 secrets fetch (SSM → EnvironmentFile)
Before=anvil-frankenphp@blue.service anvil-frankenphp@green.service
[Service]
Type=oneshot
ExecStart=/opt/anvil/lib/fetch-secrets.sh     # aws ssm get-parameters → /etc/anvil/secrets.env (0600)
RemainAfterExit=yes
```

The instance role stays minimal (`ssm:GetParameter` on `/anvil/rds/*` only — v1 IAM policy names carried forward). When HUB-20 (Sovereign Vault) lands, this unit is the single swap point.

**Backups:** nightly `mysqldump` (or `mariadb-dump`/physical snapshots per DEPLOY-02) → encrypted object storage, 7 daily + 4 weekly retention, restore rehearsal quarterly under ISPOKE-24's contract (a backup that has never been restored is a rumor, not a backup).

**Logs:** all three units log to journald (structured JSON from Caddy/FrankenPHP, the `anvil` format from Tengine §7.4). A promtail/vector shipper forwards to Loki (or CloudWatch on AWS). The `$request_id` key (§7.4) is the join key across all three tiers.

### 7.9 Monitoring & CVE Watch

| Signal | Source | Scrape/Alert |
|---|---|---|
| Node health | `node_exporter` (127.0.0.1:9100) | CPU/RAM/disk/fd alerts |
| Edge | Caddy admin `127.0.0.1:2020` (`/metrics` on admin API) | 5xx ratio, request latency, cert expiry |
| Internal LB | Tengine `/_anvil/stub` (via nginx-prometheus-exporter) + `/_anvil/upstream` (check dashboard) | upstream down/failures, 429 rate, queue depth |
| App | FrankenPHP admin `127.0.0.1:2019/metrics` → `frankenphp_total_threads`, `frankenphp_busy_threads`; `/frankenphp/threads` JSON | worker saturation (busy/total > 0.9 sustained), restart storms, `max_requests` recycle rate |
| Business | HUB-31 Analytics + `loom top`/`loom ps`/`loom pulse:trace` (live, from the same thread state) | Pulse latency, tenant load |

**The weekly CVE ritual (15 minutes, calendarized):**

```bash
anvilctl doctor           # compares running versions against floors in
                          # config/versions.env (§8.2) — the floors file is
                          # the only place versions live
```

Then check the three upstream advisory feeds (nginx security advisories — Tengine inherits its CVE surface; Caddy releases; FrankenPHP releases). On any advisory touching a running component: raise the floor in `config/versions.env`, roll the binary (a Caddy/FrankenPHP bump is a unit restart in a blue/green window — §7.7 mechanics apply to the runtime itself; a Tengine bump additionally requires `tengine -t` + HUP). **Standing posture on the next Rift-class event:** if the fixed Tengine release is not yet out, Option B (§3.5) — stop the Tengine unit, point Caddy directly at the FrankenPHP pools, and schedule the restore. That maneuver is precisely why Option B is implemented and rehearsed rather than theoretical.

### 7.10 Multi-Tenant Isolation (ADR-017 consequence)

ADR-017 is explicit: Fibers share one PHP heap; isolation is *logical* (`tenant_id` scoping, cache key prefixes, scoped paths), and memory-level isolation requires host boundaries. Anvil v3 maps that to runtime choices, in escalating order of cost:

1. **Default — shared pool:** one FrankenPHP pool, logical tenancy. Cheapest; matches current Milestone-0 reality.
2. **Per-tenant worker pools (FrankenPHP native):** the `worker` block may be declared per server (per the config docs), so a noisy or high-risk tenant can get a dedicated pool (`num 2`) inside the same process — CPU threads separated, heap still shared.
3. **Per-tenant processes:** separate `anvil-frankenphp@<tenant>` template instances on distinct loopback ports, each in its own systemd unit with its own user — real memory isolation. Tengine upstreams (one per tenant) and Caddy host-based routing make this invisible to the outside.
4. **Per-tenant containers:** full DEPLOY-01 path for the eventual multi-region edge; Anvil's dyups/Caddy model extends unchanged (upstreams become `172.x` bridge addresses instead of loopback).

On-demand certificates (§7.3) compose with all four levels: the `ask` endpoint consults the same registry that defines "which tenants exist," so TLS policy and isolation policy derive from one source of truth.

---

## 8. Anvil v3 Tool Re-Implementation

### 8.1 What Changes

| Component | v1 (legacy) | v2 draft (2026-08-26) | **v3 (this document)** |
|---|---|---|---|
| Runtime | PHP-FPM 8.3 (Docker) | FrankenPHP | **FrankenPHP 1.12.x (worker mode)** |
| Edge | nginx (mkcert certs, rendered vhosts) | Tengine (public, certbot) | **Caddy 2.11.x (ACME/on-demand, stock binary)** |
| Internal LB | — (nginx was both) | — | **Tengine 3.2.0 (loopback-only, dyups/check/buffering)** |
| Local dev | compose stack + dnsmasq + mkcert + inotify watcher | FrankenPHP standalone | **Single FrankenPHP process + v1's dnsmasq/mkcert; `stack full` parity mode** |
| TLS (prod) | certbot webroot + rendered vhosts | certbot for Tengine | **Caddy ACME (certbot deleted); on-demand for tenants** |
| Deploys | none (manual) | dyups (edge) | **dyups blue/green at the LB tier, health-gated, auto-rollback** |
| Config | shell vars (`anvil.conf`) | `{env}.yml` (planned) | **shell vars (kept — v1's sourcing contract is battle-tested) + `versions.env` floor file** |
| Engine | bash `lib/*.sh` + 3 skins | same | **same "one engine, three skins" principle — unchanged** |

The v1 principles that survive verbatim: one shared bash engine with thin skins (`anvilctl` / TUI / Web UI), idempotent installer, loopback-only Web UI (SSH tunnel on remote hosts), SSM-held RDS credentials, the t3.small cost gate, the billing alarm, injection-safe `db create`, and the honest-limitations register.

### 8.2 Directory Structure (target)

```
anvil/
├── README.md                       # rewritten for v3 (this doc becomes the reference)
├── install.sh                      # idempotent bootstrap; installs pinned binaries
├── bin/
│   ├── anvilctl                    # CLI dispatcher (same contract, new subcommands)
│   └── fetch-secrets.sh            # SSM → /etc/anvil/secrets.env (used by systemd unit)
├── config/
│   ├── anvil.conf                  # bash-sourcable engine config (v1 contract, extended)
│   └── versions.env                # PINNED FLOORS: CADDY=2.11.4 TENGINE=3.2.0 FRANKENPHP=1.12.7
│                                   # (the single source of truth anvilctl doctor enforces)
├── lib/                            # THE ENGINE (one engine, three skins — unchanged principle)
│   ├── core.sh                     # helpers (logging, require, die)
│   ├── caddy.sh                    # NEW: edge lifecycle, validate, reload, on-demand ask checks
│   ├── tengine.sh                  # NEW: internal LB lifecycle, config gen, dyups wrapper, check_status poller
│   ├── frankenphp.sh               # NEW: app lifecycle (blue/green template), worker status via admin API
│   ├── tls.sh                      # REWRITTEN: mkcert (dev) — prod TLS now lives entirely in Caddy
│   ├── docker.sh                   # data-tier compose control (trimmed from v1)
│   ├── db.sh                       # MySQL create (v1, unchanged semantics)
│   ├── deploy.sh                   # NEW: §7.7 runbook as code (health gates, dyups swap, auto-rollback)
│   ├── verify.sh                   # NEW: header-chain, port-registry, and staging-gate assertions (§6.3)
│   ├── ec2.sh                      # v1 provision/tunnel/billing/alarm (carried forward)
│   └── web.sh                      # Web UI server control (v1, unchanged)
├── dev/
│   ├── Caddyfile.dev               # §5.5 collapsed-mode dev config
│   ├── compose.data.yml            # MySQL 8.4 + Redis 7 only
│   └── tls/                        # mkcert output (*.test)
├── edge/
│   └── Caddyfile                   # §7.3 (staging overrides via anvil.conf vars)
├── lb/
│   ├── tengine.conf                # §7.4 (rendered from the same vars — ports never hardcoded twice)
│   └── tengine.build.sh            # source build w/ dyups+check+concat (fallback when distro
│                                   # packages are unavailable; 3.2.0 ships x86_64+aarch64 packages)
├── app/
│   ├── Caddyfile.blue              # §7.5 (+ Caddyfile.green — generated from blue by lib/frankenphp.sh)
│   └── php/                        # php.ini fragments + preload.php template (ADR-010)
├── systemd/
│   ├── anvil-caddy.service         # §7.6
│   ├── anvil-tengine.service
│   ├── anvil-frankenphp@.service
│   └── anvil-secrets.service
├── scripts/
│   ├── vhost-watcher.sh            # v1 inotify watcher — now registers tenants + triggers
│   │                               #   on-demand-cert allow-list, not vhost renders
│   └── deploy-smoke.sh             # public-URL smoke suite used by lib/deploy.sh
├── tui/  anvil-tui.sh              # menu skin (options updated)
├── web/  ...                       # Web UI skin (v1 structure; new panels: stack, deploy, doctor)
├── provisioning/                   # ec2-provision.sh, rds-tunnel.sh, cloud-init.yaml (v1, updated:
│   │                               #   cloud-init installs the trio + writes systemd units §7.6)
│   └── cloud-init.yaml
└── www/                            # per-project roots (v1) → tenant registry backing the TLS ask endpoint
```

### 8.3 `anvilctl` Command Surface

```bash
# Lifecycle
anvilctl start [-d]        # dev: data tier + frankenphp (collapsed) | prod env: three units
anvilctl stop
anvilctl status            # unit states, listener map (§3.4), worker counts, versions
anvilctl restart <svc>     # caddy | tengine | app@blue | app@green | data

# Stack shape (dev)
anvilctl stack slim|full   # §5.6

# Health & policy
anvilctl doctor            # CVE floors from config/versions.env vs installed binaries;
                           #   embedded-Caddy lineage check; port-registry collision check;
                           #   XFF chain probe (§3.3); exit 1 on any violation
anvilctl verify headers|ports|health   # §6.3 gates, individually runnable

# Deploys
anvilctl deploy <env> [--strategy blue-green] [--timeout 30s]   # §7.7 as code
anvilctl rollback <env>    # dyups swap back to the previous pool (kept warm)

# v1 surface (kept)
anvilctl projects|scan|watch|db|logs|build-assets
anvilctl ec2 provision|tunnel|billing|billing-alarm
```

### 8.4 Implementation Tasks

| # | Task | Est. | Priority |
|---|---|---|---|
| 1 | `config/versions.env` + `lib/core.sh` floor checks (`anvilctl doctor` skeleton) | 0.5 d | 🔥 |
| 2 | `lib/frankenphp.sh` — unit template install, blue/green Caddyfile generation, admin-API status (`/frankenphp/threads`, `/metrics`) | 1 d | 🔥 |
| 3 | `dev/Caddyfile.dev` + collapsed-mode `start`/`stop` (replaces nginx/php containers in dev) | 0.5 d | 🔥 |
| 4 | `edge/Caddyfile` + `lib/caddy.sh` — validate/reload, ACME + staging CA knob | 1 d | 🔥 |
| 5 | `lb/tengine.conf` + `lib/tengine.sh` — 3.2.0 package-or-source install, `t`-check, HUP, dyups wrapper, check_status poller | 1.5 d | 🔥 |
| 6 | systemd units (§7.6) + `fetch-secrets.sh` + boot ordering; `anvil provision` writes them | 1 d | 🔥 |
| 7 | `lib/deploy.sh` — §7.7 runbook as code incl. auto-rollback + smoke suite | 1.5 d | 🔥 |
| 8 | `lib/verify.sh` — header-chain/port/health assertions (staging gates §6.3) | 1 d | High |
| 9 | Tenant registry: `www/` → TLS-ask endpoint data source + `lib/vhost-watcher.sh` rewire | 1 d | High |
| 10 | `install.sh` v3: pinned binary installs, users (caddy/tengine/anvil), sysctl/limits, firewall baseline | 1 d | High |
| 11 | TUI/Web UI panels: stack shape, doctor, deploy, rollback | 1 d | Medium |
| 12 | `cloud-init.yaml` update: trio install + units + first-boot health gate | 0.5 d | Medium |
| 13 | Docs: rewrite `anvil/README.md` from this document; VALIDATION.md for the v3 flows | 0.5 d | Medium |

Sequencing note: tasks 1–3 alone deliver the dev-laptop experience (collapsed mode); 4–7 deliver a full prod node; 8–13 harden parity and operability. Nothing blocks on Tengine internals until task 5 — by design, since Tengine 3.2.0 final should be watched to GA before prod rollout (§2.2).

### 8.5 Backward Compatibility & Migration

- **Dev machines (v1):** `anvilctl stop` (old stack), `git pull`, `./install.sh` (idempotent — adds binaries/users/units), `anvilctl start` → collapsed mode. The `www/` projects and MySQL volumes carry over untouched; nginx vhost renders and cert files become dead artifacts, removed by `anvilctl migrate cleanup-legacy-nginx` (explicit, logged, reversible within 30 days via the git history).
- **EC2 (v1):** rebuild the node via `anvil provision` (blue/green means migration is a new node, not an in-place surgery — STRUCTURE-08's immutable-swap applied to the host itself). RDS/SSM/alarm state is name-keyed and survives.
- **v2 draft artifacts:** none shipped to prod; the v2 doc is superseded by this file (§1.3 records the corrections).
- **Escape hatch:** `anvilctl stack legacy-nginx` preserves the v1 nginx+PHP-FPM path for emergency comparison during the migration window only; it is removed at v3.1.

---

## 9. Troubleshooting Matrix

| Symptom | Likely layer | Diagnosis | Fix |
|---|---|---|---|
| 502 at public URL, Tengine log `connect() failed (111: Connection refused)` | FrankenPHP down | `systemctl status anvil-frankenphp@blue`; `curl 127.0.0.1:8090/health`; `curl 127.0.0.1:2019/frankenphp/threads` | Unit restart; if worker loop crashes repeat, check `max_consecutive_failures` and app error log — a Fiber that never yields will hang, not crash (see next row) |
| Requests hang until 60s timeout (no 5xx) | App (Fiber) | `frankenphp/threads` shows threads stuck `busy`; `loom pulse:trace <id>` pinpoints the Pulse | Cooperative scheduler: an infinite-loop Pulse blocks its worker. `loom kill <worker>`; long-term fix yields at tick boundaries (ADR-017 contract). `max_requests` bounds the leak class |
| 502 at public URL, Caddy log `dial tcp 127.0.0.1:8081: connect: connection refused` | Tengine down | `systemctl status anvil-tengine`; `tengine -t` | Fix config/syntax; unit restart. Caddy's `health_uri /_anvil/ping` should have marked it unhealthy in the interim |
| 504 at public URL after ~75s | Worker slower than LB timeout | Tengine log: `urt=…` near 75s; worker busy-ratio > 0.9 | Raise `proxy_read_timeout` **and** PHP budget together (§3.3 ladder — never only one), or add workers/capacity |
| Client IP shows `127.0.0.1` in app logs | Trust chain broken at one hop | `anvilctl verify headers` walks §3.3 and names the hop | Check `set_real_ip_from`/`real_ip_header` (Tengine) and `trusted_proxies static 127.0.0.1` + `TRUSTED_PROXIES` env (FrankenPHP + framework) |
| `https` scheme lost; app generates `http://` URLs | Same as above, Proto variant | Same walk, `X-Forwarded-Proto` branch | Same; Tengine hardcodes `https` (§7.4) so a miss means the header is being dropped before/inside the app |
| Cert not issued for a tenant host (browser TLS error) | On-demand gate | Caddy log for the `ask` call; `curl -i 'http://127.0.0.1:8081/_anvil/tls-allowed?domain=tenant.example.com'` | Registry says no → register tenant (`www/` + `anvilctl scan`); if 200 but no cert, check Caddy ACME logs / rate limits |
| Deploy swapped but new code not live | Symlink timing | `readlink /opt/anvil/current`; green unit's `WorkingDirectory` | The flip must precede green start (§7.7 step 2); never edit a live release in place |
| dyups POST returns error / upstream unchanged | dyups interface | `curl 127.0.0.1:8081/_anvil/dyups/upstream/frankenphp` (GET shows state); `/var/lib/anvil/dyups` writable by `tengine` user | Permissions (`ReadWritePaths` in the unit); body must be a full `server …;` statement |
| Workers restart constantly (boot loop) | App boot failure | `journalctl -u anvil-frankenphp@blue -e`; `opcache.preload` errors are the classic | Preload file must exist in the immutable release and be compilable under the release's own autoload map (ADR-010) |
| Dev: `*.test` does not resolve | dnsmasq/resolved | `cat /etc/resolv.conf`; RUNBOOK-ANVIL-DNS | The v1 runbook applies unchanged (stub-listener conflict, NetworkManager variant) |
| Dev: TLS warning despite mkcert | CA not in browser NSS | `mkcert -install`; Firefox profiles sometimes need manual import | Re-run install; point browser at the generated rootCA |
| Fedora: units fail with permission denied | SELinux | `ausearch -m avc -ts recent` | Set contexts on `/opt/anvil`, `/etc/anvil`, `/run/anvil`; never blanket `setenforce 0` — `anvilctl doctor` ships the policy hints |
| Port 2019 "already in use" at boot | Two Caddy-lineage processes | `ss -tlnp | grep 2019` | The §3.4 registry exists for exactly this: edge admin must be 2020, app blue 2019, green 2018 — check generated configs |
| 429s under modest load | limit_req misjudged | Tengine log `limit_req` entries; `/_anvil/stub` | Tune zone rates/bursts (§7.4 values are starting points, sized for a solo-operator node) |

---

## 10. Appendices

### Appendix A — Decision Log

| Date | Decision | Rationale |
|---|---|---|
| 2026-08-22 | FrankenPHP is the canonical runtime | ADR-017: Fiber-based cooperative scheduler requires long-lived workers; PHP-FPM excluded |
| 2026-08-26 | (v2) Tengine as edge proxy; FrankenPHP standalone for dev | dyups for zero-downtime; dev simplicity |
| 2026-08-28 | **(v3) Three layers: Caddy edge / Tengine internal LB / FrankenPHP app** | The user-proposed layering is adopted and hardened: Caddy takes ACME+H3+on-demand TLS at the edge (removing certbot entirely), Tengine's nginx-class buffering/health-checks/dyups serve the middle, FrankenPHP serves the app. Rift (CVE-2026-42945) proves fork-lag risk concentrates in Tengine — internal placement + RULE T1 neutralizes it |
| 2026-08-28 | RULE T1: Caddy is the only public listener | Infrastructure-level Zero-Exposure Test (DEPLOY-03); converts future fork-lag CVEs to loopback-only blast radius |
| 2026-08-28 | Tengine floor = 3.2.0 (final); 3.1.0 forbidden | CVE-2026-42945 unpatched in every pre-3.2.0 release |
| 2026-08-28 | Caddy floor = 2.11.1 (rec. 2.11.4); admin moved to :2020 with `origins`/`enforce_origin` | CVE-2026-27589 admin-CSRF; port collision with FrankenPHP admin fixed simultaneously |
| 2026-08-28 | FrankenPHP floor = 1.12.5 (rec. 1.12.7, PHP 8.5) | 1.12.5 security release; 1.12.7 current; `max_requests` confirmed global-level |
| 2026-08-28 | Dev collapses to one FrankenPHP process | FrankenPHP embeds Caddy; `watch` provides hot reload; prod parity remains one flag away (`stack full`) |
| 2026-08-28 | Rate limiting lives at Tengine (`limit_req`), edge stays stock-binary | Plugin-free edge keeps the CVE floor auditable; `limit_req` keys on the restored real client IP |
| 2026-08-28 | Secrets via systemd `EnvironmentFile` (SSM-sourced), never in Caddyfiles | v1 SSM contract preserved; Caddyfiles stay repo-safe |
| 2026-08-28 | Option B (Caddy-only) implemented as a rehearsed degradation mode | The next Rift-class event needs a same-day maneuver, not an architecture debate |

### Appendix B — References (researched 2026-08-28)

- Caddy releases — https://github.com/caddyserver/caddy/releases (v2.11.4, 2026-06-03)
- CVE-2026-27589 (Caddy admin-API CSRF; fixed 2.11.1) — https://nvd.nist.gov/vuln/detail/CVE-2026-27589 and https://caddyserver.com/docs/caddyfile/options (admin `origins`/`enforce_origin`, `on_demand_tls`, `acme_ca`)
- FrankenPHP releases — https://github.com/php/frankenphp/releases (v1.12.7, 2026-08-07); config directives — https://frankenphp.dev/docs/config/ (`worker{}` options, global `max_requests`, `php_ini`); production/trusted proxies — https://frankenphp.dev/docs/production/; metrics — https://frankenphp.dev/docs/metrics/ (`frankenphp_total_threads`, `frankenphp_busy_threads`, `/frankenphp/threads`)
- Tengine — https://github.com/alibaba/tengine (releases 3.2.0-rc1/2/3, Aug 2026; packages x86_64/aarch64); CVE-2026-42945 tracking — https://github.com/alibaba/tengine/issues/2044; analysis — Orca Security "Fixing CVE-2026-42945 in Tengine Servers" (2026-06-16); nginx advisory line — https://nginx.org/en/security_advisories.html (fixed 1.30.1+/1.31.0+)
- Anvil v1 — `anvil/README.md`, `anvil/config/anvil.conf`, `docker/docker-compose.local.yml` (the legacy model this document replaces)
- DGLab architecture — `ADR-017` (Fiber runtime), `ADR-016` (library/app split), `ADR-010` (opcache preload), `ADR-013`/`ADR-006` (MySQL/Redis), `DEPLOY-01..04`, `STRUCTURE-08` (immutable deployment), `DGLAB-AS-OS-RUNTIME.md`, `RUNBOOK-ANVIL-DNS.md`
- Anvil v2 draft (superseded) — `anvil/ReImplementation_Instruction.md` (2026-08-26)

### Appendix C — Glossary

**RULE T1** — the zero-exposure edge rule (§1.1). **Rift** — CVE-2026-42945, the nginx rewrite-module heap overflow inherited by Tengine (§2.2). **dyups** — Tengine's dynamic upstream module; the blue/green switch (§7.7). **Collapsed mode** — single-process dev (§5.5). **Option B** — Caddy-only degradation posture (§3.5). **The ask endpoint** — Caddy's on-demand-TLS authorization callback (§7.3). **Blue/green** — the two interchangeable FrankenPHP pools on :8090/:8091 (§7.7).



