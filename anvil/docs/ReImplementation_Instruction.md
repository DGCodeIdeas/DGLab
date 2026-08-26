# Anvil Reimplementation Instruction

**Status:** Proposed (architecture lead review required)
**Date:** 2026-08-26 (v3)
**Author:** DGCI (architecture lead), analysis by Z.ai
**Supersedes:** v2 (2026-08-26, Tengine + FrankenPHP two-layer) and v1 (2026-08-24)
**Gate decisions:** OD-07 (Fiber-based cooperative runtime → FrankenPHP mandatory)

---

## 0. Executive Summary

This document specifies how to reimplement Anvil's serving layer as a **three-component proxy architecture** — **Caddy** (edge), **Tengine** (internal load balancer), **FrankenPHP** (PHP application server) — deployed across **three tiers** that activate only the layers each tier needs.

The reimplementation has five workstreams:

| # | Workstream | Scope |
|---|---|---|
| W1 | **Three-layer serving stack** | Caddy (edge) → Tengine (internal LB) → FrankenPHP (app) |
| W2 | **Installer multi-distro** | Extend `install.sh` beyond Debian/Ubuntu (Fedora, Arch, Amazon Linux) |
| W3 | **Bug fixes** | Absolute-path cert leak, hardcoded SSH user, placeholder repo URL |
| W4 | **Edge/native tier** | Systemd-native deployment (no Docker) for production and edge nodes |
| W5 | **Mercure hub** | Real-time pub/sub at the edge via Caddy's Mercure module |

---

## 1. Purpose

Anvil's current serving layer (nginx 1.27 + PHP-FPM 8.3) is incompatible with OD-07. The OS-metaphor runtime requires PHP Fibers, which need a long-lived worker process — FrankenPHP in worker mode. PHP-FPM terminates the process after every request, making Fiber-scoped state impossible.

The v1 instruction (2026-08-24) addressed the serving-layer swap for two targets (local + EC2) using Tengine + FrankenPHP. The v2 (2026-08-26) added multi-distro support and a native edge tier. This v3 introduces **Caddy as the edge layer**, creating a three-component architecture where each component has a distinct, non-overlapping role.

### Why Three Components

| Component | Role | Why it exists in this architecture |
|---|---|---|
| **Caddy** | Edge proxy | Automatic HTTPS (no certbot), HTTP/3 (QUIC), Mercure hub for real-time SSE/WebSocket, clean Caddyfile config, on-the-fly TLS certificate management |
| **Tengine** | Internal load balancer | Built-in upstream health checks, DSO (hot-loadable modules = loadable kernel modules in the OS metaphor), dynamic upstream management, embedded Lua for routing, Prometheus metrics module |
| **FrankenPHP** | PHP application server | Long-lived worker processes for Fiber support (OD-07), hot OpCache, cooperative scheduling, `pulse()` scope via WeakMap |

### The "Two Caddies" Question

FrankenPHP *embeds Caddy* as its internal HTTP server. This is not redundancy — the two Caddy instances serve completely different purposes:

- **Standalone Caddy (edge):** Public-facing. Handles TLS termination, HTTP/3, Mercure hub, rate limiting, and static file caching. Listens on :443/:80. Configured via Caddyfile.
- **FrankenPHP's embedded Caddy (internal):** Internal-only. Receives HTTP requests from Tengine and routes them to PHP workers. Never exposed to the internet. Configured by FrankenPHP's worker-mode command.

They share the Caddy codebase but operate in different network namespaces with different configurations and different responsibilities. The standalone Caddy could be replaced by Cloudflare, an ALB, or any other edge proxy without touching FrankenPHP's internal operation.

---

## 2. Tier Model — Layer Activation

The three components are not all active in every tier. Each tier activates only the layers it needs:

```
┌───────────────────────────────────────────────────────────────────────┐
│                     TIER 1: LAPTOP (DEV)                             │
│                                                                      │
│  ┌─────────────────────────────────────────────────┐                 │
│  │  FrankenPHP (worker mode, 1–2 workers)           │                 │
│  │  Internal Caddy handles TLS via Caddyfile         │                 │
│  │  *.test wildcard domains, mkcert or self-signed  │                 │
│  │  Mercure: opt-in via Caddyfile directive          │                 │
│  └─────────────────────────────────────────────────┘                 │
│       Caddy: SKIP (FrankenPHP's internal Caddy suffices)              │
│       Tengine: SKIP (single instance, no LB needed)                  │
│  Plus: MySQL 8.0, Redis, phpMyAdmin, Web UI, TUI, inotify            │
│  OS: Ubuntu, Fedora, Arch, Pop!_OS                                   │
└───────────────────────────────────────────────────────────────────────┘

┌───────────────────────────────────────────────────────────────────────┐
│                     TIER 2: STAGING VM                                │
│                                                                      │
│  Client ─────▶ Caddy (edge) ──────────────────────┐                  │
│  :443/:80       Automatic HTTPS, HTTP/3             │                  │
│                 Mercure hub, static files           │                  │
│                 Rate limiting (basic)                │                  │
│                 vhost: real domain                   │                  │
│                       │                             │                  │
│                       │ proxy_pass                  │                  │
│                       ▼                             │                  │
│  ┌─────────────────────────────────────────────────┐ │                  │
│  │  FrankenPHP (worker mode, 2–4 workers)           │ │                  │
│  │  Internal Caddy on :8080                         │ │                  │
│  └─────────────────────────────────────────────────┘ │                  │
│       Tengine: SKIP (single FrankenPHP instance)      │                  │
│  Plus: RDS MySQL, Redis, Let's Encrypt (auto)        │                  │
│  OS: Ubuntu 24.04 LTS / Amazon Linux 2023            │                  │
└───────────────────────────────────────────────────────────────────────┘

┌───────────────────────────────────────────────────────────────────────┐
│                     TIER 3: PRODUCTION / EDGE                         │
│                                                                      │
│  Client ──▶ Caddy (edge) ──▶ Tengine (LB) ──┬──▶ FrankenPHP #1      │
│  :443/:80   TLS, HTTP/3       Health check   ├──▶ FrankenPHP #2      │
│             Mercure hub        DSO modules   └──▶ FrankenPHP #N      │
│             Rate limiting      Lua routing                         │
│             Static cache       Prometheus                            │
│                           Tengine (internal LB)                      │
│  Plus: RDS MySQL, ElastiCache Redis, external monitoring             │
│  OS: Ubuntu 24.04 LTS minimal, Debian 12 (systemd-native)            │
└───────────────────────────────────────────────────────────────────────┘
```

### Tier Differences at a Glance

