# Anvil Reimplementation Instruction

**Status:** Proposed (architecture lead review required)
**Date:** 2026-08-26
**Author:** DGCI (architecture lead), analysis by Z.ai
**Supersedes:** `anvil/docs/ReImplementation_Instruction.md` v1 (2026-08-24)
**Gate decisions:** OD-07 (Fiber-based cooperative runtime → FrankenPHP mandatory)

---

## 0. Executive Summary

This document specifies how to reimplement Anvil across **three deployment tiers** — developer laptops, staging VMs, and production servers or edge nodes — combining **Tengine 3.x** (reverse proxy / TLS / DSO) with **FrankenPHP** (long-lived PHP workers). It supersedes the v1 instruction (2026-08-24) which covered only the serving-layer swap for local + EC2.

The reimplementation has four workstreams:

| # | Workstream | Scope |
|---|---|---|
| W1 | **Serving layer** (Tengine + FrankenPHP) | Replace nginx:1.27-alpine + php:8.3-fpm in both compose files |
| W2 | **Installer multi-distro** | Extend `install.sh` beyond Debian/Ubuntu (add Fedora/RHEL, Arch) |
| W3 | **Bug fixes** | Fix the absolute-path cert leak, the hardcoded SSH user, and the placeholder repo URL |
| W4 | **Edge tier** | Add a lightweight deployment mode for resource-constrained edge nodes |

---

## 1. Purpose

Anvil's current serving layer (nginx 1.27 + PHP-FPM 8.3) is incompatible with OD-07. The OS-metaphor runtime requires PHP Fibers, which need a long-lived worker process — FrankenPHP in worker mode. PHP-FPM terminates the process after every request, making Fiber-scoped state impossible.

The v1 instruction (2026-08-24) addressed the serving-layer swap for two targets (local dev + EC2). This v2 extends that work to cover:

- **Developer laptops** (major Linux distros — Ubuntu, Fedora, Arch, Pop!_OS)
- **Staging VMs** (cloud VMs for pre-production validation)
- **Production servers / edge nodes** (lighter footprint, no Docker optional, systemd-native)

It also incorporates bug fixes discovered during the full codebase audit and adds an edge-node deployment tier that the original Anvil design did not anticipate.

---

## 2. Tier Model

```
┌─────────────────────────────────────────────────────────────────┐
│                     TIER 1: LAPTOP                             │
│  Full stack: Tengine + FrankenPHP + MySQL + Redis + phpMyAdmin │
│  mkcert TLS, dnsmasq *.test, Web UI, TUI, inotify watcher      │
│  OS: Ubuntu, Fedora, Arch, Pop!_OS                             │
│  Purpose: daily development, per-project *.test domains         │
├─────────────────────────────────────────────────────────────────┤
│                     TIER 2: STAGING VM                         │
│  Full stack: Tengine + FrankenPHP + RDS + Redis                │
│  Let's Encrypt TLS, no Web UI exposed, SSH tunnel for mgmt     │
│  OS: Ubuntu 24.04 LTS / Amazon Linux 2023                     │
│  Purpose: pre-production validation, integration testing       │
├─────────────────────────────────────────────────────────────────┤
│                     TIER 3: PRODUCTION / EDGE                  │
│  Lean stack: Tengine + FrankenPHP (no Docker, systemd-native)  │
│  Let's Encrypt TLS, health checks, DSO observability           │
│  OS: Ubuntu 24.04 LTS (minimal), Debian 12                    │
│  Purpose: production serving, edge deployment, CDN-origin      │
└─────────────────────────────────────────────────────────────────┘
```

### Tier Differences at a Glance

| Dimension | Tier 1 (Laptop) | Tier 2 (Staging VM) | Tier 3 (Prod/Edge) |
|---|---|---|---|
| PHP runtime | FrankenPHP (Docker) | FrankenPHP (Docker) | FrankenPHP (native/systemd) |
| Reverse proxy | Tengine (Docker) | Tengine (Docker) | Tengine (native/systemd) |
| Database | MySQL 8.0 (Docker) | RDS MySQL (AWS) | External (RDS / managed) |
| Redis | Yes (Docker) | Yes (Docker) | Optional (external) |
| TLS | mkcert (trusted local CA) | Let's Encrypt (certbot) | Let's Encrypt (certbot + auto-renew) |
| DNS | dnsmasq *.test → 127.0.0.1 | Real DNS (Route 53) | Real DNS (any provider) |
| Web UI | Yes (127.0.0.1:9999) | Via SSH tunnel only | No (use monitoring instead) |
| TUI | Yes (dialog/whiptail) | No | No |
| Wildcard vhosts | Yes (*.test) | No (explicit domains) | No (explicit domains) |
| phpMyAdmin | Yes (127.0.0.1:8080) | Via SSH tunnel only | No |
| inotify watcher | Yes | No | No |
| DSO modules | Dev (optional) | Staging (optional) | Production (prometheus, security) |
| Containerization | Docker Compose | Docker Compose | None (native systemd services) |

---

## 3. Why Tengine + FrankenPHP (Recap)

### 3.1 Tengine Over Plain nginx

Tengine is Alibaba's nginx fork. For DGLab's OS-metaphor runtime, three features justify the divergence:

| Feature | nginx 1.27 | Tengine 3.x | DGLab Relevance |
|---|---|---|---|
| **DSO (Dynamic Shared Objects)** | No | Yes | Hot-load/unload modules without recompiling — maps to loadable kernel modules in the OS metaphor |
| **Dynamic server/location/upstream** | No | Yes | Add/remove vhosts without reload — aligns with Anvil's inotify vhost watcher |
| **Upstream health checks** | 3rd-party module | Built-in | Auto-remove dead FrankenPHP workers from the pool |
| **Built-in ngx_lua** | OpenResty only | Yes | Complex routing, auth, rate-limiting in the proxy layer |
| **nginx config compat** | N/A | 100% | Drop-in replacement — existing vhost.conf.tpl works unchanged |
| **Base nginx version** | 1.27.x | 1.31.x | Newer upstream, later security patches |

**Risk:** Tengine's CVE patch cadence is slower than nginx mainline. Tengine 3.1.0 carried unpatched CVEs until 3.2.0 (July 2026).

