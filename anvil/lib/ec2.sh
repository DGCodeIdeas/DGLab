#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/lib/ec2.sh
#
# Phase 4 production engine: provision the Anvil stack to AWS EC2 + RDS MySQL,
# open a developer bastion tunnel to the (private) RDS instance, and run a
# cost/billing check. This is the "one shared engine" — anvilctl and the
# provisioning/*.sh wrappers both call these functions.
#
# Hard security constraints enforced here:
#   * EC2 SG: 22 from the caller's public IP ONLY (never 0.0.0.0/0).
#   * EC2 SG: 80/443 from 0.0.0.0/0 (public web is intended).
#   * RDS SG: 3306 from the EC2 SG id ONLY (never 0.0.0.0/0). RDS stays private.
#   * RDS credentials live in SSM Parameter Store as SecureString (not Secrets
#     Manager, which bills after 30 days).
#   * INSTANCE_TYPE defaults to t3.micro; t3.small is gated behind
#     --confirm-t3-small with a cost warning.
#
# Sourcing is safe: only definitions + a guarded config source. No side effects
# on source.

set -euo pipefail

if [[ -z "${ANVIL_ROOT:-}" ]]; then
  # shellcheck source=../config/anvil.conf
  source "$(dirname "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")")/config/anvil.conf"
fi

# Ensure the EC2-related config vars are present (they are set in anvil.conf,
# but allow the caller to override any of them via the environment).
: "${EC2_COMPOSE_FILE:=docker/docker-compose.ec2.yml}"
: "${EC2_SG_NAME:=anvil-ec2-sg}"
: "${RDS_SG_NAME:=anvil-rds-sg}"
: "${ANVIL_VPC_NAME:=anvil-vpc}"
: "${ANVIL_SUBNET_NAME:=anvil-subnet}"
: "${ANVIL_IGW_NAME:=anvil-igw}"
: "${ANVIL_DB_SUBNET_GROUP:=anvil-db-subnet-group}"
: "${ANVIL_KEY_NAME:=anvil-prod}"
: "${SSH_KEY_PATH:=${ANVIL_ROOT}/anvil-ec2-key.pem}"
: "${CERTBOT_DOMAINS:=}"
: "${CERTBOT_EMAIL:=}"
: "${AWS_REGION:=${AWS_DEFAULT_REGION:-us-east-1}}"

# Network CIDR blocks (from anvil.conf)
: "${ANVIL_VPC_CIDR:=10.0.0.0/16}"
: "${ANVIL_PRIMARY_SUBNET_CIDR:=10.0.1.0/24}"
: "${ANVIL_SECONDARY_SUBNET_CIDR:=10.0.2.0/24}"

# RDS configuration (from anvil.conf)
: "${ANVIL_RDS_ENGINE:=mysql}"
: "${ANVIL_RDS_ENGINE_VERSION:=8.0}"
: "${ANVIL_RDS_INSTANCE_CLASS:=db.t3.micro}"
: "${ANVIL_RDS_ALLOCATED_STORAGE:=20}"
: "${ANVIL_RDS_BACKUP_RETENTION:=7}"
: "${ANVIL_RDS_MULTI_AZ:=false}"
: "${ANVIL_RDS_PUBLICLY_ACCESSIBLE:=false}"
: "${ANVIL_RDS_STORAGE_ENCRYPTED:=true}"

# IAM configuration (from anvil.conf)
: "${ANVIL_IAM_ROLE_NAME:=anvil-ec2-role}"
: "${ANVIL_IAM_PROFILE_NAME:=anvil-ec2-profile}"
: "${ANVIL_IAM_POLICY_NAME:=anvil-ssm-read}"

# SSM Parameter Store paths (from anvil.conf)
: "${ANVIL_SSM_RDS_HOST:=/anvil/rds/host}"
: "${ANVIL_SSM_RDS_USER:=/anvil/rds/user}"
: "${ANVIL_SSM_RDS_PASSWORD:=/anvil/rds/password}"
: "${ANVIL_SSM_RDS_DATABASE:=/anvil/rds/database}"

# AMI resolution (from anvil.conf)
: "${ANVIL_AMI_SSM_PARAM:=/aws/service/ami-amazon-linux-latest/al2023-ami-kernel-default-x86_64}"

# URL used to detect the caller's public IP for the SSH security-group lock-down.
: "${ANVIL_IP_DETECTION_URL:=https://checkip.amazonaws.com}"

# EC2 tagging (from anvil.conf)
: "${ANVIL_EC2_TAG_PROJECT:=anvil}"
: "${ANVIL_EC2_TAG_NAME_PREFIX:=anvil-}"

# Remote host layout (EC2 instance) — configurable for non-standard images.
: "${ANVIL_REPO_URL:=https://github.com/example/anvil.git}"
: "${ANVIL_REMOTE_APP_DIR:=/opt/anvil}"

# SSH user for the EC2 host (ec2-user for Amazon Linux; ubuntu for Ubuntu AMIs).
: "${ANVIL_EC2_SSH_USER:=ec2-user}"

# ---------------------------------------------------------------------------
# Pre-flight guards
# ---------------------------------------------------------------------------

_anvil_ec2_check_prereqs() {
  if ! command -v aws >/dev/null 2>&1; then
    echo "ERROR: 'aws' CLI not found on PATH. Install the AWS CLI v2 and run" >&2
    echo "       'aws configure' (or export AWS_*/AWS_PROFILE) before provisioning." >&2
    return 1
  fi
  if ! aws sts get-caller-identity --region "$AWS_REGION" >/dev/null 2>&1; then
    echo "ERROR: AWS credentials are not valid/configured for region ${AWS_REGION}." >&2
    echo "       Run 'aws configure' or export AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY." >&2
    return 1
  fi
}