| Dimension | Tier 1 (Laptop) | Tier 2 (Staging) | Tier 3 (Production) |
|---|---|---|---|
| **Caddy** (standalone) | No (FrankenPHP's internal) | Yes (edge) | Yes (edge) |
| **Tengine** | No | No | Yes (internal LB) |
| **FrankenPHP** | Yes (1–2 workers) | Yes (2–4 workers) | Yes (N workers, N instances) |
| **TLS** | mkcert / self-signed | Let's Encrypt (auto by Caddy) | Let's Encrypt (auto by Caddy) |
| **HTTP/3** | Yes (FrankenPHP's Caddy) | Yes (standalone Caddy) | Yes (standalone Caddy) |
| **Mercure** | Opt-in (dev only) | Yes (staging SSE) | Yes (production real-time) |
| **DNS** | dnsmasq *.test → 127.0.0.1 | Real DNS (Route 53) | Real DNS (any provider) |
| **Database** | MySQL 8.0 (Docker) | RDS MySQL | RDS / managed MySQL |
| **Redis** | Docker | Docker | ElastiCache / external |
| **Containerization** | Docker Compose | Docker Compose | Systemd-native (no Docker) |
| **Web UI** | Yes (127.0.0.1:9999) | SSH tunnel only | No (use monitoring) |
| **TUI** | Yes | No | No |
| **inotify watcher** | Yes | No | No |
| **DSO modules** | No | No | Yes (Prometheus, security) |
| **Health checks** | No | Caddy only | Tengine upstream checks |
| **Load balancing** | No | No | Yes (Tengine, N FrankenPHP instances) |

---

## 3. Component Deep Dives

### 3.1 Caddy — The Edge

Caddy is the public-facing entry point in Tier 2 and Tier 3. It handles everything that should happen *before* the request reaches internal infrastructure.

**Why Caddy at the edge (not Tengine):**

| Capability | Caddy | Tengine |
|---|---|---|
| Automatic HTTPS (zero-config Let's Encrypt) | **Built-in** | No (needs certbot) |
| HTTP/3 (QUIC) | **Built-in** | No |
| Mercure hub (SSE/WebSocket pub/sub) | **Built-in module** | No |
| Caddyfile syntax | **Declarative, minimal** | nginx-conf (verbose) |
| On-the-fly cert renewal | **Automatic** | External (certbot cron) |
| Reverse proxy | Yes | Yes |
| Rate limiting | Via plugin | Via Lua or 3rd-party |
| Load balancing (upstream health) | Basic | **Built-in (ngx_upstream_check)** |
| DSO (hot-loadable modules) | No | **Yes** |
| Embedded Lua scripting | No | **Yes** |

The decision is clear: Caddy owns the edge because of automatic HTTPS and HTTP/3 — capabilities Tengine lacks entirely. Tengine owns the internal layer because of health checks, DSO, and Lua — capabilities Caddy lacks or provides only via plugins.

#### 3.1.1 Caddyfile for Tier 2 (Staging)

```caddyfile
# anvil/config/Caddyfile.staging
{
    # Email for Let's Encrypt certificate issuance.
    email ops@example.com

    # Mercure hub configuration.
    mercure {
        publisher_jwt_key "${MERCURE_PUBLISHER_JWT_KEY}"
        subscriber_jwt_key "${MERCURE_SUBSCRIBER_JWT_KEY}"
        cors_origins "*"
        publish_origins "*"
        transport_url "bolt:///var/lib/mercure/mercure.db"
    }
}

staging.example.com {
    # Automatic HTTPS via Let's Encrypt — no certbot needed.
    # HTTP/3 is enabled by default when Caddy can bind UDP :443.

    # Static file cache.
    root * /var/www/staging/public
    file_server

    # PHP requests → FrankenPHP.
    php_server {
        # When using a SEPARATE FrankenPHP instance (not embedded),
        # we proxy instead of using Caddy's php_server directive.
        # See the reverse_proxy block below.
    }

    # Actually, for Tier 2 with a separate FrankenPHP container,
    # we use reverse_proxy, NOT Caddy's php_server.
    # php_server is only for when Caddy directly embeds PHP.
    # Tier 2 uses this:

    reverse_proxy frankenphp:8080 {
        # Stream uploads directly (no buffering at edge).
        flush_interval -1

        # Health check: if FrankenPHP is down, return 503.
        health_uri /health
        health_interval 10s
        health_timeout 5s
    }

    # Mercure endpoint.
    handle /.well-known/mercure {
        mercure
    }

    # Logging.
    log {
        output file /var/log/caddy/access.log
        format console
    }
}
```

#### 3.1.2 Caddyfile for Tier 3 (Production)

```caddyfile
# anvil/config/Caddyfile.production
{
    email ops@example.com

    # Production Mercure — larger transport, JWT-only auth.
    mercure {
        publisher_jwt_key "${MERCURE_PUBLISHER_JWT_KEY}"
        subscriber_jwt_key "${MERCURE_SUBSCRIBER_JWT_KEY}"
        cors_origins "https://app.example.com"
        publish_origins "https://app.example.com"
        transport_url "redis://${REDIS_HOST}:6379"
        transport_options {
            topic_groups "tenant:{topic_group}"
        }
    }
}

app.example.com, www.app.example.com {
    root * /var/www/app/public
    file_server

    # Edge → Tengine (internal LB) → FrankenPHP
    # In Tier 3, Caddy proxies to Tengine, not directly to FrankenPHP.
    reverse_proxy tengine:80 {
        flush_interval -1
        health_uri /health-tengine
        health_interval 5s
        health_timeout 3s
    }

    # Rate limiting (basic, via Caddy's built-in or a plugin).
    # For advanced rate limiting, use Tengine's Lua instead.
    rate_limit {
        zone dynamic {
            key {remote_host}
            events 100
            window 1m
        }
    }

    handle /.well-known/mercure {
        mercure
    }

    log {
        output file /var/log/caddy/access.log
        format json
    }
}
```

#### 3.1.3 Tier 1 (Laptop) — FrankenPHP's Internal Caddy

On laptops, there is no standalone Caddy. FrankenPHP's embedded Caddy handles TLS and serves the `*.test` wildcard domains directly. This is configured via a Caddyfile that FrankenPHP reads:

```caddyfile
# anvil/config/Caddyfile.laptop
# FrankenPHP reads this file in worker mode.
# mkcert root CA is in the system trust store, so
# Caddy trusts it for the *.test TLD.

{
    # Disable automatic HTTPS for .test TLD (use mkcert instead).
    auto_https off
}

:443 {
    tls /etc/certs/local/rootCA.pem /etc/certs/local/rootCA-key.pem

    # FrankenPHP's Caddy routes PHP to its own workers.
    php_server {
        root /app/public
    }

    # Mercure for local dev (opt-in).
    handle /.well-known/mercure {
        mercure {
            # Insecure for local dev — no JWT keys.
            anonymous
            allow_anonymous
            cors_origins "*"
        }
    }
}
```

Each project gets its own FrankenPHP instance (or the same instance with Server-SNI routing). For simplicity in v1 of the reimplementation, each `anvilctl new <project>` creates a separate Caddyfile snippet and FrankenPHP starts with `import /path/to/snippets/*` to load all project configs.

### 3.2 Tengine — The Internal Load Balancer

Tengine exists **only in Tier 3** (production with multiple FrankenPHP instances). It is never needed on a laptop (single instance) or staging VM (single instance). Its role is the "internal kernel" — health-checking FrankenPHP workers, load-balancing across them, and providing DSO-loaded observability.

**Why Tengine (not Caddy, not HAProxy, not nginx) for the internal LB:**

| Need | Tengine | Caddy | HAProxy | nginx |
|---|---|---|---|---|
| Built-in health checks | **Yes** | Plugin | Yes | 3rd-party |
| DSO (hot-load modules) | **Yes** | No | No | No |
| Embedded Lua | **Yes** | No | No | OpenResty |
| nginx config compat | **100%** | N/A | N/A | N/A |
| Dynamic upstream | **Yes** | Plugin | Yes | No |

DSO is the deciding factor. In the OS metaphor, DSO modules are loadable kernel modules. Tengine can load a Prometheus metrics exporter or a security WAF module at runtime without a restart — this is architecturally meaningful for DGLab.

#### 3.2.1 Tengine Config for Tier 3

```nginx
# /etc/tengine/tengine.conf — Tier 3 production internal LB.
worker_processes auto;
error_log /var/log/tengine/error.log warn;
pid /var/run/tengine.pid;

events {
    worker_connections 2048;
    # Tengine: use epoll (Linux) or kqueue (BSD).
}

http {
    include       mime.types;
    default_type  application/octet-stream;
    sendfile      on;
    keepalive_timeout 65;

    # Upstream: FrankenPHP workers with built-in health checks.
    upstream frankenphp_pool {
        # FrankenPHP instances — add/remove without Tengine restart
        # via Tengine's dynamic upstream API (Phase 2 future).
        server 127.0.0.1:8081;
        server 127.0.0.1:8082;
        server 127.0.0.1:8083;

        # Built-in health check (ngx_upstream_check_module).
        # Tengine removes unhealthy instances from the pool automatically.
        check interval=3000 rise=2 fall=3 timeout=1000 type=http;
        check_http_send "GET /health HTTP/1.0\r\n\r\n";
        check_http_expect_alive http_2xx http_3xx;
    }

    # DSO: load Prometheus metrics module at runtime (no restart).
    # dso {
    #     load /usr/lib/tengine/modules/ngx_http_prometheus_module.so;
    # }

    server {
        listen 80;
        server_name _;

        # Health endpoint for Caddy to probe.
        location = /health-tengine {
            return 200 "ok";
            add_header Content-Type text/plain;
        }

        # Proxy all requests to the FrankenPHP pool.
        location / {
            proxy_pass http://frankenphp_pool;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;

            # Stream uploads directly (no buffering at LB layer).
            proxy_request_buffering off;
            proxy_buffering off;

            # Timeouts tuned for long-running Pulse execution.
            # The cooperative scheduler's 50ms quantum means a Pulse
            # that doesn't yield blocks its worker for up to 50ms.
            # The proxy timeout must exceed the worst-case wall time.
            proxy_connect_timeout 5s;
            proxy_read_timeout 300s;
            proxy_send_timeout 300s;
        }

        # DSO Prometheus metrics — loopback only.
        # location = /metrics {
        #     prometheus;
        #     allow 127.0.0.1;
        #     deny all;
        # }
    }
}
```

#### 3.2.2 Lua Routing Examples (Tier 3 Future)

Tengine's embedded Lua enables complex internal routing without touching PHP:

```nginx
# Example: Route /api/v1/ and /api/v2/ to different FrankenPHP pools
# (canary deployment of a new API version).
location /api/v2/ {
    access_by_lua_block {
        -- 10% traffic to canary pool, 90% to stable.
        if math.random() < 0.10 then
            ngx.var.upstream = "frankenphp_canary"
        else
            ngx.var.upstream = "frankenphp_pool"
        end
    }
    proxy_pass http://$upstream;
}
```

### 3.3 FrankenPHP — The Application Server

FrankenPHP is the PHP execution layer in all three tiers. It is the only component that runs PHP code.

**Why FrankenPHP (not PHP-FPM, not Swoole, not RoadRunner):**

This is a stated consequence of OD-07, not a choice. The Fiber-based cooperative scheduler requires a long-lived worker process. PHP-FPM terminates after every request. Swoole is incompatible with FrankenPHP (it replaces the runtime). RoadRunner is theoretically compatible but untested.

| | PHP-FPM (current) | FrankenPHP Worker Mode |
|---|---|---|
| Process model | One process per request, terminated | Long-lived worker, persists across requests |
| Fiber support | N/A (process dies) | Full — Fibers live across worker lifetime |
| `pulse()` scope (CORE-02) | N/A | Required — WeakMap-keyed per-Fiber cache |
| OpCache | Cold on new process spawn | Always warm (worker preloads once) |
| Protocol | FastCGI | HTTP (proxy_pass, not fastcgi_pass) |
| Embedded Caddy | No | Yes (internal HTTP server) |
| Mercure | No | Yes (built-in, opt-in) |
| HTTP/3 | No | Yes (via embedded Caddy) |
| Worker restart | N/A (process IS the request) | `--max-requests N` (configurable) |

#### 3.3.1 FrankenPHP Worker Configuration

```bash
# In anvil.conf:

# FrankenPHP version pin.
: "${FRANKENPHP_VERSION:=latest}"

# Worker count: one per vCPU for cooperative scheduling.
: "${FRANKENPHP_WORKERS:=2}"

# FrankenPHP internal HTTP port (where Tengine/Caddy connects).
: "${FRANKENPHP_LISTEN_PORT:=8080}"

# Max requests before worker restart (memory leak defense).
# 0 = unlimited (dev). Production: 10000.
: "${FRANKENPHP_MAX_REQUESTS:=0}"
```

**Worker count by tier:**

| Tier | Typical vCPU | Workers | Rationale |
|---|---|---|---|
| Laptop | 4–8 | 2 | Leave headroom for IDE, browser, Docker |
| Staging VM | 2–4 | 2–4 | Match vCPU; test prod-like concurrency |
| Production | 2–8+ | Match vCPU per instance | Cooperative scheduling within; scale instances for throughput |

**Worker restart policy:**

```bash
# Dev: no restart (faster iteration).
FRANKENPHP_MAX_REQUESTS=0

# Staging: restart after 1000 (catches leaks early).
FRANKENPHP_MAX_REQUESTS=1000

# Production: restart after 10000.
FRANKENPHP_MAX_REQUESTS=10000
```

Combined with CORE-02's `pulse()` scope using `WeakMap` (auto-evicts on Fiber GC), this provides defense-in-depth against memory leaks in long-lived workers.

#### 3.3.2 OpCache Configuration

```ini
; anvil/docker/php/php.ini
[opcache]
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000

; Dev: check for file changes. Prod: 0 (immutable deploy).
opcache.validate_timestamps=1
opcache.revalidate_freq=0
opcache.fast_shutdown=1

; PHP 8.3+ JIT — worker mode benefits significantly because
; the worker process is long-lived and JIT compilation amortizes.
opcache.jit=tracing
opcache.jit_buffer_size=64M
```

#### 3.3.3 Fiber Runtime Boot Integration

When DGLab's Kernel boots inside a FrankenPHP worker:

1. Detect FrankenPHP worker context (`\Fiber::getCurrent()` or `APP_ENV`).
2. Initialize the `KernelScheduler` with the worker's event loop.
3. Each incoming HTTP request becomes one Fiber → one `PulseDescriptor`.
4. The `pulse()` scope in CORE-02's Container uses `WeakMap<Fiber, array>` — works automatically because each request runs inside a Fiber in FrankenPHP worker mode.

---

## 4. Workstream W1: Three-Layer Serving Stack

### 4.1 File Changes

#### 4.1.1 `anvil/config/anvil.conf`

Add the three-component configuration block:

```bash
# ── Caddy (Edge) ─────────────────────────────────────
: "${CADDY_VERSION:=2.8.4}"

# Caddy listens on these ports (host-level).
: "${CADDY_HTTP_PORT:=80}"
: "${CADDY_HTTPS_PORT:=443}"

# Mercure configuration.
: "${MERCURE_ENABLED:=false}"
: "${MERCURE_PUBLISHER_JWT_KEY:=}"
: "${MERCURE_SUBSCRIBER_JWT_KEY:=}"

# ── Tengine (Internal LB) ──────────────────────────
: "${TENGINE_VERSION:=3.2.0}"
: "${TENGINE_ENABLED:=false}"  # true only in Tier 3

# ── FrankenPHP (App Server) ─────────────────────────
: "${FRANKENPHP_VERSION:=latest}"
: "${FRANKENPHP_WORKERS:=2}"
: "${FRANKENPHP_LISTEN_PORT:=8080}"
: "${FRANKENPHP_MAX_REQUESTS:=0}"

# ── Tier detection ───────────────────────────────────
# ANVIL_TIER is set by the installer or systemd env file.
# Values: laptop | staging | production
: "${ANVIL_TIER:=laptop}"

# ── Proxy config directories ─────────────────────────
# The directory on disk keeps the name 'nginx' for git history.
: "${PROXY_CONFD_DIR:=${ANVIL_ROOT}/docker/nginx/conf.d}"
: "${PROXY_CERTS_DIR:=${ANVIL_ROOT}/docker/nginx/certs}"
: "${PROXY_TEMPLATES_DIR:=${ANVIL_ROOT}/docker/nginx/templates}"

# Deprecated aliases (backward compatibility for existing scripts).
NGINX_CONFD_DIR="${PROXY_CONFD_DIR}"
CERTS_DIR="${PROXY_CERTS_DIR}"
NGINX_TEMPLATES_DIR="${PROXY_TEMPLATES_DIR}"
```

#### 4.1.2 `anvil/docker/docker-compose.local.yml`

**Tier 1: Laptop.** No standalone Caddy, no Tengine. FrankenPHP handles everything.

```yaml
services:
  # ── FrankenPHP: edge + app (laptop dev) ─────────────
  frankenphp:
    image: dunglas/frankenphp:${FRANKENPHP_VERSION:-latest}
    container_name: anvil_frankenphp
    command: >-
      frankenphp run
      --config /etc/caddy/Caddyfile
      --workers=${FRANKENPHP_WORKERS:-2}
      --listen=${FRANKENPHP_LISTEN_PORT:-8080}
    ports:
      - "80:80"
      - "443:443"
      # UDP :443 for HTTP/3 (QUIC).
      - "443:443/udp"
    volumes:
      - ./www:/app/public
      - ./config/Caddyfile.laptop:/etc/caddy/Caddyfile:ro
      - ./nginx/certs:/etc/certs/local:ro
      - ./php/php.ini:/usr/local/etc/php/conf.d/anvil.ini:ro
    environment:
      APP_ENV: development
      FRANKENPHP_MAX_REQUESTS: "${FRANKENPHP_MAX_REQUESTS:-0}"
    networks:
      - anvil_net
    restart: unless-stopped

  # mysql, phpmyadmin, redis — UNCHANGED
```

**What changed:** The `nginx` and `php` services are replaced by a single `frankenphp` service that handles TLS, vhosts, and PHP execution. FrankenPHP's embedded Caddy reads `Caddyfile.laptop` which configures the `*.test` wildcard with mkcert certificates.

**Vhost generation change:** `lib/vhost.sh` no longer renders nginx vhost configs and reloads nginx. Instead, it renders Caddyfile snippets (one per project) into a directory that FrankenPHP's Caddyfile imports. FrankenPHP supports on-the-fly config reload via SIGUSR1 (zero-downtime, no dropped connections).

#### 4.1.3 `anvil/docker/docker-compose.staging.yml` (NEW)

**Tier 2: Staging VM.** Caddy (edge) + FrankenPHP (app). No Tengine.

```yaml
services:
  # ── Caddy: edge proxy ───────────────────────────────
  caddy:
    image: caddy:${CADDY_VERSION:-2.8.4}
    container_name: anvil_caddy
    ports:
      - "80:80"
      - "443:443"
      - "443:443/udp"  # HTTP/3
    volumes:
      - ./config/Caddyfile.staging:/etc/caddy/Caddyfile:ro
      - ./www:/var/www:ro
      - caddy_data:/data
      - caddy_config:/config
    depends_on:
      frankenphp:
        condition: service_started
    networks:
      - anvil_net
    restart: unless-stopped

  # ── FrankenPHP: app server ──────────────────────────
  frankenphp:
    image: dunglas/frankenphp:${FRANKENPHP_VERSION:-latest}
    container_name: anvil_frankenphp
    command: >-
      frankenphp run
      --workers=${FRANKENPHP_WORKERS:-2}
      --listen=${FRANKENPHP_LISTEN_PORT:-8080}
    volumes:
      - ./www:/app/public
      - ./php/php.ini:/usr/local/etc/php/conf.d/anvil.ini:ro
    environment:
      APP_ENV: staging
      FRANKENPHP_MAX_REQUESTS: "${FRANKENPHP_MAX_REQUESTS:-1000}"
    networks:
      - anvil_net
    restart: unless-stopped

  # redis — same as local
  # phpmyadmin — same as local, bound to 127.0.0.1:8080
  # NO mysql service — staging uses RDS

volumes:
  caddy_data:
  caddy_config:
```

**Key difference from Tier 1:** Caddy is a separate container that handles TLS and proxies to FrankenPHP. Caddy's automatic HTTPS issues Let's Encrypt certificates — no certbot needed. Caddy's data volume persists certificates and the Mercure transport database across container restarts.

#### 4.1.4 `anvil/docker/docker-compose.ec2.yml`

**Updated to match the three-layer model.** For EC2 with a single instance, this is effectively Tier 2 (Caddy + FrankenPHP). If the EC2 instance runs multiple FrankenPHP containers (unlikely on a t3.micro, possible on t3.medium+), add Tengine.

```yaml
services:
  caddy:
    image: caddy:${CADDY_VERSION:-2.8.4}
    container_name: anvil_caddy
    ports:
      - "80:80"
      - "443:443"
      - "443:443/udp"
    volumes:
      - ./config/Caddyfile.production:/etc/caddy/Caddyfile:ro
      - ./www:/var/www:ro
      - caddy_data:/data
      - caddy_config:/config
    depends_on:
      frankenphp:
        condition: service_started
    networks:
      - anvil_net
    restart: unless-stopped

  frankenphp:
    image: dunglas/frankenphp:${FRANKENPHP_VERSION:-latest}
    container_name: anvil_frankenphp
    command: >-
      frankenphp run
      --workers=${FRANKENPHP_WORKERS:-2}
      --listen=${FRANKENPHP_LISTEN_PORT:-8080}
    entrypoint: ["/usr/local/bin/entrypoint-ssm.sh"]
    environment:
      ANVIL_SSM_HOST: "/anvil/rds/host"
      ANVIL_SSM_USER: "/anvil/rds/user"
      ANVIL_SSM_PASSWORD: "/anvil/rds/password"
      ANVIL_SSM_DATABASE: "/anvil/rds/database"
      AWS_REGION: "${AWS_REGION:-us-east-1}"
      DB_HOST: "${DB_HOST:-}"
      DB_USER: "${DB_USER:-}"
      DB_PASSWORD: "${DB_PASSWORD:-}"
      DB_NAME: "${DB_NAME:-}"
      APP_ENV: production
      FRANKENPHP_MAX_REQUESTS: "${FRANKENPHP_MAX_REQUESTS:-10000}"
    volumes:
      - ./www:/app/public
      - ./php/php.ini:/usr/local/etc/php/conf.d/anvil.ini:ro
    depends_on:
      redis:
        condition: service_started
    networks:
      - anvil_net
    restart: unless-stopped

  # phpmyadmin, redis — same as current ec2 compose

volumes:
  caddy_data:
  caddy_config:
```

#### 4.1.5 `anvil/docker/tengine/Dockerfile` (NEW — Tier 3 only)

```dockerfile
# Tengine 3.x — internal load balancer (Tier 3 production only).
# Not used in Tier 1 (laptop) or Tier 2 (staging).

ARG TENGINE_VERSION=3.2.0

FROM alpine:3.20 AS builder

RUN apk add --no-cache \
    build-base gcc libc-dev linux-headers make \
    openssl-dev pcre-dev zlib-dev curl \
    gd-dev geoip-dev libxml2-dev lua-dev git

WORKDIR /usr/src
RUN git clone --depth 1 --branch ${TENGINE_VERSION} \
    https://github.com/alibaba/tengine.git

WORKDIR /usr/src/tengine
RUN ./configure \
    --prefix=/etc/tengine \
    --sbin-path=/usr/sbin/tengine \
    --modules-path=/usr/lib/tengine/modules \
    --conf-path=/etc/tengine/tengine.conf \
    --error-log-path=/var/log/tengine/error.log \
    --http-log-path=/var/log/tengine/access.log \
    --pid-path=/var/run/tengine.pid \
    --lock-path=/var/run/tengine.lock \
    --with-http_ssl_module \
    --with-http_v2_module \
    --with-http_realip_module \
    --with-http_gzip_static_module \
    --with-http_stub_status_module \
    --with-http_auth_request_module \
    --with-http_image_filter_module \
    --with-http_lua_module \
    --with-http_upstream_check_module \
    --with-dso \
    --with-ld-opt="-Wl,-rpath,/usr/lib/tengine/modules" \
    && make -j"$(nproc)" \
    && make install

FROM alpine:3.20

RUN apk add --no-cache \
    openssl pcre zlib gd geoip libxml2 lua curl \
    libstdc++ ca-certificates

COPY --from=builder /etc/tengine /etc/tengine
COPY --from=builder /usr/sbin/tengine /usr/sbin/tengine
COPY --from=builder /usr/lib/tengine/modules /usr/lib/tengine/modules

RUN mkdir -p /etc/tengine/conf.d /var/log/tengine /var/www /var/run

EXPOSE 80
CMD ["tengine", "-g", "daemon off;"]
```

#### 4.1.6 `anvil/lib/vhost.sh` — Major Rework

The vhost generation logic changes fundamentally. Instead of rendering nginx configs and reloading nginx, it now renders **Caddyfile snippets** and signals FrankenPHP (or manages Caddy's config in Tier 2/3).

**Tier 1 (Laptop) — Caddyfile snippets imported by FrankenPHP:**

```bash
anvil_vhost_generate() {
    local project="$1"
    local domain="${project}.${DOMAIN_TLD}"
    local conf_dir="${PROXY_CONFD_DIR}"
    local certs_dir="${PROXY_CERTS_DIR}/${project}"

    # Generate Caddyfile snippet for this project.
    cat > "${conf_dir}/${project}.caddy" <<SNIPPET
${domain} {
    tls ${certs_dir}/${project}.pem ${certs_dir}/${project}-key.pem
    root * /app/public/${project}
    php_server
    file_server
}
SNIPPET

    # Signal FrankenPHP to reload its Caddy config.
    # SIGUSR1 triggers a graceful config reload (zero-downtime).
    local container
    container=$(docker compose -f "$ANVIL_COMPOSE_FILE" ps -q frankenphp 2>/dev/null)
    if [ -n "$container" ]; then
        docker kill -s USR1 "$container"
    fi
}
```

**Tier 2/3 (Staging/Production) — Caddy manages vhosts via its own config:**

For Tier 2/3, vhosts are managed in the Caddyfile (not per-project snippets). The `anvilctl` vhost commands are not used in these tiers — domains are configured in the Caddyfile before deployment.

#### 4.1.7 `anvil/lib/docker.sh`

Update the `anvil_docker_up()` function to select the correct compose file based on `ANVIL_TIER`:

```bash
anvil_docker_up() {
    case "$ANVIL_TIER" in
        laptop)
            COMPOSE_FILE="$ANVIL_COMPOSE_FILE" ;;  # docker-compose.local.yml
        staging)
            COMPOSE_FILE="${ANVIL_ROOT}/docker/docker-compose.staging.yml" ;;
        production)
            COMPOSE_FILE="${ANVIL_ROOT}/docker/docker-compose.ec2.yml" ;;
    esac
    docker compose -f "$COMPOSE_FILE" up -d
}
```

#### 4.1.8 `anvil/provisioning/certbot-setup.sh`

**Largely obsolete.** Caddy handles Let's Encrypt automatically. This file is archived as `certbot-setup.sh.legacy` for reference. The EC2 provisioning flow no longer needs a separate certbot step — Caddy issues certificates on first request.

If there is a specific need for DNS-01 challenge certificates (wildcard certs), Caddy supports this via the `dns` directive in the Caddyfile with a plugin like `caddy-dns-route53`.

#### 4.1.9 `anvil/docker/php/entrypoint-ssm.sh`

Replace `exec php-fpm` with `exec "$@"` (runtime-agnostic handoff):

```bash
# Line 65 — Before:
# exec php-fpm
# After:
exec "$@"
```

#### 4.1.10 `anvil/docker/php/Dockerfile`

Renamed to `Dockerfile.fpm-legacy` (archived, no longer used). The official `dunglas/frankenphp` image includes all needed extensions.

---

## 5. Workstream W2: Installer Multi-Distro Support

### 5.1 Problem

`install.sh` is entirely Debian/Ubuntu-specific (`apt-get`, `dpkg`, Docker's Ubuntu apt repo, `systemd-resolved`). It cannot run on Fedora, RHEL, Arch, or Amazon Linux.

### 5.2 Architecture: Distro Detection + Abstracted Package Functions

```bash
_anvil_detect_distro() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        ANVIL_DISTRO_ID="${ID}"
        ANVIL_DISTRO_VERSION="${VERSION_ID}"
        ANVIL_DISTRO_LIKE="${ID_LIKE:-}"
    else
        echo "ERROR: Cannot detect distro (/etc/os-release missing)"
        exit 1
    fi
}

_anvil_pkg_install() {
    case "$ANVIL_DISTRO_ID" in
        ubuntu|debian|pop-os|linuxmint)
            sudo apt-get update -qq && sudo apt-get install -y "$@" ;;
        fedora|rhel|centos|rocky|alma)
            sudo dnf install -y "$@" ;;
        arch|manjaro|endeavouros)
            sudo pacman -Syu --noconfirm "$@" ;;
        amzn)
            sudo dnf install -y "$@" ;;
        *)
            echo "ERROR: Unsupported distro: $ANVIL_DISTRO_ID"; exit 1 ;;
    esac
}

_anvil_pkg_is_installed() {
    case "$ANVIL_DISTRO_ID" in
        ubuntu|debian|pop-os|linuxmint) dpkg -s "$1" &>/dev/null ;;
        fedora|rhel|centos|rocky|alma|amzn) rpm -q "$1" &>/dev/null ;;
        arch|manjaro|endeavouros) pacman -Q "$1" &>/dev/null ;;
    esac
}
```

### 5.3 Distro-Specific Docker Install

```bash
_anvil_install_docker() {
    case "$ANVIL_DISTRO_ID" in
        ubuntu|debian|pop-os|linuxmint)
            # Docker official apt repo (existing logic).
            sudo apt-get update -qq
            sudo install -m 0755 -d /etc/apt/keyrings
            curl -fsSL https://download.docker.com/linux/${ID}/gpg | \
                sudo gpg --dearmor -o /etc/apt/keyrings/docker.asc
            echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] \
https://download.docker.com/linux/${ID} $(. /etc/os-release && echo $VERSION_CODENAME) stable" | \
                sudo tee /etc/apt/sources.list.d/docker.list
            sudo apt-get update -qq
            sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
            ;;
        fedora|rhel|centos|rocky|alma)
            sudo dnf install -y dnf-plugins-core
            sudo dnf config-manager --add-repo https://download.docker.com/linux/fedora/docker-ce.repo
            sudo dnf install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
            ;;
        arch|manjaro|endeavouros)
            sudo pacman -Syu --noconfirm docker docker-compose-plugin
            ;;
        amzn)
            sudo dnf install -y docker
            ;;
    esac
    sudo systemctl enable --now docker
    sudo usermod -aG docker "$(whoami)"
}
```

### 5.4 DNS Setup (dnsmasq — Tier 1 Only)

The DNS setup is distro-sensitive but the core logic is the same across all distros that use systemd-resolved (which is most of them):

```bash
_anvil_install_dns() {
    _anvil_pkg_install dnsmasq
    echo "address=/.test/127.0.0.1" | sudo tee "$ANVIL_DNSMASQ_CONF"
    sudo systemctl enable --now dnsmasq

    if systemctl is-active --quiet systemd-resolved 2>/dev/null; then
        sudo mkdir -p "$(dirname "$ANVIL_RESOLVED_CONF")"
        echo -e "[Resolve]\nDNSStubListener=no" | sudo tee "$ANVIL_RESOLVED_CONF"
        sudo systemctl restart systemd-resolved
    fi

    if [ -f /run/systemd/resolve/resolv.conf ]; then
        sudo ln -sf /run/systemd/resolve/resolv.conf /etc/resolv.conf
    else
        echo -e "nameserver 127.0.0.1\nnameserver 8.8.8.8" | sudo tee /etc/resolv.conf
    fi
}
```

### 5.5 Non-Interactive Flags

```bash
# --yes: skip all prompts, install everything
# --tier <laptop|staging|production>: set ANVIL_TIER
# --skip-dns: skip dnsmasq (for staging/production)
# --skip-mkcert: skip local CA (for staging/production)

# Example: staging VM bootstrap
sudo ./install.sh --yes --tier staging --skip-dns --skip-mkcert
```

---

## 6. Workstream W3: Bug Fixes

### W3-1: Absolute Host Path in Cert Volume Mount

**File:** `docker-compose.local.yml` line 36
**Current:** `./nginx/certs:/home/dgi/www/DGLab/anvil/docker/nginx/certs:ro`
**Fix:** Mount at a container-internal path:

```yaml
# Before (machine-specific):
- ./nginx/certs:/home/dgi/www/DGLab/anvil/docker/nginx/certs:ro
# After (portable):
- ./nginx/certs:/etc/certs/local:ro
```

Update `lib/vhost.sh` to render cert paths as `/etc/certs/local/<project>/*.pem` (matching the container mount).

### W3-2: Hardcoded SSH User

**File:** `lib/ec2.sh` line 693
**Fix:** Replace `"ec2-user@${ec2_host}"` with `"${ANVIL_EC2_SSH_USER}@${ec2_host}"`.

### W3-3: Placeholder Repo URL

**File:** `anvil.conf` line 142
**Fix:** Replace `https://github.com/example/anvil.git` with the real repository URL before any EC2 deployment.

---

## 7. Workstream W4: Edge / Production-Native Tier

### 7.1 Why a Native (Non-Docker) Tier

Docker adds overhead: the container daemon, image layers, bridge networking, and volume management consume memory and CPU. On an edge node (e.g., a 2 vCPU / 4 GB RAM VM at a CDN PoP), this overhead is significant.

Tier 3 installs Caddy, Tengine, and FrankenPHP as native systemd services — no Docker, no compose. The database and Redis are external (managed RDS, ElastiCache, or separate hosts).

### 7.2 Tier 3 Architecture (Full Three-Layer)

```
Client
  │
  │  :443 (HTTPS + HTTP/3)
  ▼
┌──────────────────────────────────────────────┐
│  Caddy (native, systemd: caddy.service)       │
│  /etc/caddy/Caddyfile                         │
│  Automatic HTTPS, HTTP/3, Mercure hub         │
│  Static file cache, rate limiting             │
└──────────────────┬───────────────────────────┘
                   │ proxy_pass http://127.0.0.1:80
                   ▼
┌──────────────────────────────────────────────┐
│  Tengine (native, systemd: tengine.service)    │
│  /etc/tengine/tengine.conf                    │
│  Upstream health checks, DSO, Lua             │
│  Prometheus metrics (loopback :9113)          │
└──────────────────┬───────────────────────────┘
                   │ proxy_pass http://frankenphp_pool
           ┌───────┼───────┐
           ▼       ▼       ▼
     ┌──────────┐ ┌──────────┐
     │FPHP #1   │ │FPHP #2   │  ... N instances
     │:8081     │ │:8082     │
     │systemd:   │ │systemd:   │
     │frankenphp@1│ │frankenphp@2│
     └──────────┘ └──────────┘
```

### 7.3 Tier 3 Systemd Units

```ini
# /etc/systemd/system/caddy.service
[Unit]
Description=Caddy Edge Server
After=network.target

[Service]
Type=simple
ExecStart=/usr/local/bin/caddy run --config /etc/caddy/Caddyfile
ExecReload=/usr/local/bin/caddy reload --config /etc/caddy/Caddyfile
Restart=on-failure
RestartSec=5
LimitNOFILE=65536

[Install]
WantedBy=multi-user.target
```

```ini
# /etc/systemd/system/tengine.service
[Unit]
Description=Tengine Internal Load Balancer
After=network.target

[Service]
Type=forking
PIDFile=/var/run/tengine.pid
ExecStartPre=/usr/sbin/tengine -t
ExecStart=/usr/sbin/tengine
ExecReload=/bin/kill -s HUP $MAINPID
Restart=on-failure
LimitNOFILE=65536

[Install]
WantedBy=multi-user.target
```

```ini
# /etc/systemd/system/frankenphp@.service  (template unit)
# Instantiate: systemctl enable --now frankenphp@1 frankenphp@2
[Unit]
Description=FrankenPHP Worker Instance %i
After=network.target

[Service]
Type=simple
WorkingDirectory=/opt/anvil/app
ExecStart=/usr/local/bin/frankenphp run \
    --workers=${FRANKENPHP_WORKERS:-2} \
    --listen=127.0.0.1:808%i
Restart=on-failure
RestartSec=5
LimitNOFILE=65536
Environment=APP_ENV=production
Environment=FRANKENPHP_MAX_REQUESTS=${FRANKENPHP_MAX_REQUESTS:-10000}

[Install]
WantedBy=multi-user.target
```

The template unit (`frankenphp@.service`) allows running multiple FrankenPHP instances on different ports (8081, 8082, ...) via systemd instantiation. Tengine's upstream pool lists these ports and health-checks them.

### 7.4 Tier 3 Install Script: `anvil/install-edge.sh` (NEW)

```bash
#!/usr/bin/env bash
# install-edge.sh — Tier 3 (production/edge) native installer.
# Installs Caddy + Tengine + FrankenPHP as systemd services.
set -euo pipefail

# ... distro detection (reuse W2 functions) ...

# 1. Install Caddy (official binary).
_anvil_install_caddy() {
    local arch="$(uname -m)"
    case "$arch" in x86_64) arch="amd64" ;; aarch64) arch="arm64" ;; esac
    curl -sL "https://caddyserver.com/api/download?os=linux&arch=${arch}" \
        -o /usr/local/bin/caddy
    chmod +x /usr/local/bin/caddy
}

# 2. Install Tengine from source (same Dockerfile logic).
_anvil_install_tengine() {
    # ... (same ./configure && make as the Dockerfile, but on host) ...
}

# 3. Install FrankenPHP binary.
_anvil_install_frankenphp() {
    local arch="$(uname -m)"
    case "$arch" in x86_64) arch="amd64" ;; aarch64) arch="arm64" ;; esac
    curl -sL "https://github.com/dunglas/frankenphp/releases/latest/download/"\
"frankenphp-linux-${arch}.tar.gz" \
        | sudo tar xz -C /usr/local/bin frankenphp
}

# 4. Create systemd units (see §7.3).
# 5. Configure Tengine upstream pool.
# 6. Configure Caddyfile.
# 7. Enable services.
```

### 7.5 Tier 3 Deployment Flow

```bash
# 1. Run the edge installer.
sudo ./install-edge.sh

# 2. Deploy application code to /opt/anvil/app/.

# 3. Configure /etc/caddy/Caddyfile (real domain, Mercure keys).

# 4. Configure /etc/tengine/tengine.conf (upstream pool).

# 5. Enable and start services (order matters).
sudo systemctl daemon-reload
sudo systemctl enable --now tengine
sudo systemctl enable --now frankenphp@1 frankenphp@2
sudo systemctl enable --now caddy

# 6. Verify.
curl -sI https://app.example.com
systemctl status caddy tengine frankenphp@1

# Caddy auto-issues Let's Encrypt cert on first request.
```

### 7.6 Scaling FrankenPHP Instances

```bash
# Add a third FrankenPHP instance:
sudo systemctl enable --now frankenphp@3

# Add its port to Tengine's upstream pool (no restart needed
# if using Tengine's dynamic upstream API — future Phase 2).
# For now, edit tengine.conf and reload:
# (add 'server 127.0.0.1:8083;' to upstream block)
sudo systemctl reload tengine

# Remove an instance:
sudo systemctl stop frankenphp@3
sudo systemctl disable frankenphp@3
```

---

## 8. Workstream W5: Mercure Hub

### 8.1 What Mercure Provides

Mercure is a real-time pub/sub protocol built on top of SSE (Server-Sent Events). In DGLab's architecture, it serves:

- **HUB-31** (Real-Time Analytics): Live BI dashboard updates pushed to browser clients.
- **ISPOKE-12** (Impact Monitor): Feature-flag rollout impact displayed in real time.
- **ISPOKE-13** (Revenue Dashboard): Live MRR/churn/LTV metrics.
- **Future Spokes**: Any spoke that needs server-push to clients (notifications, collaboration, etc.).

### 8.2 Where Mercure Lives

Mercure runs in the **standalone Caddy** at the edge (Tier 2 and Tier 3). Not inside FrankenPHP. Reasons:

1. **Decoupled from PHP worker lifecycle.** Mercure stays available during FrankenPHP deployments and restarts. Clients don't lose their SSE connections.
2. **Lower latency.** SSE connections terminate at the edge, not inside the PHP app server. One fewer network hop for pushed events.
3. **PHP workers publish via HTTP.** A PHP worker publishes an event by sending a POST to `https://internal/mercure`. This is a simple HTTP call, not an in-process API — and it works across multiple FrankenPHP instances.
4. **Transport flexibility.** In production, Mercure uses Redis as its transport backend (via Caddy's `transport_url`). This means Mercure events are distributed across multiple Caddy instances if needed.

### 8.3 Caddyfile Mercure Configuration

```caddyfile
{
    mercure {
        # JWT keys for authentication.
        # Publishers: PHP workers (internal, can use a strong key).
        # Subscribers: browser clients (public key with restricted claims).
        publisher_jwt_key "${MERCURE_PUBLISHER_JWT_KEY}"
        subscriber_jwt_key "${MERCURE_SUBSCRIBER_JWT_KEY}"

        # CORS: restrict to your application origins.
        cors_origins "https://app.example.com"
        publish_origins "https://app.example.com"

        # Transport: BoltDB for staging, Redis for production.
        # Staging:
        # transport_url "bolt:///var/lib/mercure/mercure.db"
        # Production:
        transport_url "redis://${REDIS_HOST}:6379"
    }
}

domain.com {
    # ... reverse_proxy to FrankenPHP ...

    # Mercure endpoint.
    handle /.well-known/mercure {
        mercure
    }
}
```

### 8.4 PHP Worker Publishing

```php
// Inside a FrankenPHP worker — publish a Mercure event.
// The worker POSTs to the internal Mercure endpoint.
use GuzzleHttp\Client;

$client = new Client();
$client->post('https://127.0.0.1/.well-known/mercure', [
    'headers' => [
        'Authorization' => 'Bearer ' . $publisherJwt,
        'Content-Type'  => 'application/x-www-form-urlencoded',
    ],
    'body' => http_build_query([
        'topic' => 'https://example.com/metrics/hub31',
        'data'  => json_encode(['metric' => 'mrr', 'value' => 45000]),
    ]),
]);
```

On Tier 1 (laptop), the Mercure endpoint is at `https://<project>.test/.well-known/mercure` (FrankenPHP's internal Caddy). On Tier 2/3, it's at the edge Caddy's domain.

---

## 9. Staging VM Tier Details

### 9.1 Purpose

Staging VMs validate the production configuration before it reaches production. They run Caddy (edge) + FrankenPHP (app), with real DNS and Let's Encrypt — but no Tengine (single FrankenPHP instance).

### 9.2 Staging Provisioning Flow

```bash
# 1. Provision a staging VM (2 vCPU, 4 GB RAM minimum).

# 2. SSH in and install.
sudo ./install.sh --yes --tier staging --skip-dns --skip-mkcert

# 3. Clone the repo.
git clone <repo-url> /opt/anvil && cd /opt/anvil/anvil

# 4. Configure the staging Caddyfile with the real domain.
cp config/Caddyfile.staging config/Caddyfile
# Edit config/Caddyfile: replace staging.example.com with the real domain.

# 5. Set the Mercure JWT keys (generate with openssl).
export MERCURE_PUBLISHER_JWT_KEY="$(openssl rand -base64 32)"
export MERCURE_SUBSCRIBER_JWT_KEY="$(openssl rand -base64 32)"

# 6. Start the stack.
anvilctl start

# 7. Point real DNS at the VM's public IP.

# 8. Caddy auto-issues Let's Encrypt on the first HTTPS request.
curl -sI https://staging.example.com

# 9. Verify Mercure.
curl -N https://staging.example.com/.well-known/mercure
```

### 9.3 Staging vs Production

| Dimension | Staging | Production |
|---|---|---|
| Layers | Caddy + FrankenPHP | Caddy + Tengine + FrankenPHP |
| Workers | 2–4 | Match vCPU per instance |
| FRANKENPHP_MAX_REQUESTS | 1000 (catch leaks) | 10000 |
| OpCache validate_timestamps | 1 (dev convenience) | 0 (immutable deploy) |
| Mercure transport | BoltDB (file) | Redis |
| Database | RDS db.t3.micro | RDS db.t3.micro+ |
| Monitoring | Logs only | Prometheus + CloudWatch |
| Tengine DSO | N/A | Prometheus exporter |

---

## 10. Migration Path

### Phase 1: W1 + W3 (Laptop) — FrankenPHP Only

1. Create `config/Caddyfile.laptop`.
2. Update `docker-compose.local.yml` (replace nginx + php with single FrankenPHP).
3. Rewrite `lib/vhost.sh` (nginx vhost → Caddyfile snippets + SIGUSR1).
4. Apply W3 bug fixes (cert path, SSH user, deprecated aliases in anvil.conf).
5. Add all new variables to `anvil.conf`.
6. Archive `docker/php/Dockerfile` → `Dockerfile.fpm-legacy`.
7. Update `entrypoint-ssm.sh` to `exec "$@"`.
8. **Test:** `anvilctl start` → `https://demo.test` serves PHP via FrankenPHP's embedded Caddy.
9. **Test:** `anvilctl new testproject` → generates Caddyfile snippet, SIGUSR1 reload.
10. **Test:** `anvilctl watch` → inotify creates Caddyfile snippet, reloads.
11. **Test:** Rollback — `git checkout` old compose, verify PHP-FPM stack restores.

### Phase 2: W1 (Staging) — Caddy + FrankenPHP

1. Create `config/Caddyfile.staging`.
2. Create `docker-compose.staging.yml` (Caddy + FrankenPHP + Redis).
3. Update `lib/docker.sh` to select compose file by `ANVIL_TIER`.
4. **Test:** Deploy staging VM → Caddy issues Let's Encrypt → HTTPS works.
5. **Test:** Mercure SSE endpoint responds at `/.well-known/mercure`.
6. Archive `provisioning/certbot-setup.sh` (Caddy replaces it).

### Phase 3: W2 (Multi-Distro Installer)

1. Add distro detection to `install.sh`.
2. Abstract package manager functions.
3. Add `--tier`, `--skip-dns`, `--skip-mkcert` flags.
4. **Test:** `sudo ./install.sh --yes --tier staging` on Fedora 42.
5. **Test:** `sudo ./install.sh --yes --tier laptop` on Arch Linux.
6. **Test:** `sudo ./install.sh --yes` on Ubuntu 24.04 (regression).

### Phase 4: W4 + W1 (Production/Edge) — Full Three-Layer

1. Create `docker/tengine/Dockerfile`.
2. Create `install-edge.sh` (Caddy + Tengine + FrankenPHP from binaries/source).
3. Create systemd unit files (caddy, tengine, frankenphp@.service).
4. Create Tengine config template for Tier 3.
5. **Test:** Native install on fresh Ubuntu 24.04 minimal VM.
6. **Test:** Deploy real domain, Caddy auto-issues cert, HTTPS works.
7. **Test:** Tengine health-checks remove dead FrankenPHP instances.
8. **Test:** DSO Prometheus module loads and exposes `/metrics` on loopback.
9. **Test:** Scale FrankenPHP: `systemctl enable --now frankenphp@3`.

### Phase 5: W5 (Mercure Hub)

1. Generate JWT keys for Mercure publisher/subscriber.
2. Add Mercure config to Caddyfiles (staging: BoltDB, production: Redis).
3. Add Mercure publishing helper to DGLab's PHP codebase.
4. **Test:** PHP worker publishes event → browser subscriber receives it.

### Phase 6: Future Optimizations

- **Tengine dynamic upstream API:** Add/remove FrankenPHP instances without Tengine reload.
- **Tengine Lua routing:** Canary deployments, A/B testing at the LB layer.
- **DSO observability:** Hot-load Prometheus, OpenTelemetry, or security modules.
- **FrankenPHP worker preloading:** `--preload` for DGLab Kernel pre-boot.
- **HTTP/3 measurement:** Benchmark QUIC vs TCP for DGLab's workload.

---

## 11. Rollback Strategy

### Tier 1 (Laptop)

```bash
git checkout HEAD -- anvil/docker/docker-compose.local.yml anvil/lib/vhost.sh
anvilctl stop && anvilctl start
```

### Tier 2 (Staging VM)

```bash
# Switch back to old compose + nginx + PHP-FPM.
COMPOSE_FILE=docker/docker-compose.ec2.yml.bak anvilctl stop
# Restore old compose file from git.
git checkout HEAD -- docker-compose.staging.yml
```

### Tier 3 (Production/Edge)

```bash
# Restore backed-up binaries.
sudo systemctl stop frankphp@1
sudo cp /usr/local/bin/frankenphp.bak /usr/local/bin/frankenphp
sudo systemctl start frankenphp@1
# Same for Caddy and Tengine.
```

Always keep `.bak` copies of binaries before upgrading. Add this to the Tier 3 deployment runbook.

---

## 12. Risk Register

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| **Tengine CVE lag** | Medium | High | Pin `TENGINE_VERSION`; subscribe to Tengine GitHub advisories; maintain nginx/Tengine fallback config |
| **FrankenPHP worker memory leak** | Low | High | `pulse()` WeakMap auto-evicts; `FRANKENPHP_MAX_REQUESTS` restarts workers; monitor via DSO Prometheus |
| **Caddy automatic HTTPS renewal failure** | Low | High | Caddy retries automatically; monitor certificate expiry; backup: manual certbot as fallback |
| **Three-layer latency** | Low | Low | ~0.2ms total (Caddy→Tengine→FrankenPHP); negligible vs PHP execution |
| **DSO module incompatibility** | Low | Medium | Pin module versions; test in staging before production |
| **Mercure JWT key compromise** | Low | High | Use strong RSA/ES256 keys; rotate via CI/CD; store in secrets manager |
| **Tier 3 native install complexity** | Medium | Medium | `install-edge.sh` automates it; test on Ubuntu 24.04 minimal first |
| **Multi-distro installer regression** | Medium | Medium | Test matrix: Ubuntu 24.04, Fedora 42, Arch; keep Ubuntu path as default |
| **FrankenPHP embedded Caddy config conflicts** | Low | Medium | Tier 1 uses a separate Caddyfile from Tier 2/3; test independently |
| **Caddy HTTP/3 UDP :443 conflict with system** | Low | Medium | Caddy gracefully falls back to HTTP/2 if UDP :443 is unavailable; no hard failure |

---

## 13. Validation Checklist

### Tier 1 (Laptop)

- [ ] `docker compose up -d` starts FrankenPHP container
- [ ] `https://demo.test` serves a PHP page (FrankenPHP's embedded Caddy + TLS)
- [ ] `anvilctl new testproj` generates Caddyfile snippet, SIGUSR1 reloads
- [ ] `anvilctl ssl testproj` issues mkcert cert, TLS trusted
- [ ] `anvilctl watch` auto-creates Caddyfile snippet on new `www/` directory
- [ ] `phpinfo()` shows `frankenphp` in SERVER_SOFTWARE
- [ ] OpCache enabled and warm (`opcache_get_status()`)
- [ ] HTTP/3 works: `curl --http3 https://demo.test` returns 200
- [ ] Mercure SSE endpoint responds at `/.well-known/mercure` (if enabled)
- [ ] Rollback: `git checkout` restores PHP-FPM stack

### Tier 2 (Staging VM)

- [ ] `install.sh --yes --tier staging --skip-dns --skip-mkcert` succeeds
- [ ] `docker compose up` starts Caddy + FrankenPHP containers
- [ ] Caddy auto-issues Let's Encrypt certificate (check Caddy logs)
- [ ] HTTPS serves PHP via FrankenPHP
- [ ] HTTP/3 works: `curl --http3 https://staging.example.com`
- [ ] Mercure SSE endpoint responds with JWT-authenticated topics
- [ ] RDS connection works (if configured)

### Tier 3 (Production/Edge)

- [ ] `install-edge.sh` completes on fresh Ubuntu 24.04 minimal
- [ ] `systemctl status caddy tengine frankenphp@1` → all active
- [ ] Real domain serves HTTPS (Caddy auto-issued Let's Encrypt)
- [ ] HTTP/3 works
- [ ] Tengine health-checks detect dead FrankenPHP instance and remove it from pool
- [ ] DSO Prometheus module loads, `/metrics` on loopback
- [ ] `systemctl restart frankenphp@1` → zero-downtime (Caddy retries)
- [ ] `systemctl enable --now frankenphp@3` → Tengine routes to 3 instances
- [ ] Mercure hub delivers events from PHP publisher to browser subscriber

---

## 14. Relationship to Architecture Documents

| Document | Relationship |
|---|---|
| `DGLAB-AS-OS-RUNTIME.md` | Specifies the Fiber-based cooperative scheduler that mandates FrankenPHP (OD-07). This doc implements the deployment consequence. |
| `OPEN-DECISIONS.md` OD-07 | Records FrankenPHP as accepted runtime. This doc implements that decision. |
| `OPEN-DECISIONS.md` OD-08 | Async I/O library choice (ReactPHP/Amp) — library-agnostic here; the proxy layers don't care which event loop FrankenPHP uses. |
| `CORE-02.md` | The `pulse()` scope and `WeakMap` assume a Fiber-capable runtime. FrankenPHP provides this. |
| `DEPLOY-01.md` | Specifies OCI base image (PHP-FPM + Nginx + Supervisor). This doc supersedes the PHP-FPM and Nginx components. DEPLOY-01 must be updated. |
| `HUB-31.md` | Real-time analytics needs server-push to browsers. Mercure (via Caddy) provides this as the SSE/WebSocket transport. |
| `STRUCTURE-06-Boot.md` | Boot sequence maps to Linux boot. FrankenPHP worker startup = "kernel init". |
| `anvil/README.md` | Must be updated to reference Caddy + Tengine + FrankenPHP, three-tier model, and Mercure. |
| `RUNBOOK-ANVIL-DNS.md` | Remains valid — DNS conflict resolution applies to Tier 1 (dnsmasq) only. |

---

## 15. Files Changed Per Workstream

### W1 (Three-Layer Serving Stack)

| File | Action | What Changes |
|---|---|---|
| `config/anvil.conf` | Modify | Add CADDY_*, TENGINE_*, FRANKENPHP_*, MERCURE_*, ANVIL_TIER vars; proxy-agnostic path aliases |
| `config/Caddyfile.laptop` | **Create** | FrankenPHP's Caddyfile for *.test dev domains |
| `config/Caddyfile.staging` | **Create** | Caddy edge config for staging (real domain, Mercure, BoltDB) |
| `config/Caddyfile.production` | **Create** | Caddy edge config for production (Mercure, Redis transport, rate limit) |
| `docker/docker-compose.local.yml` | Modify | Replace nginx+php with single FrankenPHP (embedded Caddy, TLS, HTTP/3) |
| `docker/docker-compose.staging.yml` | **Create** | Caddy (edge) + FrankenPHP (app) + Redis |
| `docker/docker-compose.ec2.yml` | Modify | Replace nginx+php with Caddy+FrankenPHP; add Caddy volumes |
| `docker/tengine/Dockerfile` | **Create** | Tengine build from source (Tier 3 only) |
| `lib/vhost.sh` | **Major rewrite** | nginx vhost → Caddyfile snippets; reload via SIGUSR1 instead of `nginx -s reload` |
| `lib/docker.sh` | Modify | Select compose file by ANVIL_TIER |
| `lib/ec2.sh` | Modify | Comment updates; W3-2 SSH user fix |
| `provisioning/certbot-setup.sh` | **Archive** | Renamed to `.legacy` — Caddy replaces certbot |
| `docker/php/entrypoint-ssm.sh` | Modify | `exec php-fpm` → `exec "$@"` |
| `docker/php/Dockerfile` | Rename | → `Dockerfile.fpm-legacy` |
| `docker/php/php.ini` | Modify | Add JIT config; validate_timestamps comment per tier |
| `docker/nginx/templates/vhost.conf.tpl` | **Archive** | No longer used (Caddyfile replaces nginx vhost) |

### W2 (Multi-Distro Installer)

| File | Action | What Changes |
|---|---|---|
| `install.sh` | Modify | Distro detection; abstracted package manager; --tier/--skip-dns/--skip-mkcert |

### W3 (Bug Fixes)

| File | Action | What Changes |
|---|---|---|
| `docker/docker-compose.local.yml` | Modify | Fix cert volume mount path (W3-1) |
| `lib/vhost.sh` | Modify | Fix cert path rendering (W3-1) |
| `lib/ec2.sh` | Modify | Fix hardcoded SSH user (W3-2) |
| `config/anvil.conf` | Modify | Fix placeholder repo URL (W3-3) |

### W4 (Edge / Native Tier)

| File | Action | What Changes |
|---|---|---|
| `install-edge.sh` | **Create** | Native Tier 3 installer (Caddy binary + Tengine source + FrankenPHP binary + systemd) |
| `provisioning/caddy.service` | **Create** | systemd unit for Caddy |
| `provisioning/tengine.service` | **Create** | systemd unit for Tengine |
| `provisioning/frankenphp@.service` | **Create** | systemd template unit for FrankenPHP instances |

### W5 (Mercure)

| File | Action | What Changes |
|---|---|---|
| `config/Caddyfile.staging` | Includes | Mercure block with BoltDB transport |
| `config/Caddyfile.production` | Includes | Mercure block with Redis transport |

---

**Provenance:** Written 2026-08-26 (v3). Supersedes v2 (same date, Tengine+FrankenPHP two-layer) and v1 (2026-08-24, serving-layer swap only). Triggered by architecture lead's question about combining Caddy, Tengine, and FrankenPHP for production and development. Introduces the three-component proxy architecture with tiered layer activation, Mercure hub for real-time server-push, HTTP/3 via Caddy, and systemd-native edge deployment.
