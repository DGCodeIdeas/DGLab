# Anvil Reimplementation Instruction: Tengine + FrankenPHP

**Status:** Proposed (architecture lead review required)
**Date:** 2026-08-24
**Author:** DGCI (architecture lead), analysis by Z.ai
**Supersedes:** Current nginx:1.27-alpine + php:8.3-fpm stack
**Gate decisions:** OD-07 (Fiber-based cooperative runtime → FrankenPHP mandatory)

---

## 1. Purpose

This document specifies how to reimplement Anvil's web serving layer to combine **Tengine 3.x** (reverse proxy / TLS termination) with **FrankenPHP** (PHP application server in worker mode). This replaces the current `nginx:1.27-alpine` + `php:8.3-fpm` combination.

The current Anvil stack uses nginx as a FastCGI reverse proxy to PHP-FPM. OD-07 established that DGLab requires a Fiber-based cooperative scheduler, which mandates a long-lived PHP worker process — FrankenPHP in worker mode. PHP-FPM (one process per request, terminated after response) cannot support this model. The reverse proxy layer remains necessary for TLS termination, vhost multiplexing, and static file serving, but the PHP execution layer must change.

---

## 2. Why Tengine Instead of Plain nginx

Tengine is a fork of nginx by Alibaba/Taobao. The question is whether it adds enough value over plain nginx to justify the divergence from the mainstream image.

### 2.1 Tengine Advantages Over nginx for DGLab

| Feature | nginx 1.27 | Tengine 3.x | Relevance to DGLab |
|---|---|---|---|
| nginx base version | 1.27.x | **1.31.3** | Newer upstream, later security patches |
| **DSO (Dynamic Shared Objects)** | No | **Yes** | Load/unload modules without recompiling — useful for hot-loading observability or security modules in production without rebuilds |
| **Dynamic server/location/upstream** | No | **Yes** | Add/remove vhosts without reloading nginx — aligns with Anvil's inotify-driven vhost watcher (`scripts/vhost-watcher.sh`); currently requires `nginx -s reload` on every vhost change |
| nginx config compatibility | N/A | **100%** | Drop-in replacement — existing `vhost.conf.tpl` works unchanged |
| Lua scripting | Via OpenResty only | Built-in (ngx_lua) | Enables complex routing logic, auth checks, and rate limiting directly in the proxy layer without extra containers |
| Transparent upstream health checks | Via 3rd-party module | **Built-in** (ngx_upstream_check) | Tengine can health-check FrankenPHP workers and remove unhealthy ones from the upstream pool automatically |
| Unbuffered upload proxying | Limited | **Native** (`proxy_request_buffering off` + enhanced) | Large file uploads (Bridge tier) can be streamed directly to FrankenPHP without buffering at the proxy layer |

### 2.2 The Honest Risk

**Tengine's maintenance cadence is slower than nginx.** Tengine 3.1.0 was based on nginx 1.24.0 and carried unpatched CVEs (CVE-2026-42945, per Orca Security) until Tengine 3.2.0 (July 2026). Plain nginx gets patched faster because it has a larger security response team.

**Mitigation:** Anvil must pin Tengine to the latest stable release (3.2.0+) and subscribe to the Tengine GitHub security advisories. The `anvil.conf` TENGINE_VERSION variable makes upgrading a config change, not a code change.

**Verdict:** Tengine's DSO, dynamic config, and built-in health checks are architecturally valuable for DGLab's OS-like runtime (hot-reloadable modules map to the OS metaphor of loadable kernel modules). The risk is manageable with a pinned-version policy. **Proceed with Tengine.**

---

## 3. Why FrankenPHP Instead of PHP-FPM

This is no longer a choice — it is a stated consequence of OD-07.