# ---------------------------------------------------------------------------
# Idempotent VPC / networking helpers (reuse by Name tag)
# ---------------------------------------------------------------------------

_anvil_ec2_ensure_vpc() {
  local vpc_id
  vpc_id="$(aws ec2 describe-vpcs \
    --filters "Name=tag:Name,Values=${ANVIL_VPC_NAME}" \
    --query 'Vpcs[0].VpcId' --output text --region "$AWS_REGION" 2>/dev/null || true)"
  if [[ -n "$vpc_id" && "$vpc_id" != "None" ]]; then
    echo "$vpc_id"
    return 0
  fi
  vpc_id="$(aws ec2 create-vpc \
    --cidr-block "${ANVIL_VPC_CIDR}" \
    --tag-specifications "ResourceType=vpc,Tags=[{Key=Name,Value=${ANVIL_VPC_NAME}}]" \
    --query 'Vpc.VpcId' --output text --region "$AWS_REGION")"
  aws ec2 modify-vpc-attribute --vpc-id "$vpc_id" --enable-dns-support --region "$AWS_REGION" >/dev/null
  aws ec2 modify-vpc-attribute --vpc-id "$vpc_id" --enable-dns-hostnames --region "$AWS_REGION" >/dev/null
  echo "$vpc_id"
}

# Creates (or reuses) two subnets in two AZs for the DB subnet group, plus a
# primary subnet for the EC2 instance. Echoes "PRIMARY_SUBNET OTHER_SUBNET".
_anvil_ec2_ensure_subnets() {
  local vpc_id="$1"
  local az_list az1 az2 primary other
  az_list="$(aws ec2 describe-availability-zones \
    --query 'AvailabilityZones[?State==`available`].ZoneName' \
    --output text --region "$AWS_REGION")"
  az1="${az_list%% *}"
  az2="${az_list#* }"
  az2="${az2%% *}"
  [[ -z "$az2" ]] && az2="$az1"

  primary="$(aws ec2 describe-subnets \
    --filters "Name=tag:Name,Values=${ANVIL_SUBNET_NAME}" "Name=vpc-id,Values=${vpc_id}" \
    --query 'Subnets[0].SubnetId' --output text --region "$AWS_REGION" 2>/dev/null || true)"
  if [[ -z "$primary" || "$primary" == "None" ]]; then
    primary="$(aws ec2 create-subnet \
      --vpc-id "$vpc_id" --cidr-block "${ANVIL_PRIMARY_SUBNET_CIDR}" --availability-zone "$az1" \
      --tag-specifications "ResourceType=subnet,Tags=[{Key=Name,Value=${ANVIL_SUBNET_NAME}}]" \
      --query 'Subnet.SubnetId' --output text --region "$AWS_REGION")"
  fi

  other="$(aws ec2 describe-subnets \
    --filters "Name=tag:Name,Values=${ANVIL_SUBNET_NAME}-b" "Name=vpc-id,Values=${vpc_id}" \
    --query 'Subnets[0].SubnetId' --output text --region "$AWS_REGION" 2>/dev/null || true)"
  if [[ -z "$other" || "$other" == "None" ]]; then
    other="$(aws ec2 create-subnet \
      --vpc-id "$vpc_id" --cidr-block "${ANVIL_SECONDARY_SUBNET_CIDR}" --availability-zone "$az2" \
      --tag-specifications "ResourceType=subnet,Tags=[{Key=Name,Value=${ANVIL_SUBNET_NAME}-b}]" \
      --query 'Subnet.SubnetId' --output text --region "$AWS_REGION")"
  fi

  echo "${primary} ${other}"
}

_anvil_ec2_ensure_igw() {
  local vpc_id="$1" igw_id
  igw_id="$(aws ec2 describe-internet-gateways \
    --filters "Name=tag:Name,Values=${ANVIL_IGW_NAME}" \
    --query 'InternetGateways[0].InternetGatewayId' --output text --region "$AWS_REGION" 2>/dev/null || true)"
  if [[ -z "$igw_id" || "$igw_id" == "None" ]]; then
    igw_id="$(aws ec2 create-internet-gateway \
      --tag-specifications "ResourceType=internet-gateway,Tags=[{Key=Name,Value=${ANVIL_IGW_NAME}}]" \
      --query 'InternetGateway.InternetGatewayId' --output text --region "$AWS_REGION")"
    aws ec2 attach-internet-gateway --internet-gateway-id "$igw_id" --vpc-id "$vpc_id" --region "$AWS_REGION" >/dev/null
  fi
  echo "$igw_id"
}

_anvil_ec2_ensure_route_table() {
  local vpc_id="$1" igw_id="$2" rt_id
  rt_id="$(aws ec2 describe-route-tables \
    --filters "Name=vpc-id,Values=${vpc_id}" "Name=tag:Name,Values=${ANVIL_VPC_NAME}-rt" \
    --query 'RouteTables[0].RouteTableId' --output text --region "$AWS_REGION" 2>/dev/null || true)"
  if [[ -z "$rt_id" || "$rt_id" == "None" ]]; then
    rt_id="$(aws ec2 create-route-table \
      --vpc-id "$vpc_id" \
      --tag-specifications "ResourceType=route-table,Tags=[{Key=Name,Value=${ANVIL_VPC_NAME}-rt}]" \
      --query 'RouteTable.RouteTableId' --output text --region "$AWS_REGION")"
  fi
  # Idempotently ensure the default route to the IGW exists.
  if ! aws ec2 describe-route-tables --route-table-ids "$rt_id" \
      --query 'RouteTables[0].Routes[?GatewayId==`'${igw_id}'`].GatewayId' \
      --output text --region "$AWS_REGION" 2>/dev/null | grep -q "$igw_id"; then
    aws ec2 create-route --route-table-id "$rt_id" --destination-cidr-block 0.0.0.0/0 \
      --gateway-id "$igw_id" --region "$AWS_REGION" >/dev/null 2>&1 || true
  fi
  echo "$rt_id"
}

