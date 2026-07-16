# Anvil

> Local-development automation and EC2/RDS deployment tool for PHP projects.

Anvil gives you wildcard `*.test` local domains with trusted TLS, an
inotify-driven nginx vhost watcher, a MySQL database, a TUI and a Web UI — and
reuses the exact same engine to provision a hardened EC2 + RDS MySQL stack in
AWS with Let's Encrypt TLS and a loopback-only management UI reached through an
SSH tunnel.

---

## Table of contents

- [Overview](#overview)
- [Architecture: one shared bash engine, two thin skins](#architecture-one-shared-bash-engine-two-thin-skins)
- [Layout](#layout)
- [Install](#install)
- [Local development usage](#local-development-usage)
- [EC2 mode](#ec2-mode)
- [⚠️ THE COST CORRECTION — account-date decision](#-the-cost-correction--account-date-decision)
- [Billing alarm](#billing-alarm)
- [Security notes](#security-notes)
- [Known limitations / deferred](#known-limitations--deferred)

---

## Overview

Anvil is a single bash engine wrapped by three thin front-ends:

- `anvilctl` — a CLI dispatcher (the thing you type in a terminal).
- `anvil-tui.sh` — an interactive `dialog`/`whiptail` menu.
- The **Web UI** (`web/`) — a PHP SPA + JSON API served by PHP's built-in
  server on `127.0.0.1:9999`.

All three call the **same** functions in [`anvil/lib/*.sh`](anvil/lib). There is
no duplicated business logic: the TUI and Web UI are skins, and `anvilctl` only
dispatches.

---

## Architecture: one shared bash engine, two thin skins

```
                ┌──────────────┐   ┌──────────────┐   ┌──────────────────┐
   you  ──────▶ │  anvilctl   │   │ anvil-tui.sh │   │  Web UI (PHP)   │
   (CLI)        │  (dispatch) │   │  (dialog UI) │   │  web/public/*    │
                └──────┬───────┘   └──────┬───────┘   └────────┬─────────┘
                       │                  │                    │ shells out
                       │                  │                    │ via AnvilClient
                       └──────────────────┼────────────────────┘
                                          ▼
                              ┌──────────────────────────┐
                              │   anvil/lib/*.sh  (engine)│
                              │  docker vhost ssl project │
                              │  db  ec2  web             │
                              └──────────────────────────┘
                                          │
                          ┌───────────────┼────────────────┐
                          ▼               ▼                ▼
                     docker compose    mkcert/        aws cli (ec2/rds/
                     (local stack)     dnsmasq         ssm/cloudwatch)
```

- **`anvil/bin/anvilctl`** resolves the Anvil root, sources
  [`anvil/config/anvil.conf`](anvil/config/anvil.conf) and every
  [`anvil/lib/*.sh`](anvil/lib) file, then maps each subcommand to a lib
  function. It contains **no business logic**.
- **`anvil/tui/anvil-tui.sh`** sources the same lib and calls the same
  functions directly from its menu actions.
- **`anvil/web/`** is a PHP front controller ([`web/public/index.php`](anvil/web/public/index.php))
  + router ([`web/src/Api/Router.php`](anvil/web/src/Api/Router.php)) that shells
  out to `anvilctl` through [`web/src/Api/AnvilClient.php`](anvil/web/src/Api/AnvilClient.php)
  and shapes the output as `{"ok":bool,"data":...,"error":...}` JSON
  ([`web/src/Api/Api.php`](anvil/web/src/Api/Api.php)). The real work still
  happens in the bash engine.

This is the **"one shared bash engine, two thin skins"** principle: the TUI and
Web UI are interchangeable presentations over `lib/`, and `anvilctl` is just the
CLI skin that dispatches.

---

## Layout

```
anvil/
├── README.md                     # this file
├── install.sh                    # Phase 1 bootstrap (dialog/whiptail, idempotent)
├── bin/
│   └── anvilctl                  # thin CLI dispatcher -> lib functions
├── config/
│   └── anvil.conf                # bash-sourcable config (INSTANCE_TYPE, paths, AWS)
├── lib/                          # THE ENGINE (shared by all three skins)
│   ├── docker.sh                 # docker compose up/down/ps/logs
│   ├── vhost.sh                  # nginx vhost render (envsubst) + reload/remove
│   ├── ssl.sh                    # mkcert CA install + per-project certs
│   ├── project.sh                # register/scan/list projects + build assets
│   ├── db.sh                     # MySQL database create (injection-safe)
│   ├── ec2.sh                    # EC2 + RDS provision, tunnel, billing, alarm
│   └── web.sh                    # Web UI PHP server up/down (loopback only)
├── docker/
│   ├── docker-compose.local.yml  # local stack: nginx/php/mysql/phpmyadmin/redis
│   ├── docker-compose.ec2.yml    # EC2 stack: nginx/php/phpmyadmin/redis (RDS, no mysql)
│   ├── nginx/
│   │   ├── conf.d/               # rendered per-project vhosts (gitignored content)
│   │   ├── certs/                # mkcert certs (gitignored content)
│   │   └── templates/
│   │       └── vhost.conf.tpl    # vhost template rendered by lib/vhost.sh
│   └── php/
│       ├── Dockerfile            # PHP 8.3 FPM image
│       └── entrypoint-ssm.sh     # fetches RDS creds from SSM SecureString
├── scripts/
│   └── vhost-watcher.sh          # inotify loop: auto vhost + ssl on www/ changes
├── tui/
│   └── anvil-tui.sh              # dialog/whiptail menu skin
├── web/                          # Web UI skin (PHP SPA + JSON API)
│   ├── public/
│   │   ├── index.php             # front controller
│   │   ├── app.js                # SPA JS
│   │   └── assets/               # compiled style.css (gitignored)
│   ├── scss/
│   │   └── app.scss              # SCSS source (compiled by build-assets)
│   └── src/Api/
│       ├── Router.php            # route ?route=api/* -> handlers
│       ├── Api.php               # endpoint handlers
│       ├── AnvilClient.php       # shells out to anvilctl
│       └── AnvilResult.php       # result envelope
├── provisioning/                 # EC2/RDS helpers (thin wrappers over lib/ec2.sh)
│   ├── ec2-provision.sh          # -> anvil_ec2_provision
│   ├── rds-tunnel.sh             # -> anvil_ec2_tunnel
│   ├── certbot-setup.sh          # Let's Encrypt webroot + prod vhost render
│   └── cloud-init.yaml           # EC2 user-data (installs docker/aws, clones repo)
├── www/                          # per-project web roots (demo.test -> www/demo)
└── docs/
    └── VALIDATION.md             # manual end-to-end validation guide
```

---

## Install

Run as root (the installer re-execs with `sudo` if invoked unprivileged):

```bash
sudo ./install.sh
```

It presents a `dialog` (or `whiptail`) UI: welcome → component checklist →
progress gauge → summary. Every step is **idempotent** and safe to re-run. It
installs:

- **Docker Engine + Compose plugin** (official Docker apt repo, not snap).
- **dnsmasq** for `*.test` → `127.0.0.1`, including handling the
  **systemd-resolved** stub-listener conflict on port 53 (it writes
  `DNSStubListener=no` to `/etc/systemd/resolved.conf.d/anvil.conf`, points
  `/etc/resolv.conf` at `127.0.0.1`, and restarts the services).
- **mkcert** binary + local CA (`mkcert -install`).
- **dart-sass** (`sass`) binary for asset builds.
- **inotify-tools** (`inotifywait`) for the vhost watcher.

Binary downloads that fail are **reported, never a hard failure** (the summary
lists warnings). Requires a Debian/Ubuntu host (the installer uses `apt-get`).

---

## Local development usage

Bring the stack and Web UI up:

```bash
anvilctl start        # docker compose up -d + launch Web UI on 127.0.0.1:9999
anvilctl stop         # stop Web UI + docker compose down
anvilctl status       # container status + project list
anvilctl projects     # TSV: name <TAB> url <TAB> ssl(yes|no)
anvilctl scan         # register any unregistered folders in www/
anvilctl new demo     # create www/demo, issue cert, render vhost, reload nginx
anvilctl ssl demo     # install CA (once) + issue demo.test cert
anvilctl db create myapp   # create MySQL database (idempotent, name-validated)
anvilctl build-assets     # compile SCSS -> CSS (per project + Web UI)
anvilctl watch [--once]   # inotify loop: auto vhost+ssl on www/ create|delete
anvilctl logs             # tail the docker stack logs
```

Highlights:

- **`*.test` wildcard via dnsmasq** — every project `<name>` is served at
  `https://<name>.test`, resolved to `127.0.0.1` by dnsmasq.
- **mkcert trusted certs** — `anvilctl new`/`ssl` issue a per-project certificate
  from the local mkcert CA (already trusted by your browser/OS), so TLS is
  trusted with no browser warnings.
- **inotify vhost watcher** — `anvilctl watch` (or `scripts/vhost-watcher.sh`)
  watches `www/` and, on a new/deleted project directory, runs
  `anvil_project_register` / `anvil_vhost_remove` and reloads nginx.
- **Web UI at `http://127.0.0.1:9999`** — a PHP SPA that calls the same engine.
  It is bound strictly to loopback (never `0.0.0.0`); see
  [Security notes](#security-notes).

The local stack ([`docker-compose.local.yml`](anvil/docker/docker-compose.local.yml))
runs `nginx` (TLS + PHP-FPM reverse proxy), `php` (PHP 8.3 FPM), `mysql` 8.0,
`phpmyadmin` (bound to `127.0.0.1:8080` only), and `redis` (internal network
only). nginx reaches PHP via `fastcgi_pass php:9000` on the `anvil_net` bridge.

---

## EC2 mode

Provision a production stack on AWS:

```bash
anvilctl ec2 provision [opts]   # VPC + SG + key + RDS + EC2 (idempotent by Name)
anvilctl ec2 tunnel  --rds-endpoint E --host H --key K   # bastion SSH tunnel to RDS
anvilctl ec2 billing            # this month's AWS cost (aws ce get-cost-and-usage)
anvilctl ec2 billing-alarm [USD]# create a CloudWatch billing alarm (default $5)
anvilctl ec2 help               # full option reference
anvilctl billing                # alias for the cost check
```

Key points (all enforced in [`lib/ec2.sh`](anvil/lib/ec2.sh)):

- **RDS via SSM Parameter Store SecureString** — credentials are written to
  `/anvil/rds/{host,user,password,database}` as `SecureString` and read at
  container start by [`docker/php/entrypoint-ssm.sh`](anvil/docker/php/entrypoint-ssm.sh)
  using the EC2 instance role. (SSM, not Secrets Manager — see
  [Security notes](#security-notes).)
- **Developer bastion tunnel** — `anvilctl ec2 tunnel` (or
  [`provisioning/rds-tunnel.sh`](anvil/provisioning/rds-tunnel.sh)) forwards a
  local port to the **private** RDS instance through the EC2 host. RDS is never
  public.
- **Let's Encrypt via `certbot-setup.sh`** —
  [`provisioning/certbot-setup.sh`](anvil/provisioning/certbot-setup.sh) obtains
  certs with the webroot plugin and renders a production nginx vhost referencing
  the live `/etc/letsencrypt` certs (bind-mounted into the EC2 compose).
- **Web UI loopback-only on EC2** — the EC2 compose does **not** include the Web
  UI; it runs on the host bound to `127.0.0.1:9999` and is reached only through
  the SSH tunnel, e.g.:

  ```bash
  ssh -N -L 9999:127.0.0.1:9999 -i anvil-ec2-key.pem ec2-user@<public-ip>
  # then open http://127.0.0.1:9999 on your laptop
  ```

  `cloud-init.yaml` brings the stack up on boot via `anvil.service`
  (`COMPOSE_FILE=docker/docker-compose.ec2.yml anvilctl start`).

> **Note:** `anvilctl ec2 provision` writes the RDS endpoint to
> `/opt/anvil/docker/.env` on the host (best-effort over SSH) so phpMyAdmin can
> resolve it. The EC2 stack uses `db.t3.micro` RDS and the configured
> `INSTANCE_TYPE` for the EC2 instance.

---

## ⚠️ THE COST CORRECTION — account-date decision

This is the most important operational section. **`t3.micro` is the safe
default** in [`config/anvil.conf`](anvil/config/anvil.conf) (`INSTANCE_TYPE`).
Do not change it casually.

AWS's free-tier / Free-plan eligibility for `t3.small` depends on **when your
AWS account was created**:

- **Accounts created BEFORE 2025-07-15** (legacy 12-month free tier):
  - Only **`t2.micro` / `t3.micro`** qualify for the 12-month free tier.
  - **`t3.small` is NOT free-tier eligible.** It adds roughly
    **~$0.0208/hr (~$15/month)** of on-demand cost.
- **Accounts created ON or AFTER 2025-07-15** (credit-based Free plan):
  - `t3.small` is **console-marked eligible for 6 months**, but this is a
    **credit-balance model** that **burns down faster than micro** — it is
    **not unlimited free hours**. Once the credit is exhausted you pay on-demand.

**Therefore `t3.small` is only allowed when you explicitly pass
`--confirm-t3-small` to `anvilctl ec2 provision`**, and the engine prints a cost
warning before proceeding:

```bash
anvilctl ec2 provision --confirm-t3-small
# ERROR (without the flag, when INSTANCE_TYPE=t3.small):
#   INSTANCE_TYPE=t3.small is NOT free-tier eligible on AWS accounts created
#   before 2025-07-15. Estimated cost: ~$0.0208/hr (~$15/mo).
#   Re-run with --confirm-t3-small ONLY if you are on the newer credit-based
#   Free plan and accept the cost.
```

The guard lives in `anvil_ec2_provision` ([`lib/ec2.sh`](anvil/lib/ec2.sh)): if
`INSTANCE_TYPE=t3.small` and `--confirm-t3-small` was not passed, provisioning
aborts with the warning above. If the flag is passed, a `WARNING: t3.small
confirmed by operator. Estimated cost: ~$0.0208/hr (~$15/mo).` line is printed.
This gate is the project's defense against surprise AWS bills.

---

## Billing alarm

Two complementary ways to watch spend:

1. **One-line cost check** (already present):

   ```bash
   anvilctl billing            # alias
   anvilctl ec2 billing        # -> aws ce get-cost-and-usage (UnblendedCost, last month)
   ```

2. **CloudWatch billing alarm** (added in this final phase):

   ```bash
   anvilctl ec2 billing-alarm            # threshold 5 USD (default)
   anvilctl ec2 billing-alarm 10         # threshold 10 USD
   ANVIL_BILLING_ALARM_THRESHOLD=20 anvilctl ec2 billing-alarm
   ```

   This calls `anvil_ec2_billing_alarm` ([`lib/ec2.sh`](anvil/lib/ec2.sh)), which
   runs:

   ```bash
   aws cloudwatch put-metric-alarm \
     --alarm-name "anvil-billing-alarm" \
     --namespace AWS/Billing \
     --metric-name EstimatedCharges \
     --dimensions Name=Currency,Value=USD \
     --statistic Maximum --period 21600 \
     --threshold 5 \
     --comparison-operator GreaterThanThreshold \
     --evaluation-periods 1 --region us-east-1
   ```

   Notes:
   - The `AWS/Billing` metric namespace exists **only in `us-east-1`**, so the
     alarm is always created there regardless of `AWS_REGION`.
   - You must enable **"Receive Billing Alerts"** in the AWS Billing console
     before the alarm can fire.
   - The threshold (USD) is configurable via the first argument or
     `ANVIL_BILLING_ALARM_THRESHOLD` (default `5`); the alarm name via
     `ANVIL_BILLING_ALARM_NAME` (default `anvil-billing-alarm`).

---

## Security notes

Hard requirements enforced by the engine ([`lib/ec2.sh`](anvil/lib/ec2.sh) and
the compose files):

- **SSH (22)** is opened on the EC2 security group **from the caller's public IP
  only** (`/32`, auto-detected via `checkip.amazonaws.com` or `--ssh-cidr`).
  **Never `0.0.0.0/0`.**
- **HTTP/HTTPS (80/443)** are public (`0.0.0.0/0`) — that is the intended web
  surface.
- **RDS (3306)** is opened **from the EC2 security group id ONLY** (the EC2 host
  is the bastion). **Never `0.0.0.0/0`.** RDS is created with
  `--no-publicly-accessible` and lives in a private subnet group.
- **Web UI is never internet-facing** — it binds to `127.0.0.1` (loopback) on
  both local and EC2. On EC2 it is reached only through the SSH tunnel.
- **phpMyAdmin** is bound to `127.0.0.1:8080` in both stacks (local and EC2) —
  never public.
- **RDS credentials use SSM Parameter Store `SecureString`**, not Secrets
  Manager. Rationale: Secrets Manager begins billing after 30 days; SSM
  Parameter Store (Standard) is effectively free for this low-volume use. The
  php container reads them via the EC2 instance role
  (`anvil-ec2-role` / `anvil-ec2-profile`, minimal `ssm:GetParameter` on
  `/anvil/rds/*`).

---

## Known limitations / deferred

These are honest gaps carried over from Phases 1–4. **The full stack was not
executed against a live Docker or AWS environment in this build** — the code is
written and reviewed, but not runtime-validated here.

- **SSL cert bind-mount uses an absolute host path** in
  [`docker-compose.local.yml`](anvil/docker/docker-compose.local.yml): the
  `certs` volume is mounted at the literal
  `/home/dgi/app/DGLab/anvil/docker/nginx/certs` on both host and container so
  the `${SSL_CERT}`/`${SSL_KEY}` paths rendered by `lib/vhost.sh` resolve inside
  nginx. This is environment-specific and would need adjusting on a different
  machine.
- **shellcheck / phpcs / sass were not tool-verified in the build environment.**
  The bash edits in this final phase (`lib/ec2.sh`, `anvilctl`) are
  `bash -n` clean and introduced no new shellcheck warnings; however
  `lib/ec2.sh` carries two **pre-existing** shellcheck items that predate this
  phase (SC2016 info at the availability-zone query; SC2086 warning at the route-
  table query, line ~146) and were intentionally left untouched to avoid
  modifying committed Phase 4 logic.
- **`cloud-init.yaml` repo URL is a placeholder**
  (`https://github.com/example/anvil.git`) — replace it with the real repository
  (or bake the repo into a custom AMI and skip the clone).
- **Web UI "stop" also stops its own server.** Because the Web UI is served by
  the same process that handles the stop action, stopping the stack from the Web
  UI terminates the server that is serving the request (expected, but worth
  knowing).
- **RDS provision waits ~20 minutes.** `anvil_ec2_provision` polls for the RDS
  endpoint; a fresh `db.t3.micro` instance typically takes ~20 min to become
  available. Plan accordingly.
- **No automated end-to-end tests.** Validation is manual — see
  [`docs/VALIDATION.md`](anvil/docs/VALIDATION.md).
