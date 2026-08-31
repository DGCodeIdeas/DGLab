# SESSION_STATE.md

Tracks the Anvil project across all phases. The working tree is **clean**: every
phase (1–4) and the final documentation phase are committed. This file is the
project checkpoint.

---

## Built

### Phase 1 — Engine foundation + installer
- **Key files:** [`anvil/install.sh`](anvil/install.sh),
  [`anvil/config/anvil.conf`](anvil/config/anvil.conf),
  [`anvil/lib/docker.sh`](anvil/lib/docker.sh),
  [`anvil/lib/vhost.sh`](anvil/lib/vhost.sh),
  [`anvil/lib/ssl.sh`](anvil/lib/ssl.sh),
  [`anvil/lib/project.sh`](anvil/lib/project.sh),
  [`anvil/lib/db.sh`](anvil/lib/db.sh),
  [`anvil/lib/web.sh`](anvil/lib/web.sh).
- **What it does:** idempotent `dialog`/`whiptail` installer (Docker + Compose,
  dnsmasq `*.test` with systemd-resolved handling, mkcert CA, dart-sass,
  inotify-tools); the shared bash engine that all front-ends call.
- **Commit:** Phase 1 committed (engine + installer).

### Phase 2 — Compose stack + vhost/ssl engine
- **Key files:** [`anvil/docker/docker-compose.local.yml`](anvil/docker/docker-compose.local.yml),
  [`anvil/docker/nginx/templates/vhost.conf.tpl`](anvil/docker/nginx/templates/vhost.conf.tpl),
  [`anvil/scripts/vhost-watcher.sh`](anvil/scripts/vhost-watcher.sh).
- **What it does:** nginx (TLS + PHP-FPM) / php 8.3 FPM / mysql 8.0 /
  phpmyadmin (loopback) / redis stack; envsubst vhost rendering + nginx reload;
  mkcert per-project certs; inotify vhost watcher.
- **Commit:** Phase 2 committed (compose stack + TUI/Web UI).

### Phase 3 — TUI + Web UI
- **Key files:** [`anvil/tui/anvil-tui.sh`](anvil/tui/anvil-tui.sh),
  [`anvil/web/public/index.php`](anvil/web/public/index.php),
  [`anvil/web/src/Api/Router.php`](anvil/web/src/Api/Router.php),
  [`anvil/web/src/Api/Api.php`](anvil/web/src/Api/Api.php),
  [`anvil/web/src/Api/AnvilClient.php`](anvil/web/src/Api/AnvilClient.php),
  [`anvil/web/src/Api/AnvilResult.php`](anvil/web/src/Api/AnvilResult.php),
  [`anvil/web/scss/app.scss`](anvil/web/scss/app.scss).
- **What it does:** two thin skins over the engine — a `dialog`/`whiptail` menu
  and a PHP SPA + JSON API (loopback `127.0.0.1:9999`) that shells out to
  `anvilctl` via `AnvilClient`.
- **Commit:** Phase 3 committed (TUI/Web UI).

### Phase 4 — EC2 + RDS
- **Key files:** [`anvil/lib/ec2.sh`](anvil/lib/ec2.sh),
  [`anvil/docker/docker-compose.ec2.yml`](anvil/docker/docker-compose.ec2.yml),
  [`anvil/docker/php/entrypoint-ssm.sh`](anvil/docker/php/entrypoint-ssm.sh),
  [`anvil/provisioning/ec2-provision.sh`](anvil/provisioning/ec2-provision.sh),
  [`anvil/provisioning/rds-tunnel.sh`](anvil/provisioning/rds-tunnel.sh),
  [`anvil/provisioning/certbot-setup.sh`](anvil/provisioning/certbot-setup.sh),
  [`anvil/provisioning/cloud-init.yaml`](anvil/provisioning/cloud-init.yaml).
- **What it does:** idempotent VPC/SG/key/RDS/EC2 provisioning; RDS creds in SSM
  SecureString; bastion tunnel to private RDS; Let's Encrypt via certbot;
  `t3.small` cost guard behind `--confirm-t3-small`; loopback Web UI on EC2.
- **Commit:** Phase 4 committed (EC2/RDS).

### Final phase — Documentation + billing alarm
- **Key files (new):** [`anvil/README.md`](anvil/README.md),
  [`anvil/docs/VALIDATION.md`](anvil/docs/VALIDATION.md),
  `SESSION_STATE.md` (this file, repo root).
- **Key files (modified):** [`anvil/lib/ec2.sh`](anvil/lib/ec2.sh) (added
  `anvil_ec2_billing_alarm`), [`anvil/bin/anvilctl`](anvil/bin/anvilctl)
  (wired `ec2 billing-alarm` + usage/help text).
- **What it does:** project README (architecture, usage, EC2, the account-date
  cost correction, billing alarm, security, known limits); manual end-to-end
  validation guide; CloudWatch billing-alarm command.
- **Commit:** `docs(anvil): README, SESSION_STATE, billing alarm, validation guide`.

---

## Deferred / Known Issues

Carried over from prior phases (also listed in
[`anvil/README.md`](anvil/README.md#known-limitations--deferred)):

1. **SSL cert bind-mount uses an absolute host path** in
   `docker-compose.local.yml` (`/home/dgi/app/DGLab/anvil/docker/nginx/certs`),
   so the rendered `${SSL_CERT}`/`${SSL_KEY}` paths resolve inside nginx.
   Environment-specific; adjust on a different machine.
2. **shellcheck / phpcs / sass not tool-verified in the build environment.** The
   final-phase bash edits are `bash -n` clean and introduced no new shellcheck
   warnings, but `lib/ec2.sh` retains two **pre-existing** shellcheck items from
   Phase 4 (SC2016 info at the availability-zone query; SC2086 warning at the
   route-table query, ~line 146). Left untouched to avoid modifying committed
   Phase 4 logic.
3. **`cloud-init.yaml` repo URL is a placeholder**
   (`https://github.com/example/anvil.git`); replace with the real repo (or bake
   into a custom AMI).
4. **Web UI "stop" also stops its own server** (the server handling the stop
   request terminates itself). Expected behaviour, documented.
5. **RDS provision waits ~20 minutes** for the instance endpoint to become
   available (polling in `anvil_ec2_provision`).
6. **No automated end-to-end tests** — validation is manual
   ([`anvil/docs/VALIDATION.md`](anvil/docs/VALIDATION.md)).

### Honesty note
The full Anvil stack was **not executed against a live Docker or AWS
environment** in this build. All code is written and reviewed against the actual
artifacts, but runtime validation (container bring-up, real AWS provisioning)
has not been performed here.

---

## Working tree status

Clean. All phases (1–4) and the final documentation phase are committed. The
only uncommitted changes at the time of writing were the final-phase files
(`anvil/README.md`, `anvil/docs/VALIDATION.md`, `SESSION_STATE.md`, and the
`lib/ec2.sh` + `anvilctl` billing-alarm edits), which are committed together as
the final checkpoint.