**Mitigation:** Pin `TENGINE_VERSION` in `anvil.conf`. Subscribe to [Tengine GitHub advisories](https://github.com/alibaba/tengine/security/advisories). Maintain a tested nginx fallback compose file for emergency rollback.

### 3.2 FrankenPHP Over PHP-FPM

This is a stated consequence of OD-07, not a choice:

| | PHP-FPM (current) | FrankenPHP Worker Mode |
|---|---|---|
| Process model | One process per request, terminated | Long-lived worker, persists across requests |
| Fiber support | N/A (process dies) | Full — Fibers live across worker lifetime |
| `pulse()` scope (CORE-02) | N/A | Required — WeakMap-keyed per-Fiber cache |
| OpCache | Cold on new process spawn | Always warm (worker preloads once) |
| Protocol | FastCGI (`fastcgi_pass`) | HTTP (`proxy_pass`) |
| Performance | Process spawn + teardown per request | No spawn overhead; request = function call |

---

## 4. Workstream W1: Serving Layer (Tengine + FrankenPHP)

### 4.1 Target Architecture (Tiers 1 and 2)

```
                         ┌──────────────────────────────┐
   Internet/Local  ───────▶│  Tengine 3.x (TLS + Proxy)   │
      :443 / :80            │  - TLS termination           │
                            │  - HTTP/2 (+ HTTP/3 future)   │
                            │  - Vhost multiplexing        │
                            │  - Static file serving       │
                            │  - DSO module loading        │
                            │  - Upstream health checks    │
                            └──────────┬───────────────────┘
                                       │ proxy_pass http://frankenphp:80
                                       ▼
                            ┌──────────────────────────────┐
                            │  FrankenPHP (Worker Mode)     │
                            │  - Long-lived PHP workers     │
                            │  - Fiber scheduler (OD-07)    │
                            │  - Mercure (built-in, opt-in) │
                            │  - HTTP on :80                │
                            └──────────┬───────────────────┘
                                       │
                         ┌─────────────┼────────────────┐
                         ▼             ▼                ▼
                    ┌────────┐  ┌────────┐      ┌───────────┐
                    │ MySQL  │  │ Redis  │      │ RDS (EC2) │
                    │(local) │  │        │      │           │
                    └────────┘  └────────┘      └───────────┘
```

### 4.2 File Changes

#### 4.2.1 `anvil/config/anvil.conf`

Add after the existing EC2 block:

```bash
# ── Tengine ──────────────────────────────────────────────
# Version pin — change this to upgrade. Check https://github.com/alibaba/tengine/releases
: "${TENGINE_VERSION:=3.2.0}"

# ── FrankenPHP ───────────────────────────────────────────
: "${FRANKENPHP_VERSION:=latest}"

# Worker count: one per vCPU is the baseline for cooperative scheduling.
# The KernelScheduler's 50ms quantum ensures fairness within a worker.
# Laptop: 2-4 workers. Staging VM: match vCPU. Production: match vCPU.
: "${FRANKENPHP_WORKERS:=2}"

# FrankenPHP internal HTTP port (inside the container or on the host).
: "${FRANKENPHP_LISTEN_PORT:=80}"

# Max requests before a worker restarts (memory leak defense).
# 0 = unlimited (for dev). Production: 10000.
: "${FRANKENPHP_MAX_REQUESTS:=0}"

# ── Tier detection ───────────────────────────────────────
# ANVIL_TIER is set by the installer or systemd environment file.
# Values: laptop | staging | production
: "${ANVIL_TIER:=laptop}"
```

Rename the nginx-related path variables to be proxy-agnostic. Keep the old names as deprecated aliases for backward compatibility:

```bash
# ── Proxy config directories ─────────────────────────────
# The directory on disk is still called nginx/ for git history.
# The variable names are proxy-agnostic for forward compatibility.
: "${PROXY_CONFD_DIR:=${ANVIL_ROOT}/docker/nginx/conf.d}"
: "${PROXY_CERTS_DIR:=${ANVIL_ROOT}/docker/nginx/certs}"
: "${PROXY_TEMPLATES_DIR:=${ANVIL_ROOT}/docker/nginx/templates}"

# Deprecated aliases (kept for scripts that still reference NGINX_*).
NGINX_CONFD_DIR="${PROXY_CONFD_DIR}"
CERTS_DIR="${PROXY_CERTS_DIR}"
NGINX_TEMPLATES_DIR="${PROXY_TEMPLATES_DIR}"
```

#### 4.2.2 `anvil/docker/docker-compose.local.yml`

Replace the `nginx` and `php` services:

```yaml
services:
  # ── Tengine: TLS termination + HTTP reverse proxy ──────
  tengine:
    build:
      context: ./tengine
      dockerfile: Dockerfile
      args:
        TENGINE_VERSION: "${TENGINE_VERSION:-3.2.0}"
    container_name: anvil_tengine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx/conf.d:/etc/tengine/conf.d:ro
      # W3 FIX: use relative path instead of absolute host path.
      # The cert volume is bind-mounted at the SAME path inside the
      # container so the rendered ${SSL_CERT}/${SSL_KEY} paths resolve.
      - ./nginx/certs:/etc/tengine/certs:ro
      - ./nginx/templates:/etc/tengine/templates:ro
      - ./www:/var/www:rw
    depends_on:
      frankenphp:
        condition: service_started
    networks:
      - anvil_net
    restart: unless-stopped

  # ── FrankenPHP: PHP application server (worker mode) ────
  frankenphp:
    image: dunglas/frankenphp:${FRANKENPHP_VERSION:-latest}
    container_name: anvil_frankenphp
    command: >-
      frankenphp run
      --workers=${FRANKENPHP_WORKERS:-2}
      --listen=${FRANKENPHP_LISTEN_PORT:-80}
    volumes:
      - ./www:/app/public
      - ./php/php.ini:/usr/local/etc/php/conf.d/anvil.ini:ro
    environment:
      APP_ENV: development
      FRANKENPHP_MAX_REQUESTS: "${FRANKENPHP_MAX_REQUESTS:-0}"
    networks:
      - anvil_net
    restart: unless-stopped

  # mysql, phpmyadmin, redis — UNCHANGED from current compose
  # (service names, container names, and configuration stay the same)
```

**Key differences from current:**
- `nginx` service → `tengine` service. Container `anvil_tengine`.
- `php` service → `frankenphp` service. Container `anvil_frankenphp`.
- `fastcgi_pass php:9000` → `proxy_pass http://frankenphp:80` (in vhost template).
- **W3 FIX:** Cert volume no longer uses an absolute host path (`/home/dgi/...`). Instead it mounts `./nginx/certs` at `/etc/tengine/certs` inside the container. The vhost template must render cert paths as `/etc/tengine/certs/<project>/*.pem` instead of the host-specific path.

#### 4.2.3 `anvil/docker/docker-compose.ec2.yml`

Same structural changes as local, plus the SSM entrypoint:

```yaml
  frankenphp:
    image: dunglas/frankenphp:${FRANKENPHP_VERSION:-latest}
    container_name: anvil_frankenphp
    command: >-
      frankenphp run
      --workers=${FRANKENPHP_WORKERS:-2}
      --listen=80
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
```

FrankenPHP respects `ENTRYPOINT` + `CMD` separation — the entrypoint resolves RDS credentials via SSM, then `exec "$@"` hands off to the `frankenphp run` command.

#### 4.2.4 `anvil/docker/tengine/Dockerfile` (NEW)

```dockerfile
# Anvil — Tengine 3.x image.
# DSO support enabled for hot-loadable modules.
# Multi-stage: build from source, ship minimal runtime.

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

RUN mkdir -p /etc/tengine/conf.d \
    /var/log/tengine /var/www /var/run

EXPOSE 80 443
CMD ["tengine", "-g", "daemon off;"]
```

**Build notes:**
- `--with-dso`: Dynamic module loading (Tengine's key differentiator).
- `--with-http_upstream_check_module`: Built-in health checks for FrankenPHP workers.
- `--with-http_lua_module`: Embedded Lua for routing, auth, rate-limiting.
- Image is ~40MB (Alpine + compiled Tengine), comparable to `nginx:1.27-alpine` (~30MB).

#### 4.2.5 `anvil/docker/nginx/templates/vhost.conf.tpl`

The template requires one change: the PHP location block switches from FastCGI to HTTP proxy. Everything else (TLS, HTTP/2, static files, redirect) stays the same.

**Replace the `location ~ \.php$` block:**

```nginx
# FrankenPHP worker mode (HTTP reverse proxy).
location ~ \.php$ {
    proxy_pass http://frankenphp:80;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;

    # Stream large uploads directly to FrankenPHP.
    # In the Fiber runtime, a Pulse may accept a large body and
    # process it incrementally via async I/O. Buffering the entire
    # body at the proxy layer defeats this.
    proxy_request_buffering off;
    proxy_buffering off;

    # Timeouts tuned for long-running Pulse execution.
    # The cooperative scheduler's 50ms quantum means a Pulse that
    # doesn't yield blocks its worker for up to 50ms. The proxy
    # timeout must exceed the worst-case Pulse wall-clock time.
    proxy_connect_timeout 5s;
    proxy_read_timeout 300s;
    proxy_send_timeout 300s;
}
```

**Why 300s timeouts:** Some Pulses (batch processing, report generation) may legitimately run for tens of seconds. 300s (5 minutes) is generous; tune down in production via `anvil.conf` (add `PROXY_READ_TIMEOUT` / `PROXY_SEND_TIMEOUT` variables and reference them in the template).

#### 4.2.6 `anvil/lib/vhost.sh`

Two changes:

1. Replace the reload target from `nginx` to `tengine`:
```bash
# Before:
docker compose -f "$ANVIL_COMPOSE_FILE" exec -T nginx nginx -s reload
# After:
docker compose -f "$ANVIL_COMPOSE_FILE" exec -T tengine tengine -s reload
```

2. Replace the health-check target:
```bash
# Before:
docker compose -f "$ANVIL_COMPOSE_FILE" ps -q nginx
# After:
docker compose -f "$ANVIL_COMPOSE_FILE" ps -q tengine
```

3. Update the error message: `"tengine container not running"`.

4. Update the cert path rendering to use `/etc/tengine/certs/` (matching the new volume mount in the compose file).

**Future optimization (Phase 3):** With Tengine's dynamic server/location API, the `tengine -s reload` can be eliminated entirely for vhost-only changes. For now, the reload approach is preserved for safety.

#### 4.2.7 `anvil/lib/docker.sh`

- Update any comment referencing `nginx` as a log-following example.
- No functional changes needed (it delegates to the compose file abstraction).

#### 4.2.8 `anvil/lib/ec2.sh`

- Update comments mentioning "php container" → "FrankenPHP worker" (lines 338, 581).
- **W3 FIX (line 693):** Replace hardcoded `"ec2-user@${ec2_host}"` with `"${ANVIL_EC2_SSH_USER}@${ec2_host}"`.

#### 4.2.9 `anvil/provisioning/certbot-setup.sh`

Four changes:

1. Inline vhost template (line 143): `fastcgi_pass php:9000` → `proxy_pass http://frankenphp:80` with the same proxy headers and timeouts as 4.2.5.
2. Container name checks (lines 167–168): `anvil_nginx` → `anvil_tengine`, `nginx -s reload` → `tengine -s reload`.
3. Inline template: replace `include fastcgi_params` / `fastcgi_param` directives with `proxy_set_header` directives.
4. Update error messages referencing `anvil_nginx`.

#### 4.2.10 `anvil/docker/php/entrypoint-ssm.sh`

**W1 change:** Replace `exec php-fpm` (line 65) with `exec "$@"`. The entrypoint becomes runtime-agnostic:

```bash
# Before:
exec php-fpm
# After:
exec "$@"
```

This way the same entrypoint works whether the CMD is `php-fpm` (old), `frankenphp run ...` (new), or any future runtime. The `exec` ensures PID 1 handoff and signal forwarding.

#### 4.2.11 `anvil/docker/php/Dockerfile`

**Archived but retained.** This file is no longer the PHP execution runtime (FrankenPHP replaces it). Keep it in the repo for reference and as a base for custom extension builds. Rename to `Dockerfile.fpm-legacy` to signal its status.

The official `dunglas/frankenphp` image already includes: `pdo_mysql`, `mysqli`, `gd`, `intl`, `zip`, `opcache`, `redis`, `openssl`. If DGLab needs additional extensions (`protobuf`, `grpc`), build a custom FrankenPHP image that extends the official one — but this is not needed for the initial reimplementation.

#### 4.2.12 `anvil/docker/php/php.ini` (retained, minor update)

```ini
[opcache]
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000

; Dev: check for file changes on every request.
; Prod: set to 0 and redeploy the FrankenPHP container on code change.
opcache.validate_timestamps=1
opcache.revalidate_freq=0
opcache.fast_shutdown=1

; PHP 8.3+ JIT — worker mode benefits significantly because
; the worker process is long-lived and JIT compilation amortizes.
opcache.jit=tracing
opcache.jit_buffer_size=64M

; Fiber stack size (bytes). Default 128K is fine for most Pulses.
; Increase if deep call stacks within a Fiber cause stack overflows.
; opcache.fiber_stack_size=262144
```

### 4.3 FrankenPHP Worker Configuration

#### 4.3.1 Worker Count by Tier

| Tier | Typical vCPU | FRANKENPHP_WORKERS | Rationale |
|---|---|---|---|
| Laptop (dev) | 4–8 | 2 | Leave headroom for IDE, browser, Docker overhead |
| Staging VM | 2–4 | 2–4 | Match vCPU; test production-like concurrency |
| Production / Edge | 2–8 | Match vCPU | One worker per vCPU; cooperative scheduling within |

Rule: one worker per vCPU. Each worker runs one Pulse at a time (cooperative, not preemptive). The KernelScheduler's 50ms quantum ensures fairness within a worker.

#### 4.3.2 Worker Restart Policy (Memory Leak Defense)

Long-lived processes accumulate memory. FrankenPHP supports `--max-requests N` to restart a worker after N requests:

```bash
# Dev (laptop): no restart — faster iteration.
FRANKENPHP_MAX_REQUESTS=0

# Staging: restart after 1000 requests (catches leaks early).
FRANKENPHP_MAX_REQUESTS=1000

# Production: restart after 10000 requests.
FRANKENPHP_MAX_REQUESTS=10000
```

Combined with the CORE-02 `pulse()` scope using `WeakMap` (auto-evicts on Fiber GC), this provides defense-in-depth against memory leaks.

#### 4.3.3 Fiber Runtime Boot Integration

When DGLab's Kernel boots inside a FrankenPHP worker:

1. Detect FrankenPHP worker context (`\Fiber::getCurrent()` or `APP_ENV`).
2. Initialize the `KernelScheduler` with the worker's event loop.
3. Each incoming HTTP request becomes one Fiber → one `PulseDescriptor`.
4. The `pulse()` scope in CORE-02's Container uses `WeakMap<Fiber, array>` — this works automatically because each request runs inside a Fiber in FrankenPHP worker mode.

---

## 5. Workstream W2: Installer Multi-Distro Support

### 5.1 Problem

`install.sh` is entirely Debian/Ubuntu-specific: it uses `apt-get`, `dpkg --print-architecture`, Docker's Ubuntu apt repo URL, and `systemd-resolved` manipulation. It cannot run on:

- **Fedora / RHEL** (dnf, different Docker repo, no systemd-resolved stub)
- **Arch Linux** (pacman, AUR, different DNS resolver)
- **Amazon Linux 2023** (dnf, already used on EC2 — cloud-init handles this separately)

### 5.2 Architecture: Distro Detection + Abstracted Package Functions

```bash
# At the top of install.sh, after argument parsing:

_anvil_detect_distro() {
    if [ -f /etc/os-release ]; then
        # shellcheck disable=SC1091
        . /etc/os-release
        ANVIL_DISTRO_ID="${ID}"
        ANVIL_DISTRO_VERSION="${VERSION_ID}"
        ANVIL_DISTRO_LIKE="${ID_LIKE:-}"
    else
        echo "ERROR: Cannot detect distro (/etc/os-release missing)"
        exit 1
    fi
}

# Abstracted package manager:
_anvil_pkg_install() {
    case "$ANVIL_DISTRO_ID" in
        ubuntu|debian|pop-os|linuxmint)
            sudo apt-get update -qq && sudo apt-get install -y "$@"
            ;;
        fedora|rhel|centos|rocky|alma)
            sudo dnf install -y "$@"
            ;;
        arch|manjaro|endeavouros)
            sudo pacman -Syu --noconfirm "$@"
            ;;
        amzn)
            sudo dnf install -y "$@"
            ;;
        *)
            echo "ERROR: Unsupported distro: $ANVIL_DISTRO_ID"
            exit 1
            ;;
    esac
}

_anvil_pkg_is_installed() {
    case "$ANVIL_DISTRO_ID" in
        ubuntu|debian|pop-os|linuxmint)
            dpkg -s "$1" &>/dev/null
            ;;
        fedora|rhel|centos|rocky|alma|amzn)
            rpm -q "$1" &>/dev/null
            ;;
        arch|manjaro|endeavouros)
            pacman -Q "$1" &>/dev/null
            ;;
    esac
}
```

### 5.3 Distro-Specific Install Functions

#### 5.3.1 Docker

```bash
_anvil_install_docker() {
    case "$ANVIL_DISTRO_ID" in
        ubuntu|debian|pop-os|linuxmint)
            # Existing logic: Docker's official apt repo
            _anvil_install_docker_apt
            ;;
        fedora|rhel|centos|rocky|alma)
            # Docker's official dnf repo
            sudo dnf install -y dnf-plugins-core
            sudo dnf config-manager --add-repo https://download.docker.com/linux/fedora/docker-ce.repo
            sudo dnf install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
            sudo systemctl enable --now docker
            sudo usermod -aG docker "$(whoami)"
            ;;
        arch|manjaro|endeavouros)
            # Arch has docker in official repos
            sudo pacman -Syu --noconfirm docker docker-compose-plugin
            sudo systemctl enable --now docker
            sudo usermod -aG docker "$(whoami)"
            ;;
        amzn)
            # Amazon Linux 2023: Docker is in their repos
            sudo dnf install -y docker
            sudo systemctl enable --now docker
            sudo usermod -aG docker ec2-user
            ;;
    esac
}
```

#### 5.3.2 DNS (dnsmasq)

DNS setup is the most distro-sensitive part. The core logic (write `address=/.test/127.0.0.1` to dnsmasq config, point `resolv.conf` at `127.0.0.1`) is the same, but the resolver conflict varies:

| Distro | Default resolver | Conflict resolution |
|---|---|---|
| Ubuntu 22.04+ | systemd-resolved (stub on :53) | Disable stub listener, restart resolved |
| Fedora | systemd-resolved (stub on :53) | Same as Ubuntu |
| Arch | systemd-resolved (if enabled) | Same; or use NetworkManager's dnsmasq plugin |
| Pop!_OS | systemd-resolved | Same as Ubuntu |

The existing `install_dns()` logic (disable systemd-resolved stub, write dnsmasq config, point resolv.conf) works on all of these because they all use systemd-resolved. The key is to detect whether systemd-resolved is the active resolver:

```bash
_anvil_install_dns() {
    _anvil_pkg_install dnsmasq

    # Write the wildcard DNS config.
    echo "address=/.test/127.0.0.1" | sudo tee "$ANVIL_DNSMASQ_CONF"
    sudo systemctl enable --now dnsmasq

    # Handle systemd-resolved stub listener conflict (port 53).
    if systemctl is-active --quiet systemd-resolved 2>/dev/null; then
        # Disable the stub listener so dnsmasq can bind :53.
        sudo mkdir -p "$(dirname "$ANVIL_RESOLVED_CONF")"
        echo -e "[Resolve]\nDNSStubListener=no" | sudo tee "$ANVIL_RESOLVED_CONF"
        sudo systemctl restart systemd-resolved
    fi

    # Point resolv.conf at localhost.
    # On systemd-resolved systems, the stub-resolv.conf is regenerated
    # after restart, so we link to the resolved version.
    if [ -f /run/systemd/resolve/resolv.conf ]; then
        sudo ln -sf /run/systemd/resolve/resolv.conf /etc/resolv.conf
    else
        echo -e "nameserver 127.0.0.1\nnameserver 8.8.8.8" | sudo tee /etc/resolv.conf
    fi
}
```

#### 5.3.3 Binary Downloads (mkcert, dart-sass, inotify-tools)

These are already distro-agnostic (download from GitHub releases, extract to `/usr/local/bin`). The only distro-sensitive part is `inotify-tools`:

```bash
# inotify-tools is in all major distro repos:
case "$ANVIL_DISTRO_ID" in
    ubuntu|debian|pop-os|linuxmint) _anvil_pkg_install inotify-tools ;;
    fedora|rhel|centos|rocky|alma|amzn) _anvil_pkg_install inotify-tools ;;
    arch|manjaro|endeavouros) _anvil_pkg_install inotify-tools ;;
esac
```

### 5.4 Non-Interactive Mode Enhancement

The existing `--yes` flag already supports unattended installation. For CI/CD and automated staging VM provisioning, add:

```bash
# --tier <laptop|staging|production> sets ANVIL_TIER
# --skip-dns skips dnsmasq setup (for staging/production where
#   real DNS is used instead of *.test wildcard)
# --skip-mkcert skips local CA install (for staging/production)
```

This way, staging VM bootstrap becomes:

```bash
sudo ./install.sh --yes --tier staging --skip-dns --skip-mkcert
```

---

## 6. Workstream W3: Bug Fixes

### W3-1: Absolute Host Path in Cert Volume Mount

**File:** `docker-compose.local.yml` line 36
**Current:** `./nginx/certs:/home/dgi/www/DGLab/anvil/docker/nginx/certs:ro`
**Problem:** Hardcoded to one developer's machine. Breaks on any other host.
**Fix:** Mount at a container-internal path and update the vhost template to render cert paths accordingly:

```yaml
# docker-compose.local.yml
volumes:
  - ./nginx/certs:/etc/tengine/certs:ro
```

The vhost template's `SSL_CERT` and `SSL_KEY` variables (set by `lib/vhost.sh`) must resolve to `/etc/tengine/certs/<project>/demo.pem` instead of the host-specific path. Update `lib/vhost.sh`'s `anvil_vhost_generate()`:

```bash
# Before (host-specific):
export SSL_CERT="${CERTS_DIR}/${project}/${project}.pem"
export SSL_KEY="${CERTS_DIR}/${project}/${project}-key.pem"

# After (container-internal):
export SSL_CERT="/etc/tengine/certs/${project}/${project}.pem"
export SSL_KEY="/etc/tengine/certs/${project}/${project}-key.pem"
```

### W3-2: Hardcoded SSH User in EC2 Tunnel

**File:** `lib/ec2.sh` line 693
**Current:** `"ec2-user@${ec2_host}"`
**Problem:** Doesn't use the configurable `$ANVIL_EC2_SSH_USER` from `anvil.conf`.
**Fix:**

```bash
# Before:
ssh -N -L 8080:127.0.0.1:8080 -L 9999:127.0.0.1:9999 -i "$key_path" "ec2-user@${ec2_host}"
# After:
ssh -N -L 8080:127.0.0.1:8080 -L 9999:127.0.0.1:9999 -i "$key_path" "${ANVIL_EC2_SSH_USER}@${ec2_host}"
```

### W3-3: Placeholder Repo URL

**File:** `anvil.conf` line 142, `provisioning/cloud-init.yaml`
**Current:** `https://github.com/example/anvil.git`
**Fix:** Replace with the real repository URL before any EC2 provisioning. This is a configuration change, not a code change — but it must be done before the first production deployment.

---

## 7. Workstream W4: Edge / Production-Native Tier

### 7.1 Why a Native (Non-Docker) Tier

Docker adds overhead: the container daemon, image layers, bridge networking, and volume management consume memory and CPU. On an edge node (e.g., a 2 vCPU / 4 GB RAM VM at a CDN PoP), this overhead is significant.

Tier 3 installs Tengine and FrankenPHP as native systemd services — no Docker, no compose. The database and Redis are external (managed RDS, ElastiCache, or separate hosts).

### 7.2 Tier 3 Architecture

```
┌──────────────────────────────────────────────┐
│  Tengine (native, systemd: tengine.service)   │
│  /etc/tengine/tengine.conf                    │
│  /etc/tengine/conf.d/*.conf                   │
│  DSO: prometheus exporter, geoip, lua         │
│  TLS: Let's Encrypt (/etc/letsencrypt/live/)  │
└──────────────────┬───────────────────────────┘
                   │ proxy_pass http://127.0.0.1:8080
                   ▼
┌──────────────────────────────────────────────┐
│  FrankenPHP (native, systemd: frankenphp.service) │
│  /opt/anvil/app/ (document root)              │
│  Workers: match vCPU                          │
│  /opt/anvil/etc/php.ini                       │
└──────────────────┬───────────────────────────┘
                   │
         ┌─────────┼─────────┐
         ▼         ▼         ▼
      RDS/MySQL  Redis    External APIs
```

### 7.3 Tier 3 Install Script: `anvil/install-edge.sh` (NEW)

This is a separate installer for Tier 3. It does not install Docker, dnsmasq, mkcert, or inotify-tools — those are Tier 1 only.

```bash
#!/usr/bin/env bash
# install-edge.sh — Tier 3 (production/edge) native installer.
# Usage: sudo ./install-edge.sh [--tier production]
set -euo pipefail

ANVIL_TIER="${1:---tier=production}"
# ... distro detection (reuse W2 functions) ...

# 1. Install Tengine from source (same Dockerfile logic, but directly on host).
_anvil_install_tengine_native() {
    _anvil_pkg_install build-base gcc libc-dev linux-headers make \
        openssl-dev pcre-dev zlib-dev gd-dev geoip-dev \
        libxml2-dev lua-dev git
    
    local build_dir; build_dir="$(mktemp -d)"
    cd "$build_dir"
    git clone --depth 1 --branch "${TENGINE_VERSION:-3.2.0}" \
        https://github.com/alibaba/tengine.git
    cd tengine
    # Same ./configure flags as the Dockerfile.
    ./configure --prefix=/etc/tengine \
        --sbin-path=/usr/sbin/tengine \
        --modules-path=/usr/lib/tengine/modules \
        --conf-path=/etc/tengine/tengine.conf \
        --with-http_ssl_module --with-http_v2_module \
        --with-http_upstream_check_module --with-dso \
        --with-http_lua_module \
        # ... (same flags as Dockerfile) ...
    make -j"$(nproc)" && sudo make install
    cd -
    rm -rf "$build_dir"
}

# 2. Install FrankenPHP binary.
_anvil_install_frankenphp() {
    # Download the static FrankenPHP binary from GitHub releases.
    local arch="$(uname -m)"
    case "$arch" in
        x86_64) arch="amd64" ;;
        aarch64) arch="arm64" ;;
    esac
    curl -sL "https://github.com/dunglas/frankenphp/releases/latest/download/"\
"frankenphp-linux-${arch}.tar.gz" \
        | sudo tar xz -C /usr/local/bin frankenphp
}

# 3. Install certbot for Let's Encrypt.
_anvil_install_certbot() {
    _anvil_pkg_install certbot python3-certbot-nginx 2>/dev/null || \
        sudo snap install --classic certbot 2>/dev/null || \
        pip3 install certbot certbot-nginx 2>/dev/null
}

# 4. Create systemd units.
_anvil_create_systemd_units() {
    # tengine.service
    sudo tee /etc/systemd/system/tengine.service <<'UNIT'
[Unit]
Description=Tengine HTTP Server
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
UNIT

    # frankenphp.service
    sudo tee /etc/systemd/system/frankenphp.service <<UNIT
[Unit]
Description=FrankenPHP Application Server
After=network.target

[Service]
Type=simple
WorkingDirectory=/opt/anvil/app
ExecStart=/usr/local/bin/frankenphp run \
    --workers=${FRANKENPHP_WORKERS:-2} \
    --listen=127.0.0.1:8080
Restart=on-failure
RestartSec=5
LimitNOFILE=65536
Environment=APP_ENV=production
Environment=FRANKENPHP_MAX_REQUESTS=${FRANKENPHP_MAX_REQUESTS:-10000}

[Install]
WantedBy=multi-user.target
UNIT

    sudo systemctl daemon-reload
}
```

### 7.4 Tier 3 Tengine Config

`/etc/tengine/tengine.conf` — the main config includes the conf.d directory and sets upstream health checks:

```nginx
worker_processes auto;
error_log /var/log/tengine/error.log warn;
pid /var/run/tengine.pid;

events {
    worker_connections 1024;
}

http {
    include       mime.types;
    default_type  application/octet-stream;
    sendfile      on;
    keepalive_timeout 65;

    # Upstream: FrankenPHP workers with built-in health checks.
    upstream frankenphp {
        server 127.0.0.1:8080;
        check interval=3000 rise=2 fall=3 timeout=1000;
    }

    # Include per-domain vhost configs.
    include /etc/tengine/conf.d/*.conf;
}
```

The per-domain vhost configs go in `/etc/tengine/conf.d/` and use the same `proxy_pass http://frankenphp` pattern as the Docker tier, but with `upstream://frankenphp` instead of a direct container hostname:

```nginx
server {
    listen 443 ssl http2;
    server_name app.example.com;

    ssl_certificate     /etc/letsencrypt/live/app.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/app.example.com/privkey.pem;

    root /opt/anvil/app/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        proxy_pass http://frankenphp;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_request_buffering off;
        proxy_buffering off;
        proxy_connect_timeout 5s;
        proxy_read_timeout 300s;
        proxy_send_timeout 300s;
    }
}
```

### 7.5 Tier 3 Deployment Flow

```bash
# 1. Run the edge installer.
sudo ./install-edge.sh

# 2. Deploy application code to /opt/anvil/app/.
#    (git clone, rsync, or your CI/CD pipeline)

# 3. Issue TLS certificate.
sudo certbot --nginx -d app.example.com -d www.example.com

# 4. Enable and start services.
sudo systemctl enable --now tengine frankenphp

# 5. Verify.
curl -sI https://app.example.com
systemctl status tengine frankenphp
```

### 7.6 Tier 3 DSO Modules for Production

Tengine's DSO support enables loading modules without recompiling. For production/edge:

```nginx
# In /etc/tengine/tengine.conf:

# Prometheus metrics exporter (loaded at runtime, no rebuild).
dso {
    load /usr/lib/tengine/modules/ngx_http_prometheus_module.so;
}

http {
    # Expose metrics on a loopback-only endpoint.
    server {
        listen 127.0.0.1:9113;
        location /metrics {
            prometheus;
        }
    }
}
```

This aligns with the OS metaphor: DSO modules are loadable kernel modules. Prometheus scrapes `127.0.0.1:9113/metrics` and never needs to touch the public interface.

### 7.7 Tier 3 Auto-Update Strategy

For edge nodes that are hard to reach:

1. **Tengine:** Binary update requires recompile. Use the same Dockerfile logic in a build pipeline, produce a `.tar.gz`, and distribute via your artifact store. The systemd unit's `Restart=on-failure` provides basic self-healing.
2. **FrankenPHP:** Replace the binary at `/usr/local/bin/frankenphp` and `systemctl restart frankenphp`. The worker restart is instant (FrankenPHP respawns workers on SIGUSR2 for zero-downtime binary upgrades).
3. **Application code:** `git pull` + `systemctl reload tengine` (to pick up any config changes). FrankenPHP workers pick up new PHP files on the next request (with `opcache.validate_timestamps=1` for staged rollouts, or `0` + container restart for immutable deployments).

---

## 8. Staging VM Tier Details

### 8.1 Purpose

Staging VMs validate the production configuration before it reaches production. They run the same Docker Compose stack as production (Tier 2) but with:

- A real domain (not `*.test`) pointed at the VM via DNS.
- Let's Encrypt TLS (same as production).
- RDS MySQL (same as production, but a separate instance).
- The same `docker-compose.ec2.yml` (minus the EC2-specific IAM/SSM — those are for AWS deployment, not the staging VM itself).

### 8.2 Staging Provisioning Flow

```bash
# 1. Provision a staging VM (any cloud provider, or local KVM/VMware).
#    Minimum: 2 vCPU, 4 GB RAM, 20 GB disk.

# 2. SSH in and run the installer (skip DNS and mkcert — not needed).
sudo ./install.sh --yes --tier staging --skip-dns --skip-mkcert

# 3. Clone the repo and start the stack.
git clone <repo-url> /opt/anvil
cd /opt/anvil/anvil
COMPOSE_FILE=docker/docker-compose.ec2.yml anvilctl start

# 4. Point a real domain at the VM's public IP via DNS.

# 5. Issue Let's Encrypt certificate.
sudo ./provisioning/certbot-setup.sh \
    --domain staging.example.com \
    --email ops@example.com

# 6. Verify.
curl -sI https://staging.example.com
```

### 8.3 Staging vs Production Differences

| Dimension | Staging | Production |
|---|---|---|
| Workers | 2 | Match vCPU |
| FRANKENPHP_MAX_REQUESTS | 1000 (catch leaks) | 10000 |
| OpCache validate_timestamps | 1 (dev convenience) | 0 (immutable deploy) |
| Database | RDS db.t3.micro | RDS db.t3.micro or larger |
| Backup retention | 7 days | 30 days |
| Monitoring | Basic logs | Prometheus + CloudWatch |

---

## 9. Migration Path

### Phase 1: W1 + W3 (Serving Layer + Bug Fixes) — Local Dev

1. Create `anvil/docker/tengine/Dockerfile`.
2. Update `docker-compose.local.yml` (replace nginx + php with tengine + frankenphp).
3. Update `vhost.conf.tpl` (fastcgi_pass → proxy_pass, cert paths).
4. Update `lib/vhost.sh` and `lib/docker.sh` container name references.
5. Apply W3 bug fixes (cert path, SSH user, deprecated aliases in anvil.conf).
6. Add Tengine/FrankenPHP/tier variables to `anvil.conf`.
7. Rename `docker/php/Dockerfile` → `docker/php/Dockerfile.fpm-legacy`.
8. Update `entrypoint-ssm.sh` to use `exec "$@"` instead of `exec php-fpm`.
9. **Test:** `anvilctl start` → verify `https://demo.test` serves PHP via FrankenPHP.
10. **Test:** `anvilctl new testproject` → verify vhost, SSL, proxy.
11. **Test:** `anvilctl watch` → verify inotify-driven vhost creation.
12. **Test:** Rollback — `git checkout` the old compose + template, verify PHP-FPM stack restores.

### Phase 2: W1 (Serving Layer) — EC2 / Staging

1. Update `docker-compose.ec2.yml` (same structural changes as Phase 1).
2. Update `provisioning/certbot-setup.sh` (container names, proxy_pass).
3. Update `provisioning/cloud-init.yaml` (no change needed — it runs the compose file).
4. **Test:** Full EC2 provision → RDS → certbot → verify HTTPS.
5. **Test:** Staging VM provision → verify same stack works without EC2-specific IAM.

### Phase 3: W2 (Multi-Distro Installer)

1. Add distro detection to `install.sh`.
2. Abstract package manager functions.
3. Add `--tier`, `--skip-dns`, `--skip-mkcert` flags.
4. **Test:** `sudo ./install.sh --yes --tier staging` on Fedora 42.
5. **Test:** `sudo ./install.sh --yes --tier laptop` on Arch Linux.
6. **Test:** `sudo ./install.sh --yes` on Ubuntu 24.04 (regression).

### Phase 4: W4 (Edge / Native Tier)

1. Create `install-edge.sh` (Tengine from source + FrankenPHP binary + systemd).
2. Create Tengine main config template for Tier 3.
3. Create per-domain vhost template for Tier 3.
4. Add DSO module loading for Prometheus exporter.
5. **Test:** `install-edge.sh` on a fresh Ubuntu 24.04 minimal VM.
6. **Test:** Deploy a real domain, issue certbot cert, verify serving.
7. **Test:** Verify DSO module load/unload without restart.
8. **Test:** Verify FrankenPHP worker restart on `systemctl restart frankenphp`.

### Phase 5: Tengine Dynamic Config (Future)

- Replace `tengine -s reload` with Tengine's dynamic server API for vhost-only changes.
- Enable upstream health checks to auto-detect dead FrankenPHP workers.
- DSO-load observability modules in production without image rebuilds.

### Phase 6: FrankenPHP-Specific Features (Future)

- **Mercure:** Built-in real-time hub (HUB-28, HUB-31).
- **Early Hints:** Faster perceived page loads.
- **Worker preloading:** `frankenphp run --preload` for DGLab Kernel pre-boot.

---

## 10. Rollback Strategy

### Tier 1 (Laptop)

```bash
git checkout HEAD -- anvil/docker/docker-compose.local.yml anvil/docker/nginx/templates/vhost.conf.tpl
anvilctl stop && anvilctl start
```

Single git revert on the compose file + template. No data migration. The `www/` volume is identical between stacks.

### Tier 2 (Staging VM)

Same as Tier 1 but the compose file is `docker-compose.ec2.yml`. If the VM is stateless (code deployed from git), a `git revert` + `anvilctl stop && anvilctl start` restores the old stack.

### Tier 3 (Production/Edge)

Systemd services can be rolled back by:

```bash
# Stop FrankenPHP, restore old binary, restart.
sudo systemctl stop frankenphp
sudo cp /usr/local/bin/frankenphp.bak /usr/local/bin/frankenphp
sudo systemctl start frankenphp

# Same for Tengine (if the binary was replaced).
sudo systemctl stop tengine
sudo cp /usr/local/sbin/tengine.bak /usr/local/sbin/tengine
sudo systemctl start tengine
```

Always keep `.bak` copies of the binaries before upgrading. Add this to the Tier 3 deployment runbook.

---

## 11. Risk Register

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| **Tengine CVE lag** (patches slower than nginx) | Medium | High | Pin `TENGINE_VERSION`; subscribe to Tengine GitHub advisories; maintain nginx fallback compose file |
| **FrankenPHP worker memory leak** (long-lived process) | Low | High | `pulse()` uses WeakMap (auto-evicts); `FRANKENPHP_MAX_REQUESTS` restarts workers; monitor with DSO Prometheus module |
| **proxy_pass overhead vs fastcgi_pass** | Low | Low | ~0.1ms per request; negligible vs PHP execution. Tengine's unbuffered proxy minimizes further |
| **DSO module incompatibility** | Low | Medium | Pin module versions; test in staging before production |
| **FrankenPHP container size** | Low | Low | Official image ~150MB (vs ~80MB for php:8.3-fpm). Acceptable for 2-container architecture |
| **Tier 3 native install complexity** | Medium | Medium | `install-edge.sh` automates it; tested on Ubuntu 24.04 minimal first |
| **Multi-distro installer regressions** | Medium | Medium | Test matrix: Ubuntu 24.04, Fedora 42, Arch. Keep existing Ubuntu path as default |
| **Cert path breakage during migration** | Low | High | W3-1 fix makes paths container-internal; verify with `anvilctl ssl demo` after migration |

---

## 12. Validation Checklist

### Tier 1 (Laptop)

- [ ] `docker compose build tengine` succeeds
- [ ] `docker compose up -d` starts tengine + frankenphp + mysql + redis + phpmyadmin
- [ ] `https://demo.test` serves a PHP page (browser / curl)
- [ ] `anvilctl new testproj` generates vhost, issues cert, proxy works
- [ ] `anvilctl ssl testproj` issues mkcert cert, TLS trusted
- [ ] `anvilctl vhost_remove testproj` cleans up
- [ ] `anvilctl watch` auto-creates vhost on new `www/` directory
- [ ] `phpinfo()` shows `frankenphp` in SERVER_SOFTWARE
- [ ] OpCache enabled and warm (`opcache_get_status()`)
- [ ] Rollback: `git checkout` restores PHP-FPM stack

### Tier 2 (Staging VM)

- [ ] `install.sh --yes --tier staging --skip-dns --skip-mkcert` succeeds on target distro
- [ ] `anvilctl start` with `COMPOSE_FILE=docker-compose.ec2.yml` brings up stack
- [ ] `certbot-setup.sh` issues Let's Encrypt cert
- [ ] HTTPS serves PHP via FrankenPHP
- [ ] RDS connection works (if RDS is configured)

### Tier 3 (Production/Edge)

- [ ] `install-edge.sh` completes on fresh Ubuntu 24.04 minimal
- [ ] `systemctl status tengine` → active
- [ ] `systemctl status frankenphp` → active
- [ ] Real domain serves HTTPS with Let's Encrypt cert
- [ ] DSO Prometheus module loads and exposes `/metrics` on loopback
- [ ] `systemctl restart frankenphp` → zero-downtime (workers respawn)
- [ ] Worker restart respects `FRANKENPHP_MAX_REQUESTS`

---

## 13. Relationship to Architecture Documents

| Document | Relationship |
|---|---|
| `DGLAB-AS-OS-RUNTIME.md` | Specifies the Fiber-based cooperative scheduler that mandates FrankenPHP (OD-07). This doc implements the deployment consequence. |
| `OPEN-DECISIONS.md` OD-07 | Records FrankenPHP as accepted runtime. This doc is the implementation of that decision. |
| `OPEN-DECISIONS.md` OD-08 | Async I/O library choice (ReactPHP/Amp) — library-agnostic in this doc; the proxy layer doesn't care which event loop FrankenPHP uses internally. |
| `CORE-02.md` | The `pulse()` scope and `WeakMap` assume a Fiber-capable runtime. FrankenPHP provides this. |
| `DEPLOY-01.md` | Specifies OCI base image (PHP-FPM + Nginx + Supervisor). This doc supersedes the PHP-FPM and Nginx components. DEPLOY-01 must be updated to reference Tengine + FrankenPHP. |
| `STRUCTURE-06-Boot.md` | Boot sequence maps to Linux boot. FrankenPHP worker startup = "kernel init" phase. |
| `anvil/README.md` | Must be updated to reference Tengine + FrankenPHP, three-tier model, and multi-distro support after landing. |
| `RUNBOOK-ANVIL-DNS.md` | Remains valid — the DNS conflict resolution applies to all tiers that use dnsmasq (Tier 1 only). |

---

## 14. Files Changed Per Workstream

### W1 (Serving Layer)

| File | Action | What Changes |
|---|---|---|
| `config/anvil.conf` | Modify | Add TENGINE_VERSION, FRANKENPHP_*, ANVIL_TIER vars; add PROXY_* path aliases |
| `docker/docker-compose.local.yml` | Modify | Replace nginx+php services with tengine+frankenphp; fix cert mount (W3-1) |
| `docker/docker-compose.ec2.yml` | Modify | Same structural replacement; FrankenPHP SSM entrypoint |
| `docker/tengine/Dockerfile` | **Create** | Multi-stage Tengine build from source |
| `docker/nginx/templates/vhost.conf.tpl` | Modify | `fastcgi_pass php:9000` → `proxy_pass http://frankenphp:80` with headers/timeouts |
| `lib/vhost.sh` | Modify | `nginx` → `tengine` in docker exec/ps commands; cert paths to container-internal |
| `lib/docker.sh` | Modify | Comment update only (nginx → tengine example) |
| `lib/ec2.sh` | Modify | Comment updates ("php container" → "FrankenPHP worker"); W3-2 SSH user fix |
| `provisioning/certbot-setup.sh` | Modify | Container name `anvil_nginx` → `anvil_tengine`; inline template to proxy_pass |
| `docker/php/entrypoint-ssm.sh` | Modify | `exec php-fpm` → `exec "$@"` (runtime-agnostic) |
| `docker/php/Dockerfile` | Rename | → `Dockerfile.fpm-legacy` (archived, no longer used) |
| `docker/php/php.ini` | Modify | Add JIT config; add comment about validate_timestamps per tier |

### W2 (Multi-Distro Installer)

| File | Action | What Changes |
|---|---|---|
| `install.sh` | Modify | Add distro detection; abstract package manager; add --tier/--skip-dns/--skip-mkcert flags |

### W3 (Bug Fixes)

| File | Action | What Changes |
|---|---|---|
| `docker/docker-compose.local.yml` | Modify | Fix cert volume mount (W3-1) — overlaps with W1 |
| `lib/vhost.sh` | Modify | Fix cert path rendering (W3-1) — overlaps with W1 |
| `lib/ec2.sh` | Modify | Fix hardcoded SSH user (W3-2) — overlaps with W1 |
| `config/anvil.conf` | Modify | Fix placeholder repo URL (W3-3) — user must set real URL |

### W4 (Edge Tier)

| File | Action | What Changes |
|---|---|---|
| `install-edge.sh` | **Create** | Native Tier 3 installer (Tengine from source, FrankenPHP binary, systemd units) |
| `config/tengine.conf.tpl` | **Create** | Tengine main config template for Tier 3 |
| `config/vhost-edge.conf.tpl` | **Create** | Per-domain vhost template for Tier 3 (proxy_pass to upstream) |
| `provisioning/tengine.service` | **Create** | systemd unit file for Tengine |
| `provisioning/frankenphp.service` | **Create** | systemd unit file for FrankenPHP |

---

**Provenance:** Written 2026-08-26. Supersedes v1 (2026-08-24) which covered only the serving-layer swap for local + EC2. Triggered by architecture lead's request for a reimplementation instruction covering laptops, staging VMs, and production servers / edge nodes. Incorporates full codebase audit findings (15 nginx references, 8 php references, 3 bugs) and the three-tier deployment model.
