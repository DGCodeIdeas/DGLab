#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/lib/ec2.sh
#
# STUB for Phase 4 (production provisioning to AWS EC2 + RDS MySQL).
# Present now only so the lib can be sourced and the dispatcher can delegate
# an `ec2` subcommand. The instance type is read from config (INSTANCE_TYPE),
# defaulting to the free-tier-safe t3.micro.
#
# Sourcing is safe: only definitions + a guarded config source.

set -euo pipefail

if [[ -z "${ANVIL_ROOT:-}" ]]; then
  # shellcheck source=../config/anvil.conf
  source "$(dirname "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")")/config/anvil.conf"
fi

# anvil_ec2_help
#   Prints current EC2-related configuration and explains the stub status.
anvil_ec2_help() {
  cat <<'EOF'
Anvil EC2 provisioning is not implemented in Phase 1.

Planned (Phase 4):
  - Provision the local Docker stack to an AWS EC2 instance.
  - Connect the stack to an RDS MySQL instance.
  - Default instance type (free-tier safe): INSTANCE_TYPE=t3.micro
    (t3.small is NOT free-tier eligible on accounts before 2025-07-15 and
     requires explicit user confirmation of the newer credit-based Free plan.)

Current configuration:
EOF
  echo "  INSTANCE_TYPE=${INSTANCE_TYPE:-t3.micro}"
  echo "  WEB_UI_HOST=${WEB_UI_HOST:-127.0.0.1}"
  echo "  WEB_UI_PORT=${WEB_UI_PORT:-9999}"
}