| | PHP-FPM (current) | FrankenPHP Worker Mode |
|---|---|---|
| Process model | One process per request, terminated after response | Long-lived worker process, persists across requests |
| Fiber support | N/A (process dies) | **Full support** — Fibers live across the worker's lifetime |
| `pulse()` scope (CORE-02) | N/A (each request is its own process) | **Required** — WeakMap-keyed per-Fiber instance cache |
| OpCache | Shared via SHM, but cold on every new process | **Hot** — the worker preloads code once, OpCache is always warm |
| Performance | Process spawn + teardown overhead per request | No per-request spawn overhead; request handling is a function call |
| Protocol | FastCGI | **HTTP** (proxy_pass, not fastcgi_pass) |
| TLS | Handled by nginx | Can be handled internally (Caddy) or externally (Tengine) |

FrankenPHP in worker mode listens on HTTP. This means Anvil's reverse proxy switches from `fastcgi_pass php:9000` to `proxy_pass http://frankenphp:80`.

---

## 4. Target Architecture

```
                         ┌──────────────────────────────┐
   Internet  ──────────────▶│  Tengine 3.x (TLS + Proxy)   │
      :443                   │  - TLS termination           │
      :80                    │  - HTTP/2, HTTP/3 (future)    │
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
                             │  - HTTP server on :80         │
                             └──────────┬───────────────────┘
                                        │
                          ┌─────────────┼────────────────┐
                          ▼             ▼                ▼
                     ┌────────┐  ┌────────┐      ┌───────────┐
                     │ MySQL  │  │ Redis  │      │ RDS (EC2) │
                     │ (local)│  │        │      │           │
                     └────────┘  └────────┘      └───────────┘
```

### Key Changes from Current Architecture

1. **nginx → Tengine**: Drop-in replacement. Same vhost config syntax. Adds DSO and dynamic config.
2. **PHP-FPM → FrankenPHP**: Protocol changes from FastCGI to HTTP. Container image changes from `php:8.3-fpm` to `dunglas/frankenphp`.
3. **`fastcgi_pass` → `proxy_pass`**: The vhost template's PHP location block changes.
4. **No more php-fpm pool config**: FrankenPHP manages its own worker pool via env vars.
5. **Static files stay on Tengine**: Tengine continues serving static assets directly (no change).

---

## 5. File-by-File Change Specification

### 5.1 `anvil/config/anvil.conf`

Add Tengine version pin and FrankenPHP configuration variables.

```bash
# Tengine version pin — change this to upgrade.
# Check https://github.com/alibaba/tengine/releases for latest.
: "${TENGINE_VERSION:=3.2.0}"

# FrankenPHP version pin.
: "${FRANKENPHP_VERSION:=latest}"

# FrankenPHP worker configuration.
# Number of PHP worker processes (defaults to num CPU cores).
: "${FRANKENPHP_WORKERS:=2}"

# FrankenPHP internal HTTP port (inside the container).
: "${FRANKENPHP_LISTEN_PORT:=80}"
```

### 5.2 `anvil/docker/docker-compose.local.yml`

Replace the `nginx` and `php` services.

```yaml
services:
  # ---------------------------------------------------------------------------
  # tengine — TLS termination + HTTP reverse proxy to FrankenPHP
  # ---------------------------------------------------------------------------
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
      - ./nginx/certs:/home/dgi/www/DGLab/anvil/docker/nginx/certs:ro
      - ./nginx/templates:/etc/tengine/templates:ro
      - ./www:/var/www:rw
    depends_on:
      - frankenphp
    networks:
      - anvil_net
    restart: unless-stopped

  # ---------------------------------------------------------------------------
  # frankenphp — PHP application server in worker mode
  # ---------------------------------------------------------------------------
  frankenphp:
    image: dunglas/frankenphp:${FRANKENPHP_VERSION:-latest}
    container_name: anvil_frankenphp
    # Worker mode: FrankenPHP runs PHP workers that persist across requests.
    # This is the mode required by OD-07 (Fiber-based cooperative scheduler).
    command: frankenphp run --workers=${FRANKENPHP_WORKERS:-2} --listen=${FRANKENPHP_LISTEN_PORT:-80}
    volumes:
      - ./www:/app/public
      - ./php/php.ini:/usr/local/etc/php/conf.d/anvil.ini:ro
    environment:
      APP_ENV: development
    networks:
      - anvil_net
    restart: unless-stopped

  # mysql, phpmyadmin, redis remain UNCHANGED from current docker-compose.local.yml
```