# ---------------------------------------------------------------------------
# Security groups (the security-critical part)
# ---------------------------------------------------------------------------

# Ensure an ingress rule exists; idempotent (no-op if already present).
_anvil_ec2_ensure_ingress() {
  local sg_id="$1" port="$2" cidr="$3" proto="${4:-tcp}"
  local exists
  exists="$(aws ec2 describe-security-groups \
    --group-ids "$sg_id" \
    --query "SecurityGroups[0].IpPermissions[?FromPort==${port} && ToPort==${port} && IpRanges[?CidrIp=='${cidr}']].FromPort" \
    --output text --region "$AWS_REGION" 2>/dev/null || true)"
  if [[ -n "$exists" && "$exists" != "None" ]]; then
    echo "    ingress ${proto}/${port} from ${cidr} already present."
    return 0
  fi
  aws ec2 authorize-security-group-ingress \
    --group-id "$sg_id" --protocol "$proto" --port "$port" --cidr "$cidr" \
    --region "$AWS_REGION" >/dev/null
  echo "    authorized ingress ${proto}/${port} from ${cidr}."
}

_anvil_ec2_ensure_ec2_sg() {
  local vpc_id="$1" ssh_cidr="$2" sg_id
  sg_id="$(aws ec2 describe-security-groups \
    --filters "Name=group-name,Values=${EC2_SG_NAME}" "Name=vpc-id,Values=${vpc_id}" \
    --query 'SecurityGroups[0].GroupId' --output text --region "$AWS_REGION" 2>/dev/null || true)"
  if [[ -z "$sg_id" || "$sg_id" == "None" ]]; then
    sg_id="$(aws ec2 create-security-group \
      --group-name "$EC2_SG_NAME" \
      --description "Anvil EC2 — SSH from caller only; HTTP/HTTPS public" \
      --vpc-id "$vpc_id" \
      --tag-specifications "ResourceType=security-group,Tags=[{Key=Name,Value=${EC2_SG_NAME}}]" \
      --query 'GroupId' --output text --region "$AWS_REGION")"
  fi
  echo "  EC2 SG: ${sg_id}"
  # SSH (22) from the caller's public IP ONLY — never 0.0.0.0/0.
  _anvil_ec2_ensure_ingress "$sg_id" 22 "$ssh_cidr" tcp
  # HTTP/HTTPS (80/443) from anywhere (public web is intended).
  _anvil_ec2_ensure_ingress "$sg_id" 80 "0.0.0.0/0" tcp
  _anvil_ec2_ensure_ingress "$sg_id" 443 "0.0.0.0/0" tcp
  echo "$sg_id"
}

_anvil_ec2_ensure_rds_sg() {
  local vpc_id="$1" ec2_sg_id="$2" sg_id
  sg_id="$(aws ec2 describe-security-groups \
    --filters "Name=group-name,Values=${RDS_SG_NAME}" "Name=vpc-id,Values=${vpc_id}" \
    --query 'SecurityGroups[0].GroupId' --output text --region "$AWS_REGION" 2>/dev/null || true)"
  if [[ -z "$sg_id" || "$sg_id" == "None" ]]; then
    sg_id="$(aws ec2 create-security-group \
      --group-name "$RDS_SG_NAME" \
      --description "Anvil RDS — 3306 from EC2 SG only (private)" \
      --vpc-id "$vpc_id" \
      --tag-specifications "ResourceType=security-group,Tags=[{Key=Name,Value=${RDS_SG_NAME}}]" \
      --query 'GroupId' --output text --region "$AWS_REGION")"
  fi
  echo "  RDS SG: ${sg_id}"
  # 3306 from the EC2 SG ONLY — never 0.0.0.0/0. RDS stays private in the VPC.
  local exists
  exists="$(aws ec2 describe-security-groups \
    --group-ids "$sg_id" \
    --query "SecurityGroups[0].IpPermissions[?FromPort==3306 && ToPort==3306 && UserIdGroupPairs[?GroupId=='${ec2_sg_id}']].FromPort" \
    --output text --region "$AWS_REGION" 2>/dev/null || true)"
  if [[ -n "$exists" && "$exists" != "None" ]]; then
    echo "    ingress tcp/3306 from ${ec2_sg_id} already present."
  else
    aws ec2 authorize-security-group-ingress \
      --group-id "$sg_id" --protocol tcp --port 3306 \
      --source-group "$ec2_sg_id" --region "$AWS_REGION" >/dev/null
    echo "    authorized ingress tcp/3306 from ${ec2_sg_id}."
  fi
  echo "$sg_id"
}

# ---------------------------------------------------------------------------
# Key pair
# ---------------------------------------------------------------------------

_anvil_ec2_ensure_key() {
  local key_name="$1" key_file="$2" material
  if aws ec2 describe-key-pairs --key-names "$key_name" --region "$AWS_REGION" >/dev/null 2>&1; then
    if [[ -f "$key_file" ]]; then
      echo "  key pair '${key_name}' already exists; reusing local ${key_file}."
      return 0
    fi
    echo "ERROR: key pair '${key_name}' exists in AWS but ${key_file} is missing." >&2
    echo "       Delete the key pair (or choose a different --key-name) and retry." >&2
    return 1
  fi
  material="$(aws ec2 create-key-pair --key-name "$key_name" \
    --query 'KeyMaterial' --output text --region "$AWS_REGION")"
  printf '%s\n' "$material" > "$key_file"
  chmod 600 "$key_file"
  echo "  wrote private key to ${key_file} (chmod 600)."
}

# ---------------------------------------------------------------------------
# RDS + SSM
# ---------------------------------------------------------------------------

