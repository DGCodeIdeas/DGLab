# ReImplementation_Instruction.md — Anvil v2

**Status:** Draft — awaiting architecture lead review  
**Date:** 2026-08-26  
**Replaces:** `anvil/README.md` (legacy nginx + PHP-FPM model)  
**Companion:** `ADR-017` (Fiber-based cooperative runtime), `DEPLOY-01` (containerized deployment), `DGLAB-AS-OS-RUNTIME.md`  

---

## 1. Executive Summary

### Can Tengine and FrankenPHP be combined for production?

**Yes. This is not only possible — it is the recommended production architecture for DGLab under ADR-017.**

Tengine (Alibaba's Nginx fork) and FrankenPHP serve **complementary, non-overlapping roles** in the request path:

| Layer | Responsibility | Technology |
|---|---|---|
| **Edge / Reverse Proxy** | SSL termination, HTTP/2, static file serving, rate limiting, WAF, geo-routing, load balancing across upstreams | **Tengine** |
| **Application Server** | PHP execution, worker lifecycle, Fiber scheduling, Pulse management, tenant isolation | **FrankenPHP** |

Tengine reverse-proxies dynamic requests to FrankenPHP's worker-mode HTTP endpoint. Static assets are served directly by Tengine without ever hitting FrankenPHP. This is the same pattern as Nginx → PHP-FPM, except FrankenPHP replaces PHP-FPM and adds long-lived workers, Fiber scheduling, and the Caddy-based HTTP server.

**Why this combination makes sense for DGLab:**
- Tengine's `dyups` (dynamic upstream) module enables zero-downtime rolling deployments without an external load balancer — critical for solo-operator edge nodes.
- Tengine's `concat` module reduces asset requests for the Wheel visualization.
- FrankenPHP's worker mode is **required** for ADR-017's Fiber-based cooperative scheduler. PHP-FPM is incompatible (one process per request, terminated after response).
- FrankenPHP's built-in HTTPS (via Caddy's ACME integration) can be used in development; in production, Tengine handles TLS and talks HTTP/1.1 or HTTP/2 to FrankenPHP upstreams.

**What does NOT work:**
- FrankenPHP as an Nginx/Tengine module (no such module exists; FrankenPHP is a standalone binary or a Caddy module).
- PHP-FPM alongside FrankenPHP for the same application (mutually exclusive runtime models; pick one per deployment).

---

## 2. Architecture: Three Tiers, One Runtime

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  DEVELOPMENT LAPTOP                    │  STAGING VM        │  PRODUCTION   │
│  (Ubuntu / Fedora / Arch / Debian)     │  (cloud VM)        │  (EC2 / edge) │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐              │
│  │  Tengine *   │    │  Tengine     │    │  Tengine     │  ← Edge     │
│  │  (optional)  │    │  (reverse    │    │  (SSL,       │    proxy    │
│  │              │    │   proxy)     │    │   static,    │             │
│  └──────┬───────┘    └──────┬───────┘    │   WAF, LB)   │             │
│         │                   │             └──────┬───────┘             │
│         │ (direct)          │ (reverse proxy)    │ (reverse proxy)    │
│         ▼                   ▼                    ▼                    │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐              │
│  │ FrankenPHP   │    │ FrankenPHP   │    │ FrankenPHP   │  ← App      │
│  │ (worker mode)│    │ (worker mode)│    │ (worker mode)│    server   │
│  │ port 2019    │    │ port 2019    │    │ port 2019    │             │
│  │ admin API    │    │ admin API    │    │ admin API    │             │
│  └──────┬───────┘    └──────┬───────┘    └──────┬───────┘             │
│         │                   │                    │                    │
│  ┌──────┴───────┐    ┌──────┴───────┐    ┌──────┴───────┐              │
│  │ MySQL 8      │    │ MySQL 8      │    │ MySQL 8      │  ← Data     │
│  │ Redis 7      │    │ Redis 7      │    │ Redis 7      │    tier     │
│  │ (Docker)     │    │ (Docker)     │    │ (RDS / local)│             │
│  └──────────────┘    └──────────────┘    └──────────────┘              │
│                                                                         │
│  * Development: FrankenPHP can run standalone (port 80/443) without    │
│    Tengine for simplicity. Tengine is optional for local dev.          │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Key principle:** The runtime model (FrankenPHP worker mode + Fiber scheduler) is **identical** across all environments. Only the edge proxy layer and data tier topology change.

