#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/lib/ssl.sh
#
# Manages the local mkcert Certificate Authority and per-project certificates.
# Both functions are idempotent and safe to re-run.
#
# Sourcing is safe: only definitions + a guarded config source.

set -euo pipefail

if [[ -z "${ANVIL_ROOT:-}" ]]; then
  # shellcheck source=../config/anvil.conf
  source "$(dirname "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")")/config/anvil.conf"
fi

# anvil_ssl_install
#   Installs the local mkcert CA once. `mkcert -install` is itself idempotent
#   (re-running is safe), so we simply ensure mkcert exists and call it.
anvil_ssl_install() {
  if ! command -v mkcert >/dev/null 2>&1; then
    echo "ERROR: mkcert not found on PATH; run install.sh first." >&2
    return 1
  fi
  mkcert -install
}

# anvil_ssl_project PROJECT
#   Issues (or re-uses) a per-project certificate pair into
#   CERTS_DIR/<project>/. Idempotent: skips if both files already exist.
anvil_ssl_project() {
  local project="${1:?Usage: anvil_ssl_project PROJECT}"

  local domain="${project}.${DOMAIN_TLD}"
  local cert_dir="${CERTS_DIR}/${project}"
  local cert_file="${cert_dir}/${project}.pem"
  local key_file="${cert_dir}/${project}-key.pem"

  if [[ -f "$cert_file" && -f "$key_file" ]]; then
    echo "SSL cert for ${project} already exists, skipping."
    return 0
  fi

  if ! command -v mkcert >/dev/null 2>&1; then
    echo "ERROR: mkcert not found on PATH; run install.sh first." >&2
    return 1
  fi

  # Ensure the CA is present before issuing.
  anvil_ssl_install || true

  mkdir -p "$cert_dir"
  ( cd "$cert_dir" && mkcert -cert-file "$cert_file" -key-file "$key_file" "$domain" "$project" )
  echo "SSL cert issued for ${project}: ${cert_file}"
}
