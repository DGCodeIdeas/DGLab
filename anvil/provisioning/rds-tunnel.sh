#!/usr/bin/env bash
# shellcheck disable=SC1091
#
# anvil/provisioning/rds-tunnel.sh
#
# Developer bastion tunnel: forwards a local port to the PRIVATE RDS instance
# through the EC2 host (which is the only thing allowed to reach 3306). RDS is
# NEVER public — there is no 0.0.0.0/0 rule on 3306 anywhere.
#
# Thin wrapper around the shared engine in lib/ec2.sh (anvil_ec2_tunnel).
#
# Usage:
#   ./rds-tunnel.sh --rds-endpoint <host> --host <ec2-ip> --key <key.pem>
#   anvilctl ec2 tunnel --rds-endpoint <host> --host <ec2-ip> --key <key.pem>

set -euo pipefail

ANVIL_ROOT="$(dirname "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")")"
export ANVIL_ROOT

# shellcheck source=../config/anvil.conf
source "${ANVIL_ROOT}/config/anvil.conf"
# shellcheck source=../lib/ec2.sh
source "${ANVIL_ROOT}/lib/ec2.sh"

anvil_ec2_tunnel "$@"