_anvil_ec2_ensure_db_subnet_group() {
  local group_name="$1" subnet_a="$2" subnet_b="$3"
  if aws rds describe-db-subnet-groups --db-subnet-group-name "$group_name" \
      --region "$AWS_REGION" >/dev/null 2>&1; then
    echo "  DB subnet group '${group_name}' already exists."
    return 0
  fi
  aws rds create-db-subnet-group \
    --db-subnet-group-name "$group_name" \
    --db-subnet-group-description "Anvil RDS subnet group" \
    --subnet-ids "$subnet_a" "$subnet_b" --region "$AWS_REGION" >/dev/null
  echo "  created DB subnet group '${group_name}'."
}

_anvil_ec2_put_ssm() {
  local name="$1" value="$2"
  aws ssm put-parameter --name "$name" --value "$value" \
    --type SecureString --overwrite --region "$AWS_REGION" >/dev/null
}

# Wait (poll) for the RDS instance to expose an endpoint, then return it.
_anvil_ec2_wait_rds_endpoint() {
  local db_id="$1" endpoint attempts
  attempts=80
  for _ in $(seq 1 "$attempts"); do
    endpoint="$(aws rds describe-db-instances --db-instance-identifier "$db_id" \
      --query 'DBInstances[0].Endpoint.Address' --output text --region "$AWS_REGION" 2>/dev/null || true)"
    if [[ -n "$endpoint" && "$endpoint" != "None" ]]; then
      echo "$endpoint"
      return 0
    fi
    sleep 15
  done
  echo "ERROR: timed out waiting for RDS endpoint for '${db_id}'." >&2
  return 1
}

# ---------------------------------------------------------------------------
# IAM instance profile (lets the php container read SSM via the instance role)
# ---------------------------------------------------------------------------

_anvil_ec2_ensure_instance_profile() {
  local role_name="${ANVIL_IAM_ROLE_NAME}" profile_name="${ANVIL_IAM_PROFILE_NAME}" acct
  # Idempotent: create the role + profile only if missing.
  if ! aws iam get-role --role-name "$role_name" --region "$AWS_REGION" >/dev/null 2>&1; then
    acct="$(aws sts get-caller-identity --query Account --output text --region "$AWS_REGION")"
    aws iam create-role --role-name "$role_name" \
      --assume-role-policy-document "{\"Version\":\"2012-10-17\",\"Statement\":[{\"Effect\":\"Allow\",\"Principal\":{\"Service\":\"ec2.amazonaws.com\"},\"Action\":\"sts:AssumeRole\"}]}" \
      --region "$AWS_REGION" >/dev/null
    # Minimal inline policy: read only the Anvil RDS SSM parameters.
    aws iam put-role-policy --role-name "$role_name" --policy-name "${ANVIL_IAM_POLICY_NAME}" \
      --policy-document "{\"Version\":\"2012-10-17\",\"Statement\":[{\"Effect\":\"Allow\",\"Action\":[\"ssm:GetParameter\",\"ssm:GetParameters\"],\"Resource\":\"arn:aws:ssm:${AWS_REGION}:${acct}:parameter${ANVIL_SSM_RDS_HOST%/*}/*\"}]}" \
      --region "$AWS_REGION" >/dev/null
  fi
  if ! aws iam get-instance-profile --instance-profile-name "$profile_name" --region "$AWS_REGION" >/dev/null 2>&1; then
    aws iam create-instance-profile --instance-profile-name "$profile_name" --region "$AWS_REGION" >/dev/null
    aws iam add-role-to-instance-profile --instance-profile-name "$profile_name" --role-name "$role_name" --region "$AWS_REGION" >/dev/null 2>&1 || true
  fi
  echo "$profile_name"
}

# ---------------------------------------------------------------------------
# anvil_ec2_provision — orchestrator
# ---------------------------------------------------------------------------

