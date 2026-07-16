#!/usr/bin/env bash
# shellcheck disable=SC1091
#
# anvil/provisioning/ec2-provision.sh
#
# Standalone entrypoint for Phase 4 EC2 + RDS provisioning. It is a thin wrapper
# around the shared engine in lib/ec2.sh (anvil_ec2_provision) so the real logic
# lives in one place. All AWS work, security-group rules, SSM secrets, the
# t3.small cost guard, and idempotent reuse-by-name live in lib/ec2.sh.
#
# Usage:
#   ./ec2-provision.sh [options]      (see `anvilctl ec2 help`)
#   anvilctl ec2 provision [options]

set -euo pipefail

ANVIL_ROOT="$(dirname "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")")"
export ANVIL_ROOT

# shellcheck source=../config/anvil.conf
source "${ANVIL_ROOT}/config/anvil.conf"
# shellcheck source=../lib/ec2.sh
source "${ANVIL_ROOT}/lib/ec2.sh"

anvil_ec2_provision "$@"