---

## 3. Environment Matrix

| Concern | Development (Linux Laptop) | Staging VM | Production / Edge |
|---|---|---|---|
| **FrankenPHP** | Standalone or behind local Tengine | Behind Tengine | Behind Tengine |
| **Tengine** | Optional (simpler without) | Required | Required |
| **TLS** | `mkcert` (local CA) | Let's Encrypt (staging) | Let's Encrypt (production) |
| **MySQL** | Docker container | Docker container | RDS or managed |
| **Redis** | Docker container | Docker container | ElastiCache or managed |
| **Workers** | 1–2 (laptop RAM) | 2–4 | 4–16 (per instance) |
| **Tenant isolation** | Logical (`tenant_id`) | Logical (`tenant_id`) | Logical + optional container-per-tenant |
| **Monitoring** | `loom top` (CLI) | `loom top` + basic metrics | `loom top` + HUB-31 Analytics + alerts |
| **Deployment** | `git pull` + `dglab reboot` | `anvil deploy staging` | `anvil deploy production` |
| **Cost** | $0 (local) | $20–50/mo (VM) | $50–200/mo (EC2 + RDS) |

---

## 4. Development Setup — Linux Laptops

### 4.1 Supported Distributions

Anvil v2 targets the four major Linux laptop distributions:

| Distro | Version | Package Manager | Notes |
|---|---|---|---|
| **Ubuntu** | 24.04 LTS, 22.04 LTS | `apt` | Primary target. Best Docker support. |
| **Fedora** | 40, 41 | `dnf` | Secondary. SELinux may require `setenforce 0` for Docker. |
| **Arch Linux** | Rolling | `pacman` | Tertiary. AUR required for some packages. |
| **Debian** | 12 (Bookworm) | `apt` | Same as Ubuntu, older kernel. |

### 4.2 Prerequisites

```bash
# All distros: Docker Engine + Compose plugin
# Ubuntu/Debian:
sudo apt update && sudo apt install -y docker.io docker-compose-plugin

# Fedora:
sudo dnf install -y docker docker-compose
sudo systemctl enable --now docker

# Arch:
sudo pacman -S docker docker-compose
sudo systemctl enable --now docker

# Add user to docker group (logout/login required after)
sudo usermod -aG docker $USER
```

### 4.3 FrankenPHP Installation (Development)

```bash
# Install FrankenPHP binary (static build, no dependencies)
# This is the canonical method per ADR-017.
curl -fsSL https://github.com/dunglas/frankenphp/releases/download/v1.2.5/frankenphp-linux-x86_64 -o /usr/local/bin/frankenphp
chmod +x /usr/local/bin/frankenphp

# Verify
frankenphp version
# Expected: FrankenPHP v1.2.5 PHP 8.3.x

# Install Caddy module for FrankenPHP (for worker mode config)
# FrankenPHP is distributed as a Caddy module; the binary above includes it.
# No separate Caddy installation needed.
```

### 4.4 Tengine Installation (Development — Optional)

```bash
# Tengine is optional for local development. FrankenPHP standalone is sufficient.
# Install only if you need to test Tengine-specific features (dyups, concat, etc.)

# Ubuntu/Debian (from source — no official apt repo for Tengine):
# Tengine 3.1.0 is the current stable release.
git clone https://github.com/alibaba/tengine.git /tmp/tengine
cd /tmp/tengine
git checkout 3.1.0

# Build with standard modules + dyups + concat
./configure   --prefix=/usr/local/tengine   --with-http_ssl_module   --with-http_v2_module   --with-http_realip_module   --add-module=modules/ngx_http_upstream_dyups_module   --add-module=modules/ngx_http_concat_module
make -j$(nproc)
sudo make install

# Add to PATH
sudo ln -sf /usr/local/tengine/sbin/nginx /usr/local/bin/tengine
```