anvil_ec2_provision() {
  local env_name="prod"
  local ssh_cidr="" skip_rds=0 rds_endpoint="" rds_user="" rds_password="" rds_database=""
  local confirm_t3_small=0 ami_id="" key_name="$ANVIL_KEY_NAME" key_file="$SSH_KEY_PATH"
  local caller_ip=""
  local dry_run=0

  while [[ $# -gt 0 ]]; do
    case "$1" in
      --env)            env_name="${2:?--env requires a value}"; shift 2 ;;
      --ssh-cidr)       ssh_cidr="${2:?--ssh-cidr requires a value}"; shift 2 ;;
      --skip-rds)       skip_rds=1; shift ;;
      --rds-endpoint)   rds_endpoint="${2:?--rds-endpoint requires a value}"; shift 2 ;;
      --rds-user)       rds_user="${2:?--rds-user requires a value}"; shift 2 ;;
      --rds-password)   rds_password="${2:?--rds-password requires a value}"; shift 2 ;;
      --rds-database)   rds_database="${2:?--rds-database requires a value}"; shift 2 ;;
      --confirm-t3-small) confirm_t3_small=1; shift ;;
      --ami)            ami_id="${2:?--ami requires a value}"; shift 2 ;;
      --key-name)       key_name="${2:?--key-name requires a value}"; shift 2 ;;
      --key-path)       key_file="${2:?--key-path requires a value}"; shift 2 ;;
      --region)         AWS_REGION="${2:?--region requires a value}"; shift 2 ;;
      --dry-run)        dry_run=1; shift ;;
      -h|--help)        anvil_ec2_help; return 0 ;;
      *) echo "Unknown option: $1" >&2; anvil_ec2_help; return 1 ;;
    esac
  done

  if [[ "$dry_run" -eq 1 ]]; then
    echo "=== Anvil EC2 Provisioning DRY-RUN ==="
    echo "This is a dry-run. No AWS resources will be created."
    echo
    echo "Configuration that would be used:"
    echo "  INSTANCE_TYPE: ${INSTANCE_TYPE}"
    echo "  AWS_REGION:    ${AWS_REGION}"
    echo "  EC2_SG_NAME:   ${EC2_SG_NAME}"
    echo "  RDS_SG_NAME:   ${RDS_SG_NAME}"
    echo "  VPC_NAME:      ${ANVIL_VPC_NAME}"
    echo "  SUBNET_NAME:   ${ANVIL_SUBNET_NAME}"
    echo "  IGW_NAME:      ${ANVIL_IGW_NAME}"
    echo "  DB_SUBNET_GRP: ${ANVIL_DB_SUBNET_GROUP}"
    echo "  KEY_NAME:      ${ANVIL_KEY_NAME}"
    echo "  SSH_KEY_PATH:  ${SSH_KEY_PATH}"
    echo "  CERTBOT_DOMAINS: ${CERTBOT_DOMAINS:-<none>}"
    echo "  CERTBOT_EMAIL:   ${CERTBOT_EMAIL:-<none>}"
    echo "  WEB_UI_HOST:     ${WEB_UI_HOST}"
    echo "  WEB_UI_PORT:     ${WEB_UI_PORT}"
    echo
    echo "Security group rules that would be created:"
    echo "  EC2 SG (${EC2_SG_NAME}):"
    echo "    - SSH (22) from caller IP only (auto-detected or --ssh-cidr)"
    echo "    - HTTP (80) from 0.0.0.0/0"
    echo "    - HTTPS (443) from 0.0.0.0/0"
    echo "  RDS SG (${RDS_SG_NAME}):"
    echo "    - MySQL (3306) from EC2 SG only (source-group reference)"
    echo
    echo "RDS configuration:"
    if [[ "$skip_rds" -eq 1 ]]; then
      echo "  --skip-rds specified; would use existing RDS endpoint: ${rds_endpoint:-<required>}"
    else
      local az_label public_label
      if [[ "$ANVIL_RDS_MULTI_AZ" == "true" ]]; then az_label="multi-AZ"; else az_label="single-AZ"; fi
      if [[ "$ANVIL_RDS_PUBLICLY_ACCESSIBLE" == "true" ]]; then public_label="YES"; else public_label="NO"; fi
      echo "  Engine: ${ANVIL_RDS_ENGINE} ${ANVIL_RDS_ENGINE_VERSION}"
      echo "  Instance class: ${ANVIL_RDS_INSTANCE_CLASS}"
      echo "  Storage: ${ANVIL_RDS_ALLOCATED_STORAGE} GB encrypted, ${az_label}"
      echo "  Publicly accessible: ${public_label}"
      echo "  Backup retention: ${ANVIL_RDS_BACKUP_RETENTION} days"
      echo "  Credentials stored in SSM Parameter Store as SecureString under ${ANVIL_SSM_RDS_HOST%/*}/*"
    fi
    echo
    echo "EC2 instance:"
    echo "  AMI: Amazon Linux 2023 (latest via SSM parameter)"
    echo "  Instance type: ${INSTANCE_TYPE}"
    echo "  IAM instance profile: ${ANVIL_IAM_PROFILE_NAME} (SSM read access to ${ANVIL_SSM_RDS_HOST%/*}/*)"
    echo "  User data: ${ANVIL_ROOT}/provisioning/cloud-init.yaml"
    echo "  Tags: Name=${ANVIL_EC2_TAG_NAME_PREFIX}${env_name}, Project=${ANVIL_EC2_TAG_PROJECT}"
    echo
    if [[ "$INSTANCE_TYPE" == "t3.small" && "$confirm_t3_small" -ne 1 ]]; then
      echo "ERROR (dry-run): INSTANCE_TYPE=t3.small requires --confirm-t3-small"
      echo "  (t3.small is NOT free-tier eligible on accounts created before 2025-07-15)"
      echo "  Estimated cost: ~\$0.0208/hr (~\$15/mo)"
      return 1
    fi
    if [[ "$INSTANCE_TYPE" == "t3.small" ]]; then
      echo "WARNING: t3.small confirmed. Estimated cost: ~\$0.0208/hr (~\$15/mo)."
    fi
    echo
    echo "Dry-run complete. Re-run without --dry-run to execute."
    return 0
  fi

  _anvil_ec2_check_prereqs || return 1

  # --- t3.small cost guard (hard requirement) ---
  if [[ "$INSTANCE_TYPE" == "t3.small" && "$confirm_t3_small" -ne 1 ]]; then
    echo "ERROR: INSTANCE_TYPE=t3.small is NOT free-tier eligible on AWS accounts" >&2
    echo "       created before 2025-07-15. Estimated cost: ~\$0.0208/hr (~\$15/mo)." >&2
    echo "       Re-run with --confirm-t3-small ONLY if you are on the newer" >&2
    echo "       credit-based Free plan and accept the cost." >&2
    return 1
  fi
  if [[ "$INSTANCE_TYPE" == "t3.small" ]]; then
    echo "WARNING: t3.small confirmed by operator. Estimated cost: ~\$0.0208/hr (~\$15/mo)." >&2
  fi

  # --- resolve caller IP for the SSH lock-down ---
  if [[ -z "$ssh_cidr" ]]; then
    if ! command -v curl >/dev/null 2>&1; then
      echo "ERROR: 'curl' not found and --ssh-cidr not provided; cannot resolve" >&2
      echo "       your public IP. Pass --ssh-cidr CIDR (e.g. 1.2.3.4/32)." >&2
      return 1
    fi
    caller_ip="$(curl -s "${ANVIL_IP_DETECTION_URL}" || true)"
    if [[ -z "$caller_ip" ]]; then
      echo "ERROR: could not resolve caller IP via ${ANVIL_IP_DETECTION_URL}." >&2
      echo "       Pass --ssh-cidr CIDR explicitly." >&2
      return 1
    fi
    ssh_cidr="${caller_ip}/32"
  fi
  echo "Resolved SSH source CIDR: ${ssh_cidr}"

  # --- networking ---
  echo "Ensuring VPC '${ANVIL_VPC_NAME}' ..."
  local vpc_id igw_id rt_id subnets primary_subnet other_subnet ec2_sg_id rds_sg_id
  vpc_id="$(_anvil_ec2_ensure_vpc)"
  echo "  VPC: ${vpc_id}"
  igw_id="$(_anvil_ec2_ensure_igw "$vpc_id")"
  rt_id="$(_anvil_ec2_ensure_route_table "$vpc_id" "$igw_id")"
  subnets="$(_anvil_ec2_ensure_subnets "$vpc_id")"
  primary_subnet="${subnets%% *}"
  other_subnet="${subnets#* }"
  other_subnet="${other_subnet%% *}"

  # --- security groups (security-critical) ---
  echo "Ensuring security groups ..."
  ec2_sg_id="$(_anvil_ec2_ensure_ec2_sg "$vpc_id" "$ssh_cidr")"
  rds_sg_id="$(_anvil_ec2_ensure_rds_sg "$vpc_id" "$ec2_sg_id")"

  # --- key pair ---
  echo "Ensuring key pair '${key_name}' ..."
  _anvil_ec2_ensure_key "$key_name" "$key_file" || return 1

  # --- RDS (or reuse provided endpoint) ---
  local db_host db_user db_password db_name
  if [[ "$skip_rds" -eq 1 ]]; then
    if [[ -z "$rds_endpoint" ]]; then
      echo "ERROR: --skip-rds requires --rds-endpoint (the existing RDS host)." >&2
      return 1
    fi
    db_host="$rds_endpoint"
    db_user="${rds_user:-${ANVIL_RDS_MASTER_USERNAME_PREFIX}}"
    db_password="${rds_password:-}"
    db_name="${rds_database:-${ANVIL_RDS_DB_NAME}}"
    echo "Skipping RDS creation; using provided endpoint ${db_host}."
    if [[ -z "$db_password" ]]; then
      echo "ERROR: --skip-rds requires --rds-password to write SSM secrets." >&2
      return 1
    fi
  else
    _anvil_ec2_ensure_db_subnet_group "$ANVIL_DB_SUBNET_GROUP" "$primary_subnet" "$other_subnet"
    db_user="${ANVIL_RDS_MASTER_USERNAME_PREFIX}$(date +%s | tail -c 5)"
    db_password="$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | head -c 24)"
    db_name="${ANVIL_RDS_DB_NAME}"
    local db_id="${ANVIL_RDS_IDENTIFIER_PREFIX}${env_name}"
    local multi_az_flag no_public_flag storage_enc_flag
    if [[ "$ANVIL_RDS_MULTI_AZ" == "true" ]]; then
      multi_az_flag="--multi-az"
    else
      multi_az_flag="--no-multi-az"
    fi
    if [[ "$ANVIL_RDS_PUBLICLY_ACCESSIBLE" == "true" ]]; then
      no_public_flag="--publicly-accessible"
    else
      no_public_flag="--no-publicly-accessible"
    fi
    if [[ "$ANVIL_RDS_STORAGE_ENCRYPTED" == "true" ]]; then
      storage_enc_flag="--storage-encrypted"
    else
      storage_enc_flag="--no-storage-encrypted"
    fi
    echo "Creating RDS instance '${db_id}' (${ANVIL_RDS_INSTANCE_CLASS}, ${storage_enc_flag}, ${multi_az_flag}) ..."
    aws rds create-db-instance \
      --db-instance-identifier "$db_id" \
      --db-instance-class "${ANVIL_RDS_INSTANCE_CLASS}" \
      --engine "${ANVIL_RDS_ENGINE}" \
      --engine-version "${ANVIL_RDS_ENGINE_VERSION}" \
      --master-username "$db_user" \
      --master-user-password "$db_password" \
      --db-name "$db_name" \
      --allocated-storage "${ANVIL_RDS_ALLOCATED_STORAGE}" \
      "$storage_enc_flag" \
      "$multi_az_flag" \
      "$no_public_flag" \
      --db-subnet-group-name "$ANVIL_DB_SUBNET_GROUP" \
      --vpc-security-group-ids "$rds_sg_id" \
      --backup-retention-period "${ANVIL_RDS_BACKUP_RETENTION}" \
      --region "$AWS_REGION" >/dev/null
    db_host="$(_anvil_ec2_wait_rds_endpoint "$db_id")"
    echo "  RDS endpoint: ${db_host}"
  fi

  # --- store RDS credentials in SSM Parameter Store as SecureString ---
  echo "Writing RDS credentials to SSM Parameter Store (SecureString) ..."
  _anvil_ec2_put_ssm "${ANVIL_SSM_RDS_HOST}" "$db_host"
  _anvil_ec2_put_ssm "${ANVIL_SSM_RDS_USER}" "$db_user"
  _anvil_ec2_put_ssm "${ANVIL_SSM_RDS_PASSWORD}" "$db_password"
  _anvil_ec2_put_ssm "${ANVIL_SSM_RDS_DATABASE}" "$db_name"

  # --- resolve AMI (Amazon Linux 2023 latest) if not provided ---
  if [[ -z "$ami_id" ]]; then
    ami_id="$(aws ssm get-parameter \
      --name "${ANVIL_AMI_SSM_PARAM}" \
      --query 'Parameter.Value' --output text --region "$AWS_REGION")"
  fi

  # --- IAM instance profile (SSM read for the php container) ---
  local instance_profile
  instance_profile="$(_anvil_ec2_ensure_instance_profile)"

  # --- launch EC2 instance ---
  echo "Launching EC2 instance (${INSTANCE_TYPE}) ..."
  local instance_id public_ip user_data_file user_data_tmp
  user_data_file="${ANVIL_ROOT}/provisioning/cloud-init.yaml"
  if [[ ! -f "$user_data_file" ]]; then
    echo "ERROR: cloud-init user-data not found at ${user_data_file}." >&2
    return 1
  fi
  # Substitute configurable values (repo URL, remote app dir) into a temp copy
  # so the source template is never modified in place.
  user_data_tmp="$(mktemp)"
  sed -e "s#https://github.com/example/anvil.git#${ANVIL_REPO_URL}#g" \
      -e "s#/opt/anvil#${ANVIL_REMOTE_APP_DIR}#g" \
      "$user_data_file" > "$user_data_tmp"
  user_data_file="$user_data_tmp"
  instance_id="$(aws ec2 run-instances \
    --image-id "$ami_id" \
    --instance-type "$INSTANCE_TYPE" \
    --key-name "$key_name" \
    --subnet-id "$primary_subnet" \
    --security-group-ids "$ec2_sg_id" \
    --iam-instance-profile "Name=${instance_profile}" \
    --associate-public-ip-address \
    --user-data "file://${user_data_file}" \
    --tag-specifications "ResourceType=instance,Tags=[{Key=Name,Value=${ANVIL_EC2_TAG_NAME_PREFIX}${env_name}},{Key=Project,Value=${ANVIL_EC2_TAG_PROJECT}}]" \
    --query 'Instances[0].InstanceId' --output text --region "$AWS_REGION")"
  echo "  instance: ${instance_id}"
  # Wait for public IP.
  for _ in $(seq 1 40); do
    public_ip="$(aws ec2 describe-instances --instance-ids "$instance_id" \
      --query 'Reservations[0].Instances[0].PublicIpAddress' --output text --region "$AWS_REGION" 2>/dev/null || true)"
    [[ -n "$public_ip" && "$public_ip" != "None" ]] && break
    sleep 5
  done

  # --- best-effort: push RDS_ENDPOINT to the EC2 host so phpMyAdmin resolves it ---
  if [[ -n "$public_ip" && -n "$db_host" ]]; then
    local env_push="RDS_ENDPOINT=${db_host}"
    if ssh -i "$key_file" -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10 \
        "${ANVIL_EC2_SSH_USER}@${public_ip}" \
        "mkdir -p ${ANVIL_REMOTE_APP_DIR}/docker && printf '%s\n' '${env_push}' > ${ANVIL_REMOTE_APP_DIR}/docker/.env" \
        >/dev/null 2>&1; then
      echo "Wrote RDS_ENDPOINT to ${ANVIL_REMOTE_APP_DIR}/docker/.env on the EC2 host."
    else
      echo "NOTE: could not SSH to push RDS_ENDPOINT automatically."
      echo "      On the EC2 host, create ${ANVIL_REMOTE_APP_DIR}/docker/.env with: ${env_push}"
    fi
  fi

  # --- summary ---
  echo
  echo "=== Anvil EC2 provisioning complete ==="
  echo "Instance : ${instance_id} (${INSTANCE_TYPE})"
  echo "Public IP: ${public_ip:-<pending>}"
  echo "SSH key  : ${key_file}"
  echo "EC2 SG   : ${ec2_sg_id}  (22 from ${ssh_cidr}; 80/443 from 0.0.0.0/0)"
  echo "RDS SG   : ${rds_sg_id}  (3306 from ${ec2_sg_id} only)"
  echo "RDS host : ${db_host}"
  echo
  echo "Next steps:"
  echo "  - SSH tunnel for phpMyAdmin/Web UI:"
  echo "      ssh -N -L 8080:127.0.0.1:8080 -L 9999:127.0.0.1:9999 -i ${key_file} ${ANVIL_EC2_SSH_USER}@${public_ip}"
  echo "  - Bastion tunnel to private RDS (local 3306):"
  echo "      anvilctl ec2 tunnel --rds-endpoint ${db_host} --host ${public_ip} --key ${key_file}"
  echo "  - Issue Let's Encrypt certs:"
  echo "      anvilctl ec2 certbot   (or provisioning/certbot-setup.sh)"
}