**Critical differences from the current compose:**
- The `php` service (PHP-FPM) is replaced by `frankenphp` (HTTP server).
- FrankenPHP mounts `./www` at `/app/public` (its document root).
- FrankenPHP exposes HTTP on port 80 (inside the container), not FastCGI on 9000.
- The `tengine` service depends on `frankenphp`, not `php`.
- A custom `php.ini` can still be mounted for opcache and extension settings.

### 5.3 `anvil/docker/docker-compose.ec2.yml`

Same structural changes as the local compose, plus:

```yaml
  frankenphp:
    image: dunglas/frankenphp:${FRANKENPHP_VERSION:-latest}
    container_name: anvil_frankenphp
    command: frankenphp run --workers=${FRANKENPHP_WORKERS:-2} --listen=80
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
    volumes:
      - ./www:/app/public
      - ./php/php.ini:/usr/local/etc/php/conf.d/anvil.ini:ro
    depends_on:
      - redis
    networks:
      - anvil_net
    restart: unless-stopped
```

The SSM entrypoint pattern (`entrypoint-ssm.sh`) is reused. FrankenPHP respects `ENTRYPOINT` + `CMD` separation the same way Docker does — the entrypoint resolves RDS credentials, then `exec`s the FrankenPHP command.

### 5.4 `anvil/docker/tengine/Dockerfile` (NEW)

```dockerfile
# Anvil — Tengine 3.x image.
#
# Builds Tengine from source with commonly-needed modules.
# DSO support is enabled so modules can be loaded/unloaded
# without recompiling the image.

ARG TENGINE_VERSION=3.2.0

FROM alpine:3.20 AS builder

RUN apk add --no-cache \
    build-base \
    gcc \
    libc-dev \
    linux-headers \
    make \
    openssl-dev \
    pcre-dev \
    zlib-dev \
    curl \
    gd-dev \
    geoip-dev \
    libxml2-dev \
    lua-dev \
    git

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
    --without-http_rewrite_module \
    && make -j"$(nproc)" \
    && make install

FROM alpine:3.20

RUN apk add --no-cache \
    openssl \
    pcre \
    zlib \
    gd \
    geoip \
    libxml2 \
    lua \
    curl \
    libstdc++ \
    ca-certificates

COPY --from=builder /etc/tengine /etc/tengine
COPY --from=builder /usr/sbin/tengine /usr/sbin/tengine
COPY --from=builder /usr/lib/tengine/modules /usr/lib/tengine/modules

RUN mkdir -p /etc/tengine/conf.d \
    /var/log/tengine \
    /var/www \
    /var/run

EXPOSE 80 443

# Tengine is a drop-in nginx replacement — the binary name differs
# but the signal handling and CLI flags are identical.
CMD ["tengine", "-g", "daemon off;"]
```