### 4.5 Anvil v2 Local Development Stack

```bash
# Clone DGLab
git clone https://github.com/DGCodeIdeas/DGLab.git ~/dglab
cd ~/dglab

# Start data tier (MySQL + Redis via Docker Compose)
docker compose -f infrastructure/compose/dev-data.yml up -d

# Start FrankenPHP (worker mode, Caddyfile in repo)
frankenphp run --config infrastructure/frankenphp/Caddyfile.dev

# Or: start with Tengine in front (if installed)
# tengine -c infrastructure/tengine/nginx.dev.conf

# Verify
open https://dglab.test    # mkcert TLS, resolves via dnsmasq
```

### 4.6 Caddyfile (Development)

```caddyfile
# infrastructure/frankenphp/Caddyfile.dev
{
    frankenphp {
        worker {
            file public/index.php
            env APP_ENV=dev
            env DB_DSN=mysql:host=127.0.0.1;dbname=dglab_dev
            env REDIS_URL=redis://127.0.0.1:6379
            num 2          # 2 workers (laptop RAM)
            max_requests 1000
        }
    }
}

:443 {
    tls internal          # Self-signed; mkcert overrides for *.test
    root * public/
    php_server {
        try_files {path} {path}/index.php index.php
    }
    file_server
}
```

### 4.7 dnsmasq + mkcert (Unchanged from Anvil v1)

```bash
# dnsmasq: wildcard *.test → 127.0.0.1
# mkcert: trusted local CA for *.test domains
# These are unchanged from Anvil v1 and remain the canonical local-dev TLS solution.

sudo apt install -y dnsmasq inotify-tools
sudo mkdir -p /etc/dnsmasq.d
echo 'address=/.test/127.0.0.1' | sudo tee /etc/dnsmasq.d/dglab-test
sudo systemctl restart dnsmasq

# mkcert
curl -fsSL https://github.com/FiloSottile/mkcert/releases/download/v1.4.4/mkcert-v1.4.4-linux-amd64 -o /usr/local/bin/mkcert
chmod +x /usr/local/bin/mkcert
mkcert -install
mkcert -key-file infrastructure/tls/dglab.test-key.pem -cert-file infrastructure/tls/dglab.test.pem "*.test" dglab.test localhost 127.0.0.1 ::1
```

---

## 5. Staging VM Setup

### 5.1 VM Specification

| Resource | Minimum | Recommended |
|---|---|---|
| CPU | 2 vCPU | 4 vCPU |
| RAM | 4 GB | 8 GB |
| Disk | 20 GB SSD | 40 GB SSD |
| OS | Ubuntu 24.04 LTS | Ubuntu 24.04 LTS |
| Network | Public IPv4 + IPv6 | Public IPv4 + IPv6 |

### 5.2 Provisioning via Anvil v2

```bash
# Anvil v2 CLI (replaces the legacy EC2-only anvilctl)
anvil provision staging   --provider aws   --region eu-west-2   --type t3.medium   --disk 40   --tls staging   --frankenphp-workers 4

# This command:
# 1. Creates an EC2 instance (or Hetzner / DigitalOcean VM)
# 2. Installs Docker, FrankenPHP, Tengine
# 3. Configures Let's Encrypt staging certificates
# 4. Deploys the current git branch
# 5. Runs health checks via HUB-15 /health endpoint
```

### 5.3 Tengine Configuration (Staging)

```nginx
# /usr/local/tengine/conf/nginx.staging.conf
# Tengine acts as reverse proxy to FrankenPHP upstream.

upstream frankenphp {
    server 127.0.0.1:2019;
    keepalive 64;
}

server {
    listen 443 ssl http2;
    server_name staging.dglab.example;

    ssl_certificate /etc/letsencrypt/live/staging.dglab.example/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/staging.dglab.example/privkey.pem;

    # Static assets: served directly by Tengine, never hit FrankenPHP
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        root /var/www/dglab/public/;
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Health check: direct pass-through (no caching)
    location /health {
        proxy_pass http://frankenphp;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_read_timeout 30s;
    }

    # All other requests → FrankenPHP worker
    location / {
        proxy_pass http://frankenphp;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_read_timeout 60s;
        proxy_send_timeout 60s;
    }
}

# HTTP → HTTPS redirect
server {
    listen 80;
    server_name staging.dglab.example;
    return 301 https://$server_name$request_uri;
}
```

