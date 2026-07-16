# Anvil — End-to-End Validation Guide

This guide describes the **manual** validation flow for Anvil's local-development
path. It is provided because the stack **could not be executed against a live
Docker environment in the build where these docs were written** — the steps below
are the exact procedure a reviewer should follow on a real Kubuntu host to
confirm the engine works end to end.

> Scope: local dev only (`anvilctl start` → `new` → browse → `status`). The EC2
> path is covered by [`../README.md`](../README.md#ec2-mode) and the
> `anvilctl ec2` commands; it requires real AWS credentials and is out of scope
> for a local validation run.

---

## Prerequisites

A Debian/Ubuntu (Kubuntu) host with:

- **Docker Engine + Compose plugin** (`docker compose version` works).
- **mkcert** (local CA installed via `mkcert -install`).
- **dnsmasq** (configured for `*.test` → `127.0.0.1`; the `install.sh` installer
  sets this up and resolves the systemd-resolved stub-listener conflict).
- **inotify-tools** (`inotifywait` on PATH) for the vhost watcher.
- **dart-sass** (`sass` on PATH) for `build-assets`.
- **PHP 8.3 CLI** (`php -v`) for the Web UI skin.
- `dialog` or `whiptail` (only needed for `install.sh` / the TUI skin).

The fastest way to satisfy these is:

```bash
sudo ./install.sh      # picks components via a checklist; idempotent
```

Verify the `*.test` wildcard resolves before continuing:

```bash
dig +short demo.test @127.0.0.1     # expect: 127.0.0.1
```

---

## Manual validation flow

Run from the `anvil/` directory (or with `anvilctl` on PATH).

### 1. Bring the stack + Web UI up

```bash
anvilctl start
```

Expected:
- `docker compose -f docker/docker-compose.local.yml up -d` starts
  `nginx`, `php`, `mysql`, `phpmyadmin`, `redis`.
- The Web UI PHP server launches in the background, bound to
  `127.0.0.1:9999` (pid tracked in `.anvil-web.pid`).

### 2. Register a new project

```bash
anvilctl new demo
```

Expected (this is the core integration test):
- Creates the web root `www/demo` and writes `www/demo/.anvil-registered`.
- Issues a mkcert certificate into `docker/nginx/certs/demo/`
  (`demo.pem` + `demo-key.pem`) from the trusted local CA.
- Renders `docker/nginx/conf.d/demo.conf` from
  `docker/nginx/templates/vhost.conf.tpl` (via `envsubst`), with
  `server_name demo.test`, the cert paths, and `fastcgi_pass php:9000`.
- Reloads nginx so the new vhost is live.

### 3. Browse the project

Open a browser (on the same host) at:

```
https://demo.test
```

Expected:
- `demo.test` resolves to `127.0.0.1` via dnsmasq.
- TLS is **trusted** (mkcert CA is in the system/browser trust store) — no
  certificate warning.
- nginx terminates TLS and proxies PHP to `php:9000` (PHP-FPM). A project placed
  in `www/demo` (e.g. an `index.php`) is served.
- The HTTP→HTTPS redirect works (port 80 → 301 to `https://demo.test`).

### 4. (Optional) exercise the Web UI

Open `http://127.0.0.1:9999` — the SPA loads and its API calls
(`?route=api/status`, `?route=api/projects`, `?route=api/new`, …) shell out to
`anvilctl` and return JSON. The UI is loopback-only.

### 5. Check status

```bash
anvilctl status
```

Expected:
- `docker compose ps` shows the five containers `Up` (healthy where defined).
- A `--- Projects ---` section lists `demo` as `[registered]`.

### 6. (Optional) vhost watcher

```bash
anvilctl watch --once     # sync existing www/ dirs once
anvilctl watch            # loop: reacts to new/removed project dirs
```

Expected: creating `www/another` triggers cert + vhost generation and an nginx
reload; removing it purges the vhost + certs.

### 7. Tear down

```bash
anvilctl stop
```

Expected: Web UI server stopped (pidfile removed) and `docker compose down`
stops the stack.

---

## Success criteria

- [ ] `dig +short demo.test @127.0.0.1` → `127.0.0.1`.
- [ ] `anvilctl new demo` creates `www/demo`, certs, and `conf.d/demo.conf`
      without error.
- [ ] `https://demo.test` loads with a **trusted** certificate and is served by
      nginx → php-fpm:9000.
- [ ] `anvilctl status` shows the stack `Up` and `demo` registered.
- [ ] `anvilctl stop` cleanly stops the Web UI and the stack.

---

## Not executed here

This validation was **not run** in the documentation build environment. The
procedures above are derived directly from the engine code
([`lib/*.sh`](../../anvil/lib), [`bin/anvilctl`](../../anvil/bin/anvilctl),
[`docker/docker-compose.local.yml`](../../anvil/docker/docker-compose.local.yml),
[`docker/nginx/templates/vhost.conf.tpl`](../../anvil/docker/nginx/templates/vhost.conf.tpl))
and are the authoritative manual test plan for a reviewer with a live host.