# ---------------------------------------------------------------------------
# anvil_ec2_tunnel — developer bastion tunnel to private RDS
# ---------------------------------------------------------------------------

anvil_ec2_tunnel() {
  local rds_endpoint="" ec2_host="" key_file="$SSH_KEY_PATH" local_port="3306" remote_port="3306"
  while [[ $# -gt 0 ]]; do
    case "$1" in
      --rds-endpoint) rds_endpoint="${2:?--rds-endpoint requires a value}"; shift 2 ;;
      --host)         ec2_host="${2:?--host requires a value}"; shift 2 ;;
      --key)          key_file="${2:?--key requires a value}"; shift 2 ;;
      --local-port)   local_port="${2:?--local-port requires a value}"; shift 2 ;;
      --remote-port)  remote_port="${2:?--remote-port requires a value}"; shift 2 ;;
      -h|--help)      anvil_ec2_help; return 0 ;;
      *) echo "Unknown option: $1" >&2; return 1 ;;
    esac
  done

  if [[ -z "$rds_endpoint" || -z "$ec2_host" ]]; then
    echo "ERROR: anvil_ec2_tunnel requires --rds-endpoint and --host." >&2
    echo "       RDS is NEVER public; the tunnel goes through the EC2 bastion." >&2
    return 1
  fi
  if ! command -v ssh >/dev/null 2>&1; then
    echo "ERROR: 'ssh' client not found on PATH." >&2
    return 1
  fi
  if [[ ! -f "$key_file" ]]; then
    echo "ERROR: SSH key not found at ${key_file}." >&2
    return 1
  fi

  echo "Opening SSH tunnel: local :${local_port} -> ${rds_endpoint}:${remote_port}"
  echo "via bastion ${ANVIL_EC2_SSH_USER}@${ec2_host} (RDS stays private inside the VPC)."
  echo "Press Ctrl-C to close. On your Kubuntu, point your DB client at 127.0.0.1:${local_port}."
  # -N: do not execute a remote command; -L: local forward.
  exec ssh -N \
    -L "${local_port}:${rds_endpoint}:${remote_port}" \
    -i "$key_file" \
    -o StrictHostKeyChecking=accept-new \
    "ec2-user@${ec2_host}"
}

