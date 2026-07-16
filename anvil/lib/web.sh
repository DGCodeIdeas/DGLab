#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/lib/web.sh
#
# Manages the optional Anvil Web UI skin: a PHP built-in server that serves
# anvil/web/public (the SPA + API front controller). The server is ALWAYS
# bound to WEB_UI_HOST (default 127.0.0.1 / loopback) — never 0.0.0.0 — so the
# UI is only reachable from the local machine. This is a hard requirement for
# the later EC2 security model.
#
# anvil_web_up   : idempotently launch the server in the background (PID file).
# anvil_web_down : idempotently stop it (no-op if not running).
#
# Sourcing is safe: only definitions + a guarded config source. No side effects
# on source.

set -euo pipefail

if [[ -z "${ANVIL_ROOT:-}" ]]; then
  # shellcheck source=../config/anvil.conf
  source "$(dirname "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")")/config/anvil.conf"
fi

# Absolute path to the Web UI document root (the SPA + API front controller).
: "${ANVIL_WEB_DOCROOT:=${ANVIL_ROOT}/web/public}"
# PID file tracking the background PHP server process.
: "${ANVIL_WEB_PIDFILE:=${ANVIL_ROOT}/.anvil-web.pid}"
# Log file for the background PHP server's stdout/stderr.
: "${ANVIL_WEB_LOGFILE:=${ANVIL_ROOT}/.anvil-web.log}"

export ANVIL_WEB_DOCROOT ANVIL_WEB_PIDFILE ANVIL_WEB_LOGFILE

# _anvil_web_is_running
#   Returns 0 (and echoes the pid) if a live server is tracked by the pidfile.
_anvil_web_is_running() {
  [[ -f "$ANVIL_WEB_PIDFILE" ]] || return 1
  local pid
  pid="$(cat "$ANVIL_WEB_PIDFILE" 2>/dev/null || echo "")"
  [[ -n "$pid" ]] || return 1
  kill -0 "$pid" 2>/dev/null
}

# anvil_web_up
#   Launch the PHP built-in server bound strictly to WEB_UI_HOST:WEB_UI_PORT,
#   serving ANVIL_WEB_DOCROOT and routing all requests through index.php.
#   Idempotent: returns early (success) if the server is already running.
anvil_web_up() {
  if _anvil_web_is_running; then
    local pid
    pid="$(cat "$ANVIL_WEB_PIDFILE" 2>/dev/null || echo "")"
    echo "Web UI already running (pid ${pid}) on http://${WEB_UI_HOST}:${WEB_UI_PORT}"
    return 0
  fi

  # A stale pidfile (process gone) must not block a fresh start.
  [[ -f "$ANVIL_WEB_PIDFILE" ]] && rm -f "$ANVIL_WEB_PIDFILE"

  if ! command -v php >/dev/null 2>&1; then
    echo "ERROR: 'php' (PHP 8.3 CLI) not found on PATH; cannot start Web UI." >&2
    echo "       Install PHP CLI, then run 'anvilctl start' again." >&2
    return 1
  fi

  if [[ ! -f "${ANVIL_WEB_DOCROOT}/index.php" ]]; then
    echo "ERROR: Web UI front controller not found at ${ANVIL_WEB_DOCROOT}/index.php" >&2
    return 1
  fi

  # Bind strictly to loopback. The router script (index.php) ensures every
  # request — including static assets that do not exist — is handled by the
  # front controller, while real static files (app.js, style.css) are served
  # directly by the built-in server.
  nohup php -S "${WEB_UI_HOST}:${WEB_UI_PORT}" \
    -t "$ANVIL_WEB_DOCROOT" "$ANVIL_WEB_DOCROOT/index.php" \
    > "$ANVIL_WEB_LOGFILE" 2>&1 &

  local pid
  pid="$!"
  echo "$pid" > "$ANVIL_WEB_PIDFILE"

  # Give the server a moment; verify it actually bound before declaring success.
  local i
  for i in 1 2 3 4 5; do
    if kill -0 "$pid" 2>/dev/null; then
      echo "Web UI started (pid ${pid}) on http://${WEB_UI_HOST}:${WEB_UI_PORT}"
      return 0
    fi
    sleep 0.2
  done

  echo "ERROR: Web UI process exited immediately; see ${ANVIL_WEB_LOGFILE}" >&2
  rm -f "$ANVIL_WEB_PIDFILE"
  return 1
}

# anvil_web_down
#   Stop the background PHP server tracked by the pidfile. Idempotent: a
#   missing pidfile (or an already-dead process) is treated as success.
anvil_web_down() {
  if [[ ! -f "$ANVIL_WEB_PIDFILE" ]]; then
    echo "Web UI not running (no pidfile); nothing to do."
    return 0
  fi

  local pid
  pid="$(cat "$ANVIL_WEB_PIDFILE" 2>/dev/null || echo "")"

  if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
    kill "$pid" 2>/dev/null || true
    # Wait briefly for a graceful exit before forcing it.
    local i
    for i in 1 2 3 4 5 6 7 8 9 10; do
      kill -0 "$pid" 2>/dev/null || break
      sleep 0.2
    done
    kill -9 "$pid" 2>/dev/null || true
  fi

  rm -f "$ANVIL_WEB_PIDFILE"
  echo "Web UI stopped."
}
