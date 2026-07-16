#!/usr/bin/env bash
# shellcheck disable=SC1091
#
# anvil/provisioning/certbot-setup.sh
#
# Obtain Let's Encrypt certificates via the webroot plugin on a real domain and
# render an nginx vhost (into the EC2 compose's conf.d) that terminates TLS with
# the live certs. Intended to run ON the EC2 host (after DNS points at the
# instance's public IP). Idempotent: certbot is naturally idempotent and the
# vhost is only (re)rendered once the certificate actually exists.
#
# Security: the Web UI and phpMyAdmin are NOT touched here — they stay on
# loopback and are reached only through the SSH tunnel. Only 80/443 are public.
#
# Usage:
#   ./certbot-setup.sh --domain example.com --email you@example.com
#   ./certbot-setup.sh --domain example.com --domain www.example.com --email ...

set -euo pipefail

ANVIL_ROOT="$(dirname "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")")"
export ANVIL_ROOT

# shellcheck source=../config/anvil.conf
source "${ANVIL_ROOT}/config/anvil.conf"

domains=()
email="${CERTBOT_EMAIL:-}"
# Default webroot = the host directory the EC2 compose mounts as ./www
# (i.e. anvil/docker/www, which nginx serves as /var/www).
webroot="$(dirname "${ANVIL_ROOT}/${EC2_COMPOSE_FILE}")/www"
dry_run=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --domain)  domains+=("${2:?--domain requires a value}"); shift 2 ;;
    --email)   email="${2:?--email requires a value}"; shift 2 ;;
    --webroot) webroot="${2:?--webroot requires a value}"; shift 2 ;;
    --dry-run) dry_run=1; shift ;;
    -h|--help)
      echo "Usage: certbot-setup.sh --domain example.com [--domain www.example.com] --email you@example.com [--webroot DIR] [--dry-run]"
      exit 0 ;;
    *) echo "Unknown option: $1" >&2; exit 1 ;;
  esac
done

# Fall back to CERTBOT_DOMAINS from config (space or comma separated).
if [[ ${#domains[@]} -eq 0 ]]; then
  IFS=', ' read -r -a domains <<< "${CERTBOT_DOMAINS:-}"
fi

# Drop empty elements (e.g. when CERTBOT_DOMAINS is unset).
nonempty=()
for d in "${domains[@]:-}"; do
  [[ -n "$d" ]] && nonempty+=("$d")
done
domains=("${nonempty[@]}")

if [[ ${#domains[@]} -eq 0 ]]; then
  echo "ERROR: no domains given. Pass --domain or set CERTBOT_DOMAINS." >&2
  exit 1
fi
if [[ -z "$email" ]]; then
  echo "ERROR: no email given. Pass --email or set CERTBOT_EMAIL." >&2
  exit 1
fi

command -v certbot >/dev/null 2>&1 || { echo "ERROR: 'certbot' not found (install certbot first)." >&2; exit 1; }
command -v docker >/dev/null 2>&1 || { echo "ERROR: 'docker' not found." >&2; exit 1; }

primary="${domains[0]}"
cert_dir="/etc/letsencrypt/live/${primary}"
fullchain="${cert_dir}/fullchain.pem"
privkey="${cert_dir}/privkey.pem"

# Build the -d arguments.
domain_args=()
for d in "${domains[@]}"; do
  domain_args+=(-d "$d")
done

certbot_cmd=(certbot certonly --webroot -w "$webroot" "${domain_args[@]}" \
  --email "$email" --agree-tos --non-interactive --expand)
if [[ "$dry_run" -eq 1 ]]; then
  certbot_cmd+=(--dry-run)
fi

echo "Obtaining Let's Encrypt certificate for: ${domains[*]}"
"${certbot_cmd[@]}"

# Idempotent re-render guard: only render once the cert actually exists.
if [[ ! -f "$fullchain" ]]; then
  echo "ERROR: expected certificate not found at ${fullchain}." >&2
  exit 1
fi

conf_dir="${ANVIL_ROOT}/docker/nginx/conf.d"
mkdir -p "$conf_dir"
vhost_file="${conf_dir}/${primary}.conf"

# Render the production vhost. Placeholders are substituted with sed so the
# nginx $ variables are NOT expanded by bash.
tmp_tpl="$(mktemp)"
cat > "$tmp_tpl" <<'TPL'
server {
    listen 80;
    listen [::]:80;
    server_name __DOMAINS__;

    # ACME http-01 challenges (served from the webroot; everything else -> HTTPS).
    location /.well-known/acme-challenge {
        root /var/www;
    }
    location / {
        return 301 https://$host$request_uri;
    }
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;

    server_name __DOMAINS__;

    ssl_certificate     __FULLCHAIN__;
    ssl_certificate_key __PRIVKEY__;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;

    root /var/www;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.ht {
        deny all;
    }
}
TPL

# Compose the space-separated server_name list.
server_names="${domains[*]}"

sed -e "s|__DOMAINS__|${server_names}|g" \
    -e "s|__FULLCHAIN__|${fullchain}|g" \
    -e "s|__PRIVKEY__|${privkey}|g" \
    "$tmp_tpl" > "$vhost_file"
rm -f "$tmp_tpl"

echo "Rendered vhost -> ${vhost_file}"

# Reload nginx (running in the anvil_nginx container).
if docker ps --format '{{.Names}}' | grep -q '^anvil_nginx$'; then
  docker exec anvil_nginx nginx -s reload
  echo "Reloaded nginx."
else
  echo "NOTE: anvil_nginx container not running; start the stack then reload nginx manually."
fi
