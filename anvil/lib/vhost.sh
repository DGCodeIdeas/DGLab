#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/lib/vhost.sh
#
# Generates an nginx vhost config for a project from a template using
# `envsubst`, then reloads nginx. Idempotent: skips when a marker exists and
# the template has not changed since the marker was written.
#
# Sourcing is safe: only definitions + a guarded config source.

set -euo pipefail

if [[ -z "${ANVIL_ROOT:-}" ]]; then
  # shellcheck source=../config/anvil.conf
  source "$(dirname "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")")/config/anvil.conf"
fi

# anvil_vhost_generate PROJECT
#   PROJECT  : project name; the domain becomes <project>.<DOMAIN_TLD>.
anvil_vhost_generate() {
  local project="${1:?Usage: anvil_vhost_generate PROJECT}"

  local domain="${project}.${DOMAIN_TLD}"
  local cert_dir="${CERTS_DIR}/${project}"
  local ssl_cert="${cert_dir}/${project}.pem"
  local ssl_key="${cert_dir}/${project}-key.pem"
  local project_root="${WWW_DIR}/${project}"
  local out_file="${NGINX_CONFD_DIR}/${project}.conf"
  local marker="${out_file}.anvil"
  local tpl="${NGINX_TEMPLATES_DIR}/vhost.conf.tpl"

  # Idempotency: skip if the marker exists AND the template is unchanged.
  if [[ -f "$marker" && "$tpl" -ot "$marker" ]]; then
    echo "vhost for ${project} is up to date, skipping."
    return 0
  fi

  if [[ ! -f "$tpl" ]]; then
    echo "ERROR: vhost template not found at ${tpl}" >&2
    return 1
  fi

  mkdir -p "$NGINX_CONFD_DIR"

  # Substitute only the variables the template is allowed to use.
  # Run in a subshell so the exported vars do not leak into the caller's env.
  (
    export PROJECT="$project" DOMAIN="$domain" SSL_CERT="$ssl_cert" \
      SSL_KEY="$ssl_key" PROJECT_ROOT="$project_root"
    # shellcheck disable=SC2016
    envsubst '${PROJECT} ${DOMAIN} ${SSL_CERT} ${SSL_KEY} ${PROJECT_ROOT}' \
      < "$tpl" > "$out_file"
  )

  # Reload nginx if the stack is running; ignore failure (e.g. not started yet).
  docker compose -f "$ANVIL_COMPOSE_FILE" exec -T nginx nginx -s reload 2>/dev/null \
    || docker kill -s HUP "$(docker compose -f "$ANVIL_COMPOSE_FILE" ps -q nginx 2>/dev/null)" 2>/dev/null \
    || true

  touch "$marker"
  echo "vhost generated: ${out_file}"
}

# anvil_vhost_reload
#   Gracefully reload nginx inside the running container so new/removed vhost
#   configs take effect without a full restart. No-op (and never fatal) when the
#   stack or the nginx container is not running yet.
anvil_vhost_reload() {
  if docker compose -f "$ANVIL_COMPOSE_FILE" ps -q nginx 2>/dev/null | grep -q .; then
    docker compose -f "$ANVIL_COMPOSE_FILE" exec -T nginx nginx -s reload 2>/dev/null || true
  else
    echo "nginx container not running; skipping reload."
  fi
}

# anvil_vhost_remove PROJECT
#   Removes the generated vhost config + its idempotency marker and deletes the
#   per-project SSL certificate directory. Idempotent: safe to call when nothing
#   exists. Does NOT fail if files are already gone.
anvil_vhost_remove() {
  local project="${1:?Usage: anvil_vhost_remove PROJECT}"

  local out_file="${NGINX_CONFD_DIR}/${project}.conf"
  local marker="${out_file}.anvil"
  local cert_dir="${CERTS_DIR}/${project}"

  rm -f "$out_file" "$marker"
  rm -rf "$cert_dir"
  echo "vhost removed for ${project} (config + certs purged)."
}