### 5.4 FrankenPHP Configuration (Staging)

```caddyfile
# /etc/frankenphp/Caddyfile.staging
{
    frankenphp {
        worker {
            file /var/www/dglab/public/index.php
            env APP_ENV=staging
            env DB_DSN=mysql:host=rds-staging.xxx.eu-west-2.rds.amazonaws.com;dbname=dglab_staging
            env REDIS_URL=redis://elasticache-staging.xxx.cache.amazonaws.com:6379
            num 4
            max_requests 5000
        }
    }
    admin 127.0.0.1:2019
}

:2019 {
    # FrankenPHP listens on localhost:2019; Tengine reverse-proxies here.
    # No TLS here — Tengine handles SSL termination.
    root * /var/www/dglab/public/
    php_server {
        try_files {path} {path}/index.php index.php
    }
    file_server
}
```

---

## 6. Production / Edge Node Setup

### 6.1 Edge Node Specification

| Resource | Minimum | Recommended |
|---|---|---|
| CPU | 4 vCPU | 8 vCPU (ARM Graviton3 for AWS) |
| RAM | 8 GB | 16 GB |
| Disk | 40 GB NVMe SSD | 100 GB NVMe SSD |
| OS | Ubuntu 24.04 LTS | Ubuntu 24.04 LTS |
| Network | Public IPv4 + IPv6, 1 Gbps | Public IPv4 + IPv6, 10 Gbps |
| Uptime | 99.9% | 99.99% |

