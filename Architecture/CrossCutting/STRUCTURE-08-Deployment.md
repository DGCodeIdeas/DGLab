# DGLab Wheel Architecture
## Structure 08: Deployment & Infrastructure Architecture

> **Reconciled to ADR-007 / ADR-001** (`Verification/INCONSISTENCIES.md` #1 and #5):
> connection URIs use the `postgresql://…:5432` scheme, and the directory trees below describe
> the layout **within a single repository of the polyrepo**, not a monorepo.


> **Repository:** https://github.com/DGCodeIdeas/DGLab  
> **Framework:** Custom PHP MVC Framework  
> **Pattern:** Concentric Wheel with Immutable Infrastructure

---

## 1. The Deployment Principle

The DGLab wheel is **never modified in place**. It is rebuilt, tested, and replaced as a complete unit. Every deployment is an atomic swap — the old wheel stops, the new wheel starts. There is no "patching" or "hotfixing" a running instance.

```
Immutable Deployment:

   Build          Test           Deploy           Verify
    │              │               │                │
    ▼              ▼               ▼                ▼
┌────────┐    ┌────────┐     ┌────────┐      ┌────────┐
│  Code  │───►│  CI    │────►│  Blue  │─────►│ Health │
│ Change │    │ Pipeline│     │ /Green │      │ Checks │
└────────┘    └────────┘     └────────┘      └────────┘
                                  │
                                  ▼
                            ┌────────┐
                            │  Roll  │
                            │  Back  │ (if health fails)
                            └────────┘
```

**Rule:** If a deployment fails health checks, it is automatically rolled back. No human decision is required for rollback.

---

## 2. Infrastructure as Code

### 2.1 Repository Structure

```
DGLab/
├── 📁 deploy/
│   ├── 📁 terraform/              # Infrastructure definitions
│   │   ├── 📁 modules/
│   │   │   ├── 📁 vpc/            # Network topology
│   │   │   ├── 📁 eks/            # Kubernetes cluster
│   │   │   ├── 📁 rds/            # Database
│   │   │   ├── 📁 elasticache/    # Redis
│   │   │   ├── 📁 s3/             # Object storage
│   │   │   └── 📁 cloudfront/     # CDN
│   │   ├── 📁 environments/
│   │   │   ├── 📁 dev/
│   │   │   ├── 📁 staging/
│   │   │   └── 📁 production/
│   │   └── main.tf
│   │
│   ├── 📁 kubernetes/             # K8s manifests
│   │   ├── 📁 base/               # Shared resources
│   │   │   ├── namespace.yaml
│   │   │   ├── configmap.yaml
│   │   │   └── secrets.yaml
│   │   ├── 📁 hub/                # Hub service deployments
│   │   │   ├── identity-deployment.yaml
│   │   │   ├── cache-deployment.yaml
│   │   │   └── ...
│   │   ├── 📁 spokes/             # Spoke deployments
│   │   │   ├── external/
│   │   │   └── internal/
│   │   ├── 📁 bridge/             # Vanguard deployment
│   │   │   ├── vanguard-deployment.yaml
│   │   │   ├── vanguard-service.yaml
│   │   │   └── vanguard-hpa.yaml
│   │   └── 📁 monitoring/         # Prometheus, Grafana
│   │
│   └── 📁 pipeline/               # CI/CD definitions
│       ├── 📁 github-actions/
│       ├── 📁 argocd/
│       └── 📁 scripts/
│
└── 📁 infrastructure/             # Runtime infrastructure code
    └── 📁 scripts/
        ├── backup.sh
        ├── restore.sh
        └── rotate-secrets.sh
```

### 2.2 Terraform Module: VPC

```hcl
# deploy/terraform/modules/vpc/main.tf

resource "aws_vpc" "main" {
  cidr_block           = "10.0.0.0/16"
  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = {
    Name        = "sovereign-${var.environment}"
    Environment = var.environment
    ManagedBy   = "terraform"
  }
}

# Public subnets (Outer Rim — CDN, LB, BRIDGE-01)
resource "aws_subnet" "public" {
  count                   = 3
  vpc_id                  = aws_vpc.main.id
  cidr_block              = "10.0.${count.index + 1}.0/24"
  availability_zone       = data.aws_availability_zones.available.names[count.index]
  map_public_ip_on_launch = true

  tags = {
    Name = "sovereign-${var.environment}-public-${count.index + 1}"
    Tier = "public"
  }
}

# Private subnets (Hub + Inner Spokes)
resource "aws_subnet" "private" {
  count             = 3
  vpc_id            = aws_vpc.main.id
  cidr_block        = "10.0.${count.index + 10}.0/24"
  availability_zone = data.aws_availability_zones.available.names[count.index]

  tags = {
    Name = "sovereign-${var.environment}-private-${count.index + 1}"
    Tier = "private"
  }
}

# Isolated subnets (Datastores — no inbound from private)
resource "aws_subnet" "isolated" {
  count             = 3
  vpc_id            = aws_vpc.main.id
  cidr_block        = "10.0.${count.index + 20}.0/24"
  availability_zone = data.aws_availability_zones.available.names[count.index]

  tags = {
    Name = "sovereign-${var.environment}-isolated-${count.index + 1}"
    Tier = "isolated"
  }
}
```

### 2.3 Terraform Module: EKS Cluster

```hcl
# deploy/terraform/modules/eks/main.tf

module "eks" {
  source  = "terraform-aws-modules/eks/aws"
  version = "~> 20.0"

  cluster_name    = "sovereign-${var.environment}"
  cluster_version = "1.30"

  vpc_id     = var.vpc_id
  subnet_ids = var.private_subnet_ids

  eks_managed_node_groups = {
    hub = {
      desired_size = var.environment == "production" ? 3 : 2
      min_size     = 2
      max_size     = 10
      instance_types = ["m6i.xlarge"]
      capacity_type  = "ON_DEMAND"

      labels = {
        tier = "hub"
      }

      taints = [{
        key    = "tier"
        value  = "hub"
        effect = "NO_SCHEDULE"
      }]
    }

    spokes = {
      desired_size = var.environment == "production" ? 5 : 2
      min_size     = 2
      max_size     = 20
      instance_types = ["m6i.large"]
      capacity_type  = var.environment == "production" ? "ON_DEMAND" : "SPOT"

      labels = {
        tier = "spokes"
      }
    }

    bridge = {
      desired_size = var.environment == "production" ? 3 : 2
      min_size     = 2
      max_size     = 10
      instance_types = ["m6i.xlarge"]
      capacity_type  = "ON_DEMAND"

      labels = {
        tier = "bridge"
      }

      taints = [{
        key    = "tier"
        value  = "bridge"
        effect = "NO_SCHEDULE"
      }]
    }
  }
}
```

---

## 3. Kubernetes Architecture

### 3.1 Namespace Strategy

```yaml
# deploy/kubernetes/base/namespace.yaml
apiVersion: v1
kind: Namespace
metadata:
  name: sovereign-hub
  labels:
    tier: hub
    environment: production
---
apiVersion: v1
kind: Namespace
metadata:
  name: sovereign-spokes-internal
  labels:
    tier: spokes
    spoke-type: internal
    environment: production
---
apiVersion: v1
kind: Namespace
metadata:
  name: sovereign-spokes-external
  labels:
    tier: spokes
    spoke-type: external
    environment: production
---
apiVersion: v1
kind: Namespace
metadata:
  name: sovereign-bridge
  labels:
    tier: bridge
    environment: production
```

### 3.2 Bridge Deployment (Vanguard)

```yaml
# deploy/kubernetes/bridge/vanguard-deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: vanguard
  namespace: sovereign-bridge
  labels:
    app: vanguard
    tier: bridge
spec:
  replicas: 3
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 1
      maxUnavailable: 0
  selector:
    matchLabels:
      app: vanguard
  template:
    metadata:
      labels:
        app: vanguard
        tier: bridge
    spec:
      serviceAccountName: vanguard
      containers:
        - name: vanguard
          image: ghcr.io/dgcodeideas/sovereign-bridge:v2.1.0
          imagePullPolicy: Always
          ports:
            - containerPort: 8080
              name: http
            - containerPort: 9090
              name: metrics
          env:
            - name: APP_ENV
              value: "production"
            - name: DATABASE_URL
              valueFrom:
                secretKeyRef:
                  name: vanguard-secrets
                  key: database-url
            - name: REDIS_URL
              valueFrom:
                secretKeyRef:
                  name: vanguard-secrets
                  key: redis-url
            - name: JWT_PUBLIC_KEY
              valueFrom:
                secretKeyRef:
                  name: vanguard-secrets
                  key: jwt-public-key
          resources:
            requests:
              memory: "512Mi"
              cpu: "500m"
            limits:
              memory: "1Gi"
              cpu: "1000m"
          livenessProbe:
            httpGet:
              path: /health/live
              port: 8080
            initialDelaySeconds: 10
            periodSeconds: 10
            failureThreshold: 3
          readinessProbe:
            httpGet:
              path: /health/ready
              port: 8080
            initialDelaySeconds: 5
            periodSeconds: 5
            failureThreshold: 2
          volumeMounts:
            - name: tmp
              mountPath: /tmp
      volumes:
        - name: tmp
          emptyDir: {}
---
apiVersion: v1
kind: Service
metadata:
  name: vanguard
  namespace: sovereign-bridge
spec:
  selector:
    app: vanguard
  ports:
    - port: 80
      targetPort: 8080
      name: http
    - port: 9090
      targetPort: 9090
      name: metrics
  type: ClusterIP
---
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: vanguard-hpa
  namespace: sovereign-bridge
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: vanguard
  minReplicas: 3
  maxReplicas: 20
  metrics:
    - type: Resource
      resource:
        name: cpu
        target:
          type: Utilization
          averageUtilization: 70
    - type: Resource
      resource:
        name: memory
        target:
          type: Utilization
          averageUtilization: 80
  behavior:
    scaleUp:
      stabilizationWindowSeconds: 60
      policies:
        - type: Percent
          value: 100
          periodSeconds: 15
    scaleDown:
      stabilizationWindowSeconds: 300
      policies:
        - type: Percent
          value: 10
          periodSeconds: 60
```

### 3.3 Network Policies

```yaml
# deploy/kubernetes/base/network-policies.yaml
# Zero-Exposure enforcement at network layer

---
# Allow ingress to Vanguard only from LoadBalancer
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: vanguard-ingress
  namespace: sovereign-bridge
spec:
  podSelector:
    matchLabels:
      app: vanguard
  policyTypes:
    - Ingress
  ingress:
    - from:
        - namespaceSelector:
            matchLabels:
              name: ingress-nginx
      ports:
        - protocol: TCP
          port: 8080

---
# Allow Vanguard egress to Hub services only
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: vanguard-egress-hub
  namespace: sovereign-bridge
spec:
  podSelector:
    matchLabels:
      app: vanguard
  policyTypes:
    - Egress
  egress:
    - to:
        - namespaceSelector:
            matchLabels:
              name: sovereign-hub
      ports:
        - protocol: TCP
          port: 8080

---
# Allow Vanguard egress to External Spokes only
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: vanguard-egress-external-spokes
  namespace: sovereign-bridge
spec:
  podSelector:
    matchLabels:
      app: vanguard
  policyTypes:
    - Egress
  egress:
    - to:
        - namespaceSelector:
            matchLabels:
              name: sovereign-spokes-external
      ports:
        - protocol: TCP
          port: 8080

---
# DENY External Spokes from accessing Hub directly
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: external-spokes-deny-hub
  namespace: sovereign-spokes-external
spec:
  podSelector: {}
  policyTypes:
    - Egress
  egress:
    - to:
        - namespaceSelector:
            matchLabels:
              name: sovereign-bridge
      ports:
        - protocol: TCP
          port: 8080
    - to: []  # Allow DNS
      ports:
        - protocol: UDP
          port: 53

---
# DENY all ingress to Hub from non-Bridge sources
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: hub-deny-non-bridge
  namespace: sovereign-hub
spec:
  podSelector: {}
  policyTypes:
    - Ingress
  ingress:
    - from:
        - namespaceSelector:
            matchLabels:
              name: sovereign-bridge
```

---

## 4. CI/CD Pipeline

### 4.1 GitHub Actions Workflow

```yaml
# .github/workflows/deploy.yml
name: Deploy

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

env:
  REGISTRY: ghcr.io
  IMAGE_NAME: ${{ github.repository }}

jobs:
  build:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        component: [core, hub, bridge, spokes-internal, spokes-external]
    steps:
      - uses: actions/checkout@v4

      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      - name: Log in to Container Registry
        uses: docker/login-action@v3
        with:
          registry: ${{ env.REGISTRY }}
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Extract metadata
        id: meta
        uses: docker/metadata-action@v5
        with:
          images: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}/${{ matrix.component }}
          tags: |
            type=sha,prefix={{branch}}-
            type=semver,pattern={{version}}
            type=raw,value=latest,enable={{is_default_branch}}

      - name: Build and push
        uses: docker/build-push-action@v5
        with:
          context: .
          file: ./docker/${{ matrix.component }}.Dockerfile
          push: ${{ github.event_name != 'pull_request' }}
          tags: ${{ steps.meta.outputs.tags }}
          labels: ${{ steps.meta.outputs.labels }}
          cache-from: type=gha
          cache-to: type=gha,mode=max

  test:
    needs: build
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install
      - run: vendor/bin/phpunit --testsuite=unit
      - run: vendor/bin/phpunit --testsuite=integration
      - run: vendor/bin/phpstan analyse
      - run: composer audit

  deploy-dev:
    needs: [build, test]
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    environment: development
    steps:
      - uses: actions/checkout@v4

      - name: Configure AWS credentials
        uses: aws-actions/configure-aws-credentials@v4
        with:
          role-to-assume: ${{ secrets.AWS_DEPLOY_ROLE_ARN }}
          aws-region: us-east-1

      - name: Update kubeconfig
        run: aws eks update-kubeconfig --name sovereign-dev

      - name: Deploy to development
        run: |
          kubectl set image deployment/vanguard             vanguard=ghcr.io/${{ github.repository }}/bridge:${{ github.sha }}             -n sovereign-bridge
          kubectl rollout status deployment/vanguard -n sovereign-bridge

      - name: Run smoke tests
        run: |
          curl -f https://dev.sovereign.example/health/ready
          curl -f https://dev.sovereign.example/health/live

  deploy-staging:
    needs: deploy-dev
    runs-on: ubuntu-latest
    environment: staging
    steps:
      - uses: actions/checkout@v4
      - name: Deploy to staging
        run: |
          # Similar to dev but with staging EKS cluster
          echo "Deploying to staging..."

      - name: Run integration tests against staging
        run: |
          BASE_URL=https://staging.sovereign.example             vendor/bin/phpunit --testsuite=e2e

  deploy-production:
    needs: deploy-staging
    runs-on: ubuntu-latest
    environment: production
    steps:
      - uses: actions/checkout@v4
      - name: Deploy to production (canary)
        run: |
          # ArgoCD handles canary deployment
          # This step just triggers the sync
          argocd app sync sovereign-production

      - name: Verify canary health
        run: |
          sleep 60
          curl -f https://sovereign.example/health/ready
```

### 4.2 ArgoCD Application

```yaml
# deploy/pipeline/argocd/sovereign-production.yaml
apiVersion: argoproj.io/v1alpha1
kind: Application
metadata:
  name: sovereign-production
  namespace: argocd
spec:
  project: default
  source:
    repoURL: https://github.com/DGCodeIdeas/DGLab.git
    targetRevision: main
    path: deploy/kubernetes
    helm:
      valueFiles:
        - values-production.yaml
  destination:
    server: https://kubernetes.default.svc
    namespace: sovereign-hub
  syncPolicy:
    automated:
      prune: true
      selfHeal: true
      allowEmpty: false
    syncOptions:
      - CreateNamespace=true
      - PrunePropagationPolicy=foreground
      - PruneLast=true
  retry:
    limit: 5
    backoff:
      duration: 5s
      factor: 2
      maxDuration: 3m
```

---

## 5. Environment Promotion

### 5.1 Promotion Gates

| Gate | Dev → Staging | Staging → Production |
|---|---|---|
| All tests pass | ✅ Auto | ✅ Required |
| Static analysis clean | ✅ Auto | ✅ Required |
| Security scan pass | ✅ Auto | ✅ Required |
| Performance baseline | ❌ N/A | ✅ Required |
| Manual approval | ❌ Auto | ✅ Required (2-person rule) |
| Canary health check | ❌ N/A | ✅ Required (10 min observation) |

### 5.2 Canary Deployment

```yaml
# deploy/pipeline/argocd/canary-analysis.yaml
apiVersion: flagger.app/v1beta1
kind: Canary
metadata:
  name: vanguard
  namespace: sovereign-bridge
spec:
  targetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: vanguard
  service:
    port: 8080
  analysis:
    interval: 1m
    threshold: 5
    maxWeight: 50
    stepWeight: 10
    metrics:
      - name: request-success-rate
        thresholdRange:
          min: 99
        interval: 1m
      - name: request-duration
        thresholdRange:
          max: 500
        interval: 1m
    webhooks:
      - name: load-test
        type: pre-rollout
        url: http://flagger-loadtester.test/
        timeout: 30s
        metadata:
          cmd: "hey -z 2m -q 10 -c 2 http://vanguard-canary:8080/"
      - name: conformance-test
        type: pre-rollout
        url: http://flagger-loadtester.test/
        timeout: 5m
        metadata:
          type: bash
          cmd: "curl -f http://vanguard-canary:8080/health/ready"
```

---

## 6. Secret Management in Production

### 6.1 External Secrets Operator

```yaml
# deploy/kubernetes/base/external-secrets.yaml
apiVersion: external-secrets.io/v1beta1
kind: ExternalSecret
metadata:
  name: vanguard-secrets
  namespace: sovereign-bridge
spec:
  refreshInterval: 1h
  secretStoreRef:
    kind: ClusterSecretStore
    name: aws-secrets-manager
  target:
    name: vanguard-secrets
    creationPolicy: Owner
  data:
    - secretKey: database-url
      remoteRef:
        key: sovereign/production/database
        property: url
    - secretKey: redis-url
      remoteRef:
        key: sovereign/production/redis
        property: url
    - secretKey: jwt-public-key
      remoteRef:
        key: sovereign/production/crypto
        property: jwt-public-key
```

### 6.2 Secret Rotation

```bash
#!/bin/bash
# infrastructure/scripts/rotate-secrets.sh

set -e

ENVIRONMENT=${1:-production}
SERVICE=${2:-all}

echo "Rotating secrets for ${ENVIRONMENT}..."

# Generate new database password
NEW_DB_PASSWORD=$(openssl rand -base64 32)

# Update AWS Secrets Manager
aws secretsmanager put-secret-value     --secret-id sovereign/${ENVIRONMENT}/database     --secret-string "{"password":"${NEW_DB_PASSWORD}","url":"postgresql://user:${NEW_DB_PASSWORD}@db:5432/sovereign"}"

# Trigger rolling restart to pick up new secrets
kubectl rollout restart deployment/vanguard -n sovereign-bridge
kubectl rollout status deployment/vanguard -n sovereign-bridge

echo "Secret rotation complete."
```

---

## 7. Observability Stack

### 7.1 Prometheus ServiceMonitor

```yaml
# deploy/kubernetes/monitoring/servicemonitor.yaml
apiVersion: monitoring.coreos.com/v1
kind: ServiceMonitor
metadata:
  name: sovereign-metrics
  namespace: monitoring
spec:
  selector:
    matchExpressions:
      - key: tier
        operator: In
        values: [hub, bridge, spokes]
  endpoints:
    - port: metrics
      path: /metrics
      interval: 15s
      scrapeTimeout: 10s
```

### 7.2 Grafana Dashboard

```json
{
  "dashboard": {
    "title": "Sovereign Wheel Health",
    "panels": [
      {
        "title": "Pulse Rate (RPM)",
        "targets": [
          {
            "expr": "sum(rate(http_requests_total[1m]))",
            "legendFormat": "Total Pulses/sec"
          }
        ]
      },
      {
        "title": "Pulse Depth Distribution",
        "targets": [
          {
            "expr": "sum(rate(pulse_depth_total[1m])) by (depth)",
            "legendFormat": "Depth {{depth}}"
          }
        ]
      },
      {
        "title": "Error Rate by Layer",
        "targets": [
          {
            "expr": "sum(rate(http_requests_total{status=~"5.."}[1m])) by (tier)",
            "legendFormat": "{{tier}} Errors/sec"
          }
        ]
      },
      {
        "title": "Cache Hit Rate",
        "targets": [
          {
            "expr": "sum(rate(cache_hits_total[1m])) / sum(rate(cache_requests_total[1m]))",
            "legendFormat": "Hit Rate"
          }
        ]
      }
    ]
  }
}
```

### 7.3 Alertmanager Rules

```yaml
# deploy/kubernetes/monitoring/alerts.yaml
groups:
  - name: sovereign
    rules:
      - alert: HighErrorRate
        expr: sum(rate(http_requests_total{status=~"5.."}[5m])) / sum(rate(http_requests_total[5m])) > 0.05
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "High error rate detected"
          description: "Error rate is {{ $value | humanizePercentage }} over the last 5 minutes"

      - alert: BridgeReplicaDown
        expr: kube_deployment_status_replicas_available{deployment="vanguard"} < 3
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: "Vanguard replica down"

      - alert: DatabaseConnectionPoolExhausted
        expr: sovereign_db_pool_active_connections / sovereign_db_pool_max_connections > 0.9
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Database connection pool near exhaustion"

      - alert: AuditChainBreak
        expr: sovereign_audit_chain_integrity == 0
        for: 0s
        labels:
          severity: critical
        annotations:
          summary: "AUDIT CHAIN BROKEN — potential tampering detected"
```

---

## 8. Disaster Recovery

### 8.1 Runbook: Complete Region Failure

```
SCENARIO: AWS us-east-1 complete outage

STEP 1: Activate standby region
    $ aws route53 change-resource-record-sets         --hosted-zone-id Z123456789         --change-batch file://failover-to-standby.json

STEP 2: Verify standby EKS cluster
    $ kubectl --context=sovereign-standby get nodes
    $ kubectl --context=sovereign-standby get pods --all-namespaces

STEP 3: Promote RDS read replica to primary
    $ aws rds promote-read-replica         --db-instance-identifier sovereign-standby-primary

STEP 4: Verify data consistency
    $ php bin/console audit:verify-chain
    $ php bin/console health:check --all

STEP 5: Notify stakeholders
    $ php bin/console notify:send         --channel=ops         --message="Failover to standby complete. RTO: XX minutes."

STEP 6: Post-incident review
    - Document actual RTO/RPO
    - Identify gaps
    - Update runbook
```

### 8.2 Backup Verification

```yaml
# CronJob: Daily backup verification
apiVersion: batch/v1
kind: CronJob
metadata:
  name: backup-verification
  namespace: sovereign-hub
spec:
  schedule: "0 6 * * *"
  jobTemplate:
    spec:
      template:
        spec:
          containers:
            - name: verifier
              image: ghcr.io/dgcodeideas/sovereign-tools:backup-verifier
              command:
                - /bin/sh
                - -c
                - |
                  echo "Restoring latest backup to verification instance..."
                  aws rds restore-db-instance-to-point-in-time                     --source-db-instance-identifier sovereign-primary                     --target-db-instance-identifier sovereign-verify-$(date +%s)                     --restore-time $(date -d '1 hour ago' -Iseconds)

                  echo "Running smoke tests..."
                  php /app/bin/console health:check --database=sovereign-verify

                  echo "Cleaning up..."
                  aws rds delete-db-instance                     --db-instance-identifier sovereign-verify-$(date +%s)                     --skip-final-snapshot
          restartPolicy: OnFailure
```

---

## 9. Operational Runbooks

### 9.1 Scaling Runbook

```bash
#!/bin/bash
# Scale Hub services horizontally

COMPONENT=${1}
REPLICAS=${2}
ENVIRONMENT=${3:-production}

echo "Scaling ${COMPONENT} to ${REPLICAS} replicas in ${ENVIRONMENT}..."

kubectl scale deployment/${COMPONENT}   --replicas=${REPLICAS}   -n sovereign-hub

kubectl rollout status deployment/${COMPONENT} -n sovereign-hub

echo "Scale complete."
```

### 9.2 Log Investigation Runbook

```bash
#!/bin/bash
# Investigate errors for a specific Pulse

PULSE_ID=${1}

echo "Investigating Pulse ${PULSE_ID}..."

# Collect logs from all tiers
kubectl logs -l tier=bridge --all-containers | grep "${PULSE_ID}"
kubectl logs -l tier=hub --all-containers | grep "${PULSE_ID}"
kubectl logs -l tier=spokes --all-containers | grep "${PULSE_ID}"

# Collect traces from Jaeger
jaeger-query --trace-id=${PULSE_ID}

# Collect audit entries
php bin/console audit:search --pulse-id=${PULSE_ID}
```

---

*End of Structure 08: Deployment & Infrastructure Architecture*
