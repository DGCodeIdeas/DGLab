#!/usr/bin/env bash
# shellcheck disable=SC1091
#
# entrypoint-ssm.sh — EC2 php container entrypoint (Phase 4).
#
# Fetches RDS MySQL credentials from AWS SSM Parameter Store (SecureString) and
# exports them as DB_HOST / DB_USER / DB_PASSWORD / DB_NAME (plus MYSQL_* aliases)
# before handing off to php-fpm (or the CMD passed by the image / compose file).
#
# The container MUST run with an IAM instance/profile role that is allowed to
# call ssm:GetParameter on the /anvil/rds/* parameters. We deliberately use SSM
# Parameter Store (not Secrets Manager) to avoid the 30-day Secrets Manager fee.

set -euo pipefail

SSM_REGION="${AWS_REGION:-us-east-1}"
SSM_HOST_PARAM="${ANVIL_SSM_RDS_HOST:-/anvil/rds/host}"
SSM_USER_PARAM="${ANVIL_SSM_RDS_USER:-/anvil/rds/user}"
SSM_PASSWORD_PARAM="${ANVIL_SSM_RDS_PASSWORD:-/anvil/rds/password}"
SSM_DATABASE_PARAM="${ANVIL_SSM_RDS_DATABASE:-/anvil/rds/database}"

# --- guard: aws cli present ---
if ! command -v aws >/dev/null 2>&1; then
  echo "ERROR: 'aws' CLI not found in PATH. The SSM entrypoint requires the" >&2
  echo "       AWS CLI v2. Rebuild the php image from docker/php/Dockerfile." >&2
  exit 1
fi

# fetch_param NAME PATH
#   Prints the decrypted parameter value, or exits with a clear error.
fetch_param() {
  local name="$1" param_path="$2" value
  if ! value="$(aws ssm get-parameter \
        --region "$SSM_REGION" \
        --name "$param_path" \
        --with-decryption \
        --query Parameter.Value \
        --output text 2>/dev/null)"; then
    echo "ERROR: failed to read SSM parameter '${param_path}' (${name})." >&2
    echo "       Ensure the parameter exists and the instance role allows" >&2
    echo "       ssm:GetParameter on it." >&2
    exit 1
  fi
  if [[ -z "$value" || "$value" == "None" ]]; then
    echo "ERROR: SSM parameter '${param_path}' is empty." >&2
    exit 1
  fi
  printf '%s' "$value"
}

DB_HOST="$(fetch_param host "$SSM_HOST_PARAM")"
DB_USER="$(fetch_param user "$SSM_USER_PARAM")"
DB_PASSWORD="$(fetch_param password "$SSM_PASSWORD_PARAM")"
DB_NAME="$(fetch_param database "$SSM_DATABASE_PARAM")"

export DB_HOST DB_USER DB_PASSWORD DB_NAME
# MySQL-client style aliases (used by some apps / the phpmyadmin bridge).
export MYSQL_HOST="$DB_HOST" MYSQL_USER="$DB_USER" \
       MYSQL_PASSWORD="$DB_PASSWORD" MYSQL_DATABASE="$DB_NAME"

echo "entrypoint-ssm: resolved RDS credentials from SSM (host=${DB_HOST}, db=${DB_NAME})."

# Hand off to php-fpm, or to whatever CMD the image / compose file supplied.
if [[ $# -eq 0 ]]; then
  exec php-fpm
else
  exec "$@"
fi