**Build notes:**
- `--with-dso` enables dynamic module loading (Tengine's key differentiator).
- `--with-http_upstream_check_module` enables built-in upstream health checks — Tengine will automatically remove a dead FrankenPHP worker from the rotation.
- `--with-http_lua_module` enables embedded Lua for complex routing.
- The image is ~40MB (Alpine + compiled Tengine), comparable to `nginx:1.27-alpine` (~30MB).

### 5.5 `anvil/docker/nginx/templates/vhost.conf.tpl`

The template requires **one change**: the PHP location block switches from FastCGI to HTTP proxy.

**Current (FastCGI to PHP-FPM):**

```nginx
# PHP-FPM via FastCGI (service name "php" on port 9000).
location ~ \.php$ {
    fastcgi_pass php:9000;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

**New (HTTP proxy to FrankenPHP):**

```nginx
# FrankenPHP worker mode (HTTP reverse proxy).
location ~ \.php$ {
    proxy_pass http://frankenphp:80;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;

    # Stream large uploads directly to FrankenPHP without buffering.
    proxy_request_buffering off;
    proxy_buffering off;

    # Timeouts tuned for long-running Pulse execution.
    # The cooperative scheduler's 50ms quantum means a Pulse that
    # doesn't yield will block its worker for up to 50ms. The proxy
    # timeout must exceed the worst-case Pulse execution time.
    proxy_connect_timeout 5s;
    proxy_read_timeout 300s;
    proxy_send_timeout 300s;
}
```

**Why `proxy_request_buffering off`:** In the Fiber runtime, a Pulse may accept a large request body (file upload, large JSON payload) and process it incrementally using async I/O. Buffering the entire body at the Tengine layer defeats the purpose — the body should stream directly to FrankenPHP so the Fiber can begin processing before the upload completes.

**Why 300s timeouts:** The OS metaphor means some Pulses (batch processing, report generation) may legitimately run for tens of seconds. The old PHP-FPM model had `request_terminate_timeout` in php-fpm.conf; here, the proxy timeout is the equivalent. 300s (5 minutes) is generous; tune down in production via `anvil.conf`.

### 5.6 `anvil/lib/vhost.sh`

**No structural changes.** The vhost generation logic (envsubst on the template, nginx reload) works identically with Tengine. Two tweaks:

1. The reload command changes from `nginx -s reload` to `tengine -s reload`.
2. The Docker exec target changes from the `nginx` container to the `tengine` container.

```bash
# In anvil_vhost_generate() and anvil_vhost_reload():
# Before:
docker compose -f "$ANVIL_COMPOSE_FILE" exec -T nginx nginx -s reload
# After:
docker compose -f "$ANVIL_COMPOSE_FILE" exec -T tengine tengine -s reload
```

3. With Tengine's dynamic server/location support (future optimization), the reload can be eliminated entirely for vhost-only changes. This is a **Phase 2** optimization — for now, the reload approach is preserved for safety.

### 5.7 `anvil/docker/php/Dockerfile`

**Retained but repurposed.** This image is no longer the PHP execution runtime. It becomes an **extension and config provider**:

- Renamed to `anvil/docker/php/Dockerfile.extensions` (optional, for custom PHP extensions not in the FrankenPHP image).
- The base `dunglas/frankenphp` image already includes: `pdo_mysql`, `mysqli`, `gd`, `intl`, `zip`, `opcache`, `redis`, `openssl`.
- If DGLab needs additional extensions (e.g., `protobuf`, `grpc`), build a custom FrankenPHP image that extends the official one.

**For the initial reimplementation, the official `dunglas/frankenphp` image is sufficient. No custom PHP build is needed.** The existing `php/Dockerfile` and `php/entrypoint-ssm.sh` can be archived or adapted.

### 5.8 `anvil/provisioning/certbot-setup.sh`

**Minimal changes.** This script renders a production vhost and obtains Let's Encrypt certs. Changes:

1. The vhost template it renders must use the new `proxy_pass` block (same template change as 5.5).
2. The reload command targets `tengine` instead of `nginx`.
3. The cert paths remain identical (Let's Encrypt writes to `/etc/letsencrypt/live/`).

### 5.9 `anvil/lib/docker.sh`

The Docker engine functions that reference the `nginx` and `php` container names need updating:

- Any hardcoded `nginx` container reference → `tengine`
- Any hardcoded `php` container reference → `frankenphp`
- The `docker compose ps -q nginx` health check → `docker compose ps -q tengine`

### 5.10 `anvil/install.sh`

**No changes.** The installer handles host-level dependencies (Docker, dnsmasq, mkcert, sass, inotify-tools). None of these change with Tengine + FrankenPHP.

### 5.11 `anvil/bin/anvilctl`

**No changes.** `anvilctl` is a pure dispatcher — it sources `lib/*.sh` and maps subcommands to functions. The function names and signatures don't change.

---

## 6. FrankenPHP Worker Mode Configuration

### 6.1 Worker Count

```bash
# In anvil.conf:
: "${FRANKENPHP_WORKERS:=2}"
```

For local development, 2 workers are sufficient. For EC2 production:
- `t3.micro` (2 vCPU): `FRANKENPHP_WORKERS=2`
- `t3.small` (2 vCPU): `FRANKENPHP_WORKERS=2`
- `t3.medium` (4 vCPU): `FRANKENPHP_WORKERS=4`

Rule of thumb: one worker per vCPU. Each worker runs one Pulse at a time (cooperative scheduling within the worker, not preemptive). The KernelScheduler's 50ms quantum ensures fairness within a worker.

### 6.2 OpCache Configuration

FrankenPHP in worker mode preloads the application code once when the worker starts. OpCache is always warm. Mount a custom `php.ini` for tuning:

```ini
; anvil/docker/php/php.ini
[opcache]
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1   ; dev: check for file changes
; opcache.validate_timestamps=0 ; prod: disable for max performance
opcache.revalidate_freq=0
opcache.fast_shutdown=1
opcache.jit=tracing               ; PHP 8.3+ JIT (worker mode benefits significantly)
opcache.jit_buffer_size=64M
```

In production, set `opcache.validate_timestamps=0` and redeploy the FrankenPHP container on code changes (immutable deployment model per DEPLOY-04).

### 6.3 Fiber Runtime Boot Integration

When DGLab's Kernel boots inside a FrankenPHP worker, it must:

1. Detect that it's running inside a FrankenPHP worker (check `iber::getCurrent()` context or env var `APP_ENV`).
2. Initialize the `KernelScheduler` with the worker's event loop.
3. Accept incoming HTTP requests as Pulses (each request → one Fiber → one `PulseDescriptor`).
4. The `pulse()` scope in CORE-02's Container uses `iber::getCurrent()` to key the `WeakMap` — this works automatically in FrankenPHP worker mode because each request runs inside a Fiber.

---

## 7. Migration Path

### Phase 1: Local Development (This Reimplementation)

1. Create `anvil/docker/tengine/Dockerfile`.
2. Update `docker-compose.local.yml` (replace nginx+php with tengine+frankenphp).
3. Update `vhost.conf.tpl` (fastcgi_pass → proxy_pass).
4. Update `lib/vhost.sh` and `lib/docker.sh` container name references.
5. Add Tengine/FrankenPHP variables to `anvil.conf`.
6. Test: `anvilctl start` → verify `https://demo.test` serves PHP via FrankenPHP.
7. Test: `anvilctl new testproject` → verify vhost generation, SSL, and proxy.
8. Test: `anvilctl watch` → verify inotify-driven vhost creation.
9. Archive `anvil/docker/php/Dockerfile` (kept for reference, no longer used).

### Phase 2: Production (EC2)

1. Update `docker-compose.ec2.yml` (same structural changes).
2. Adapt `entrypoint-ssm.sh` for FrankenPHP (same SSM logic, different CMD).
3. Update `certbot-setup.sh` for Tengine.
4. Update `cloud-init.yaml` to build the Tengine image on the EC2 host.
5. Test: full EC2 provision → RDS → certbot → verify HTTPS serving.

### Phase 3: Tengine Dynamic Config (Future)

- Replace `nginx -s reload` with Tengine's dynamic server API.
- Add upstream health checks (`ngx_upstream_check_module`) to auto-detect dead FrankenPHP workers.
- Add DSO-loaded modules for observability (prometheus metrics) without image rebuilds.

### Phase 4: FrankenPHP-Specific Features (Future)

- **Mercure**: Enable built-in Mercure hub for real-time updates (HUB-28, HUB-31).
- **Early Hints**: Use FrankenPHP's early hints support for faster perceived page loads.
- **Worker preloading**: Use `frankenphp run --preload` to preload the DGLab Kernel before accepting requests.

---

## 8. Rollback Strategy

If the Tengine + FrankenPHP stack proves unstable:

1. Revert `docker-compose.local.yml` to the committed nginx + PHP-FPM version (`git checkout HEAD -- anvil/docker/docker-compose.local.yml`).
2. Revert `vhost.conf.tpl` to the `fastcgi_pass` version.
3. `anvilctl stop && anvilctl start` restores the old stack.

The rollback is a single `git revert` on the compose file + template. No data migration, no schema change, no DNS change. The `www/` volume is identical between both stacks.

---

## 9. Risk Register

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Tengine CVE lag (patches arrive later than nginx) | Medium | High | Pin TENGINE_VERSION in anvil.conf; subscribe to Tengine GitHub advisories; have a tested nginx fallback compose file |
| FrankenPHP worker memory leak (long-running process) | Low | High | The `pulse()` scope uses WeakMap (auto-evicts on Fiber GC); FrankenPHP itself restarts workers after N requests (configurable via `FRANKENPHP_MAX_REQUESTS`); monitor with `loom top` equivalent |
| proxy_pass overhead vs fastcgi_pass | Low | Low | HTTP proxy adds ~0.1ms per request vs FastCGI; negligible compared to PHP execution time. Tengine's unbuffered proxy minimizes this further |
| DSO module incompatibility | Low | Medium | DSO is Tengine's most tested feature; but pin module versions and test in staging before production |
| FrankenPHP container size | Low | Low | Official image is ~150MB (vs ~80MB for php:8.3-fpm). Acceptable for a 2-container architecture |

---

## 10. Validation Checklist

- [ ] `docker compose build tengine` succeeds
- [ ] `docker compose up -d` starts both tengine and frankenphp containers
- [ ] `https://demo.test` serves a PHP page (verify via browser / curl)
- [ ] `anvilctl new testproj` generates vhost, issues cert, proxy works
- [ ] `anvilctl ssl testproj` issues mkcert cert, TLS trusted
- [ ] `anvilctl vhost_remove testproj` cleans up
- [ ] `anvilctl watch` auto-creates vhost on new `www/` directory
- [ ] PHP info page shows `frankenphp` in SERVER_SOFTWARE
- [ ] OpCache is enabled and warm (check `opcache_get_status()`)
- [ ] EC2 stack: certbot issues Let's Encrypt cert, HTTPS works
- [ ] EC2 stack: RDS credentials fetched from SSM, DB connection works
- [ ] Rollback: reverting compose + template restores PHP-FPM stack

---

## 11. Relationship to Architecture Documents

| Document | Relationship |
|---|---|
| `DGLAB-AS-OS-RUNTIME.md` | Specifies the Fiber-based cooperative scheduler that makes FrankenPHP mandatory (OD-07). This doc implements the deployment consequence. |
| `OPEN-DECISIONS.md` OD-07 | Records FrankenPHP as accepted runtime. This doc is the implementation of that decision. |
| `DEPLOY-01.md` | Specifies the shared OCI base image (PHP-FPM + Nginx + Supervisor). This doc supersedes the PHP-FPM and Nginx components of DEPLOY-01. DEPLOY-01 must be updated to reference Tengine + FrankenPHP once this reimplementation lands. |
| `CORE-02.md` | The `pulse()` scope and `WeakMap` implementation assume a Fiber-capable runtime. FrankenPHP provides this. |
| `STRUCTURE-06-Boot.md` | The boot sequence maps to Linux boot. FrankenPHP worker startup is the "kernel init" phase. |
| `anvil/README.md` | This doc adds to the Anvil documentation; README.md should be updated to reference Tengine + FrankenPHP after landing. |

---

**Provenance:** Written 2026-08-24. Triggered by OD-07 ratification consequence (FrankenPHP mandatory) and architecture lead's question about combining Tengine with FrankenPHP for production use in Anvil.