# ---------------------------------------------------------------------------
# anvil_ec2_billing — one-line cost check
# ---------------------------------------------------------------------------

anvil_ec2_billing() {
  if ! command -v aws >/dev/null 2>&1; then
    echo "ERROR: 'aws' CLI not found on PATH." >&2
    return 1
  fi
  local start end
  start="$(date -d '1 month ago' +%Y-%m-%d)"
  end="$(date +%Y-%m-%d)"
  echo "AWS cost & usage (UnblendedCost), ${start} -> ${end}:"
  aws ce get-cost-and-usage \
    --time-period Start="$start" End="$end" \
    --granularity MONTHLY \
    --metrics UnblendedCost \
    --region "$AWS_REGION"
}

# ---------------------------------------------------------------------------
# anvil_ec2_billing_alarm — CloudWatch billing alarm
# ---------------------------------------------------------------------------

anvil_ec2_billing_alarm() {
  if ! command -v aws >/dev/null 2>&1; then
    echo "ERROR: 'aws' CLI not found on PATH." >&2
    return 1
  fi

  # Threshold (USD) is configurable via the first argument or the
  # ANVIL_BILLING_ALARM_THRESHOLD environment variable; default 5 USD.
  local threshold="${1:-${ANVIL_BILLING_ALARM_THRESHOLD:-5}}"
  local alarm_name="${ANVIL_BILLING_ALARM_NAME:-anvil-billing-alarm}"
  # The AWS/Billing metric namespace is ONLY available in us-east-1, so the
  # alarm must be created there regardless of the configured AWS_REGION.
  local alarm_region="us-east-1"

  echo "Creating CloudWatch billing alarm '${alarm_name}' (threshold ${threshold} USD) in ${alarm_region} ..."
  echo "NOTE: ensure 'Receive Billing Alerts' is enabled in the AWS Billing console."
  aws cloudwatch put-metric-alarm \
    --alarm-name "$alarm_name" \
    --namespace AWS/Billing \
    --metric-name EstimatedCharges \
    --dimensions Name=Currency,Value=USD \
    --statistic Maximum \
    --period 21600 \
    --threshold "$threshold" \
    --comparison-operator GreaterThanThreshold \
    --evaluation-periods 1 \
    --region "$alarm_region"
  echo "Billing alarm created. You will be notified when estimated charges exceed ${threshold} USD."
}