### 6.2 Tengine + FrankenPHP Production Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              EDGE NODE                                      │
│  (Single EC2 instance or bare-metal server — solo-operator model)          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │  Tengine (edge proxy)                                               │   │
│  │  ├─ SSL termination (Let's Encrypt, auto-renew via certbot)        │   │
│  │  ├─ HTTP/2 + HTTP/3 (QUIC) push for static assets                  │   │
│  │  ├─ Rate limiting (per-IP, per-tenant)                             │   │
│  │  ├─ WAF rules (mod_security or Tengine's own)                      │   │
│  │  ├─ Geo-routing (if multi-region in future)                        │   │
│  │  ├─ dyups: dynamic upstream for zero-downtime deploys              │   │
│  │  └─ concat: merge CSS/JS for Wheel viz (ISPOKE-05)                │   │
│  └────────────────────────────────┬────────────────────────────────────┘   │
│                                   │                                         │
│  ┌────────────────────────────────┴────────────────────────────────────┐   │
│  │  FrankenPHP (worker pool)                                          │   │
│  │  ├─ 8 workers (configurable via HUB-01 APP_WORKERS env)           │   │
│  │  ├─ Fiber scheduler (ADR-017)                                     │   │
│  │  ├─ Pulse tracer (HUB-15)                                         │   │
│  │  ├─ Tenant isolation (HUB-21, logical)                            │   │
│  │  └─ Admin API on :2019 (localhost only, Tengine proxies /admin)   │   │
│  └────────────────────────────────┬────────────────────────────────────┘   │
│                                   │                                         │
│  ┌────────────────────────────────┴────────────────────────────────────┐   │
│  │  Data Tier                                                         │   │
│  │  ├─ MySQL 8 (RDS or local Docker — DEPLOY-02 decides)             │   │
│  │  ├─ Redis 7 (ElastiCache or local Docker)                         │   │
│  │  └─ Local backup volume (/var/backups/dglab/)                     │   │
│  └────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 6.3 Zero-Downtime Deployment via Tengine dyups

```bash
# Anvil v2 production deploy command
anvil deploy production   --strategy blue-green   --dyups-socket /var/run/tengine/dyups.sock   --health-check /health   --timeout 30s

# What happens:
# 1. Build new FrankenPHP worker image (docker build)
# 2. Start new container on port 2020 (green)
# 3. Run health checks against green /health endpoint
# 4. If green passes: update Tengine upstream via dyups API
#    curl -X POST --unix-socket /var/run/tengine/dyups.sock #      -d 'server 127.0.0.1:2020;' #      /upstream/frankenphp
# 5. Drain old container (blue) — stop sending new traffic, wait for in-flight
# 6. If drain timeout exceeded: SIGKILL; else SIGTERM graceful shutdown
# 7. Remove blue container
```

### 6.4 FrankenPHP Admin API Security

```bash
# FrankenPHP's admin API (port 2019) exposes runtime metrics and config.
# It must NEVER be exposed to the public internet.

# Tengine configuration: proxy /admin to FrankenPHP admin, but restrict by IP
location /admin/ {
    allow 127.0.0.1;
    allow 10.0.0.0/8;      # VPN range
    deny all;

    proxy_pass http://127.0.0.1:2019;
    proxy_http_version 1.1;
}

# Access via SSH tunnel only:
# ssh -L 2019:localhost:2019 user@edge-node
# open http://localhost:2019/metrics
```

### 6.5 Monitoring Stack

| Component | Tool | Purpose |
|---|---|---|
| **OS metrics** | `node_exporter` + Prometheus | CPU, RAM, disk, network |
| **Application metrics** | FrankenPHP admin API + HUB-31 | Worker count, Pulse latency, tenant load |
| **Logs** | `promtail` + Loki (or `journald` + `vector`) | Structured JSON logs from CORE-09 |
| **Alerts** | Prometheus Alertmanager | PagerDuty / Slack / email |
| **CLI** | `loom top`, `loom ps`, `loom pulse:trace` | Real-time OS-feel monitoring |

---

## 7. Anvil v2 Tool Reimplementation

### 7.1 What Changes from Anvil v1

| Component | v1 (Legacy) | v2 (Current) |
|---|---|---|
| **Runtime** | PHP-FPM + Nginx | FrankenPHP (worker mode) |
| **Edge proxy** | Nginx | Tengine (Nginx fork with dyups/concat) |
| **Local dev** | `php -S` or nginx vhost | FrankenPHP standalone |
| **Deployment** | EC2-only, manual | Multi-provider (AWS, Hetzner, DO), automated |
| **TLS** | Let's Encrypt only | mkcert (dev) + Let's Encrypt (staging/prod) |
| **Provisioning** | Bash + AWS CLI | Bash + Docker + provider CLI |
| **Config format** | Shell variables | `infrastructure/config/{env}.yml` |

### 7.2 New Directory Structure

```
anvil/
├── README.md                          # This document (replaces old README)
├── install.sh                         # Idempotent bootstrap (updated for FrankenPHP)
├── bin/
│   ├── anvilctl                       # CLI dispatcher (unchanged interface)
│   ├── anvil-tui.sh                 # Interactive menu (updated options)
│   └── anvil-web.sh                 # Web UI launcher
├── lib/                               # Shared bash engine (unchanged core)
│   ├── core.sh
│   ├── docker.sh
│   ├── frankenphp.sh                # NEW: FrankenPHP lifecycle
│   ├── tengine.sh                   # NEW: Tengine config generation
│   ├── tls.sh                       # Updated: mkcert + Let's Encrypt
│   └── providers/
│       ├── aws.sh
│       ├── hetzner.sh               # NEW
│       └── digitalocean.sh          # NEW
├── config/
│   ├── development.yml
│   ├── staging.yml
│   └── production.yml
├── provisioning/
│   ├── aws-ec2.yml                  # CloudFormation / Terraform wrapper
│   ├── hetzner-cloud.yml            # NEW
│   └── docker-compose.staging.yml
├── docker/
│   ├── frankenphp/
│   │   ├── Dockerfile               # FrankenPHP base image
│   │   ├── Caddyfile.dev
│   │   ├── Caddyfile.staging
│   │   └── Caddyfile.production
│   └── tengine/
│       ├── Dockerfile               # Tengine build from source
│       ├── nginx.dev.conf
│       ├── nginx.staging.conf
│       └── nginx.production.conf
├── scripts/
│   ├── deploy.sh                    # Zero-downtime deploy via dyups
│   ├── backup.sh                    # Database + asset backup
│   └── health-check.sh            # HUB-15 /health poller
├── tui/
│   └── menus.json                   # Updated menu definitions
└── web/
    └── index.php                    # PHP SPA (unchanged interface)
```

### 7.3 Key Implementation Tasks

| # | Task | Effort | Priority |
|---|---|---|---|
| 1 | Update `install.sh` to install FrankenPHP binary + Tengine from source | 1 day | 🔥 Critical |
| 2 | Create `lib/frankenphp.sh` — start/stop/reload workers, read admin API | 1 day | 🔥 Critical |
| 3 | Create `lib/tengine.sh` — generate configs, dyups API wrapper | 1 day | 🔥 Critical |
| 4 | Create `docker/frankenphp/Dockerfile` — production FrankenPHP image | 1 day | 🔥 Critical |
| 5 | Create `docker/tengine/Dockerfile` — Tengine build with dyups/concat | 1 day | 🔥 Critical |
| 6 | Update `anvilctl` subcommands: `start`, `stop`, `reload`, `deploy` | 2 days | 🔥 Critical |
| 7 | Add provider support: Hetzner Cloud, DigitalOcean | 2 days | Medium |
| 8 | Implement `scripts/deploy.sh` — blue-green via dyups | 2 days | Medium |
| 9 | Add `config/{env}.yml` — unified config (replaces shell vars) | 1 day | Medium |
| 10 | Update TUI/Web UI menus for new options | 1 day | Low |

### 7.4 Backward Compatibility

- Anvil v1 commands (`anvilctl local up`, `anvilctl ec2 provision`) are **deprecated** but remain functional via wrapper scripts that emit a migration warning.
- The old `nginx` vhost model is preserved as `anvilctl local up --legacy-nginx` for emergency rollback.
- PHP-FPM is **not supported** for new deployments. Existing PHP-FPM deployments must migrate to FrankenPHP before receiving DGLab updates post-ADR-017.

---

## 8. Migration Path from Anvil v1

### 8.1 Assessment

```bash
# Check current runtime
anvilctl status
# If it shows "PHP-FPM + Nginx", you are on v1 and need migration.

# Check if any production deployments use PHP-FPM
grep -r "php-fpm" infrastructure/deploy/
```

### 8.2 Migration Steps (Production)

1. **Provision new FrankenPHP + Tengine stack** alongside existing PHP-FPM + Nginx stack.
2. **Run parallel health checks** for 24 hours against the new stack.
3. **Update DNS** (or Tengine upstream) to point traffic to new stack.
4. **Decommission old stack** after 48 hours of zero errors.
5. **Update `anvil/` directory** to v2 files (this is a git pull + anvil upgrade).

### 8.3 Migration Steps (Development)

```bash
# 1. Stop old stack
anvilctl local down

# 2. Pull v2
anvil upgrade

# 3. Install FrankenPHP (idempotent)
anvilctl install frankenphp

# 4. Start new stack
anvilctl local up
# → Starts FrankenPHP standalone (no Tengine needed for dev)
# → MySQL + Redis via Docker Compose (unchanged)
# → mkcert TLS (unchanged)
# → dnsmasq *.test (unchanged)

# 5. Verify
open https://dglab.test
loom top  # Should show FrankenPHP workers, not PHP-FPM pools
```

---

## 9. Security Hardening

| Layer | Control | Implementation |
|---|---|---|
| **Edge** | TLS 1.3 only, HSTS, OCSP stapling | Tengine `ssl_protocols TLSv1.3;` |
| **Edge** | Rate limiting | Tengine `limit_req_zone` per tenant |
| **Edge** | WAF | `mod_security` or Tengine Lua rules |
| **Application** | Non-root execution | FrankenPHP `USER www-data` in Dockerfile |
| **Application** | Secret injection | Runtime env vars from Vault/Secrets Manager |
| **Application** | Admin API lockdown | Tengine `allow 10.0.0.0/8; deny all;` |
| **Data** | Encryption at rest | RDS encryption, EBS encrypted volumes |
| **Data** | Encryption in transit | TLS 1.3 for all inter-service communication |
| **OS** | Minimal attack surface | Ubuntu Minimal + Docker + FrankenPHP + Tengine only |
| **OS** | Automatic updates | `unattended-upgrades` for security patches |

---

## 10. Troubleshooting

### 10.1 FrankenPHP won't start

```bash
# Check if port 80/443 is already bound
sudo ss -tlnp | grep -E ':80|:443'
# → If nginx is still running: sudo systemctl stop nginx

# Check Caddyfile syntax
frankenphp validate --config infrastructure/frankenphp/Caddyfile.dev

# Check worker logs
frankenphp run --config infrastructure/frankenphp/Caddyfile.dev --watch
```

### 10.2 Tengine → FrankenPHP connection refused

```bash
# Verify FrankenPHP is listening on localhost:2019
curl -s http://127.0.0.1:2019/metrics
# → Should return JSON metrics

# Check Tengine upstream config
sudo tengine -t
# → Should show "syntax is ok"

# Check dyups socket exists
ls -la /var/run/tengine/dyups.sock
```

### 10.3 Fiber scheduler hangs (infinite loop in Pulse)

```bash
# FrankenPHP worker mode is cooperative, not preemptive.
# A Pulse with an infinite loop blocks the scheduler.
# Mitigation: set max_requests to force worker recycle.

# Emergency: kill the worker
loom kill <worker-pid>
# FrankenPHP will spawn a replacement worker automatically.

# Long-term: fix the Pulse code to yield at tick boundaries.
```

### 10.4 DNS broken after Anvil install (Xubuntu)

```bash
# See RUNBOOK-ANVIL-DNS.md for the full fix.
# Quick check:
cat /etc/resolv.conf
# If it shows 127.0.0.1 and dnsmasq is not running:
sudo systemctl start dnsmasq
# Or use NetworkManager's dnsmasq plugin (Approach B in runbook).
```

---

## 11. Appendix: Tengine vs Nginx Feature Comparison

| Feature | Nginx | Tengine | DGLab Relevance |
|---|---|---|---|
| **HTTP/2** | ✅ | ✅ | Required |
| **HTTP/3 (QUIC)** | ❌ (commercial) | ❌ | Not yet needed |
| **Dynamic upstreams (dyups)** | ❌ | ✅ | **Critical** — zero-downtime deploys |
| **Concat module** | ❌ | ✅ | **Useful** — Wheel asset merging |
| **Lua scripting** | ❌ (OpenResty) | ✅ | **Useful** — WAF rules |
| **SPDY** | ❌ (deprecated) | ✅ (legacy) | Not needed |
| **Community** | Very large | Alibaba-centric | Acceptable for solo operator |
| **Documentation** | Excellent | Good (Chinese + English) | Sufficient |

**Verdict:** Tengine's `dyups` and `concat` modules justify the switch from Nginx for DGLab's production edge nodes. For development, the difference is irrelevant — use whichever is easier to install.

---

## 12. Decision Log

| Date | Decision | Rationale |
|---|---|---|
| 2026-08-22 | FrankenPHP as canonical runtime | ADR-017: Fiber-based cooperative scheduler requires long-lived workers |
| 2026-08-26 | Tengine as edge proxy | `dyups` enables zero-downtime deploys without external load balancer; `concat` optimizes Wheel viz |
| 2026-08-26 | FrankenPHP standalone for dev | Simpler than Tengine + FrankenPHP; no need for dyups/concat in local dev |
| 2026-08-26 | Tengine required for staging + prod | SSL termination, static file serving, rate limiting, WAF all belong at the edge |
| 2026-08-26 | Multi-provider support (AWS, Hetzner, DO) | Reduces vendor lock-in; Hetzner is 3× cheaper than AWS for equivalent specs |

---

*End of document. Questions: architecture lead (DGCI). Implementation: next available agent.*