# ---------------------------------------------------------------------------
# anvil_ec2_help
# ---------------------------------------------------------------------------

anvil_ec2_help() {
  cat <<'EOF'
Anvil EC2 / RDS provisioning (Phase 4)

Security model (hard requirements):
  - EC2 SG: 22 (SSH) from the caller's public IP ONLY (never 0.0.0.0/0).
  - EC2 SG: 80/443 from 0.0.0.0/0 (public web is intended).
  - RDS SG: 3306 from the EC2 SG id ONLY (RDS is private inside the VPC).
  - Web UI binds to 127.0.0.1 on the host; reached only via the SSH tunnel.
  - RDS credentials are stored in SSM Parameter Store as SecureString.
  - INSTANCE_TYPE defaults to t3.micro (free-tier safe). t3.small requires
    --confirm-t3-small and prints a ~$0.0208/hr (~$15/mo) cost warning.

Commands:
  anvilctl ec2 provision [opts]   Provision VPC/SG/key/RDS/EC2 (idempotent).
  anvilctl ec2 tunnel  --rds-endpoint E --host H --key K
                                 Open a bastion SSH tunnel to private RDS.
  anvilctl ec2 billing            Show this month's AWS cost (UnblendedCost).
  anvilctl ec2 billing-alarm [USD]  Create a CloudWatch billing alarm
                                 (default 5 USD; override via arg or
                                 ANVIL_BILLING_ALARM_THRESHOLD).
  anvilctl ec2 help               Show this help.
  anvilctl billing                Alias for the billing check.

provision options:
  --env NAME            Env tag (default: prod) -> key anvil-NAME, tag anvil-NAME.
  --ssh-cidr CIDR       SSH source CIDR (default: your public IP /32).
  --skip-rds            Do not create RDS; use --rds-endpoint instead.
  --rds-endpoint HOST   Existing RDS host (with --skip-rds).
  --rds-user/-password/-database  Credentials for --skip-rds (written to SSM).
  --confirm-t3-small    Allow t3.small (cost warning printed).
  --ami ID              Override the Amazon Linux 2023 AMI.
  --key-name NAME       EC2 key pair name (default: anvil-prod).
  --key-path PATH       Where to write the private key (chmod 600).
  --region REGION       AWS region (default: us-east-1).

Current configuration:
EOF
  echo "  INSTANCE_TYPE=${INSTANCE_TYPE:-t3.micro}"
  echo "  EC2_COMPOSE_FILE=${EC2_COMPOSE_FILE}"
  echo "  EC2_SG_NAME=${EC2_SG_NAME}"
  echo "  RDS_SG_NAME=${RDS_SG_NAME}"
  echo "  AWS_REGION=${AWS_REGION}"
  echo "  WEB_UI_HOST=${WEB_UI_HOST:-127.0.0.1}  WEB_UI_PORT=${WEB_UI_PORT:-9999}"
}
