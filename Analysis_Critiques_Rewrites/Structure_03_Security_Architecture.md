# DGLab Wheel Architecture
## Structure 03: Security & Trust Architecture

> **Repository:** https://github.com/DGCodeIdeas/DGLab  
> **Framework:** Custom PHP MVC Framework  
> **Pattern:** Concentric Wheel with Zero-Exposure Trust Model

---

## 1. The Trust Model

In the DGLab wheel, **trust is not assumed at any layer**. It is established at the Outer Rim and cryptographically propagated inward. Every layer verifies the layer outside it before accepting the Pulse.

```
Trust Flow (Inbound):
    Entity ──► CDN (TLS cert trust)
                  │
                  └──► BRIDGE-01 (JWT signature trust)
                            │
                            └──► Hub Services (Scope + Role trust)
                                      │
                                      └──► Core (No trust — pure execution)
```

```
Trust Flow (Outbound):
    Core ──► Hub (Data integrity via type safety)
               │
               └──► Spoke (Tenant isolation enforced)
                         │
                         └──► BRIDGE-01 (Response signing optional)
                                   │
                                   └──► Entity (TLS + content integrity)
```

### 1.1 The Zero-Exposure Principle

> **No Internal Spoke, Hub service, or Core primitive is directly reachable from the public internet.**

The Inner Rim (BRIDGE-01) is the **only** publicly exposed component. All traffic — legitimate or malicious — terminates at the Rim. The Rim decides:
- Is this Pulse authenticated?
- Is this Pulse authorized for the requested route?
- Is this Pulse within rate limits?
- Is this Pulse's tenant active and valid?

Only if all checks pass does the Pulse enter the wheel.

---

## 2. Authentication & Identity Flow

### 2.1 The JWT as a Trust Passport

Every authenticated Pulse carries a JWT signed with `CORE-16` (Ed25519). This JWT is the **trust passport** that every inner layer inspects.

```php
// JWT Payload Structure (registered + custom claims)
{
  "sub": "01ARZ3NDEKTSV4RRFFQ69G5FAV",      // User ULID
  "tid": "01ARZ3NDEKTSV4RRFFQ69G5FAV",      // Tenant ULID
  "iss": "sovereign-hub-identity",           // Issuer (HUB-04)
  "aud": "sovereign-bridge-vanguard",        // Audience (BRIDGE-01)
  "iat": 1722633600,
  "exp": 1722637200,                         // 1 hour expiry
  "jti": "01J4K...",                         // Unique token ID (for revocation)
  "scope": "user:read user:write cms:read",  // OAuth scopes
  "role": "admin",                           // RBAC role
  "mfa_verified": true,                      // MFA gate passed
  "session_id": "sess_01J4K..."              // Session binding
}
```

### 2.2 Authentication Phases

```
Phase 1: TLS (Outer Rim)
    Entity ──► CDN ──► BRIDGE-01
    Verification: Certificate chain, TLS 1.3, SNI
    Trust established: Transport is encrypted

Phase 2: JWT Verification (Inner Rim)
    BRIDGE-01 ──► CORE-16::verify()
    Verification: Signature valid? Not expired? Audience correct? Issuer trusted?
    Trust established: Token is authentic and unmodified

Phase 3: Tenant Resolution (Inner Rim)
    BRIDGE-01 ──► HUB-21 (Tenancy)
    Verification: Tenant active? Not suspended? Origin IP allowed?
    Trust established: Tenant context is valid

Phase 4: Scope & Role Verification (Spoke Entry)
    Spoke Controller ──► HUB-05 (RBAC)
    Verification: Does user have required scope for this route?
    Trust established: User is authorized for this specific action

Phase 5: Resource Ownership (Hub Execution)
    Hub Service ──► HUB-21 (TenantScope)
    Verification: Does requested resource belong to user's tenant?
    Trust established: User cannot access cross-tenant data
```

### 2.3 Token Lifecycle

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         TOKEN STATE MACHINE                                 │
│                                                                             │
│   [Issued] ──► [Active] ──► [Refreshed] ──► [Active] ──► [Expired]        │
│       │           │              │                          │               │
│       │           │              │                          ▼               │
│       │           │              │                      [Revoked]            │
│       │           │              │                          │               │
│       │           │              └──────────────────────────┘               │
│       │           │                    (explicit logout / security event)   │
│       │           │                                                         │
│       │           └──► [Used] ──► [Validated by BRIDGE-01]                  │
│       │                                                                     │
│       └──► Stored in HUB-02 (Redis) with TTL = exp - iat                   │
│            Revocation list checked on every validation                      │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Revocation:** When a user logs out or a security event occurs, the `jti` (token ID) is added to a revocation list in `HUB-02` with TTL equal to the token's remaining lifetime. BRIDGE-01 checks this list on every request.

---

## 3. Authorization by Layer

### 3.1 BRIDGE-01 (Inner Rim) — Route-Level Authorization

BRIDGE-01 enforces **coarse-grained** authorization: which tenants and roles may enter which spoke categories.

```php
// BRIDGE-01 route authorization matrix
$routePolicies = [
    '/admin/*'     => ['role' => 'super_admin', 'mfa' => true],
    '/staff/*'     => ['role' => ['admin', 'support', 'developer'], 'mfa' => false],
    '/api/public/*'=> ['scope' => 'public', 'auth' => 'optional'],
    '/api/private/*'=> ['scope' => 'user:read', 'auth' => 'required'],
    '/account/*'   => ['scope' => 'user:read', 'auth' => 'required'],
    '/webhook/*'   => ['auth' => 'hmac'], // No JWT — HMAC signature instead
];
```

**Key rule:** BRIDGE-01 never enforces **resource-level** authorization ("can this user edit *this specific* document?"). That is the Spoke's responsibility.

### 3.2 Spoke Level — Resource-Level Authorization

Spokes enforce **fine-grained** authorization using `HUB-05` (RBAC) with policy objects.

```php
// ESPOKE-03 (Account Hub) — user can only edit their own profile
final class ProfilePolicy
{
    public function update(User $user, Profile $profile): bool
    {
        return $user->id === $profile->userId;
    }
}

// ISPOKE-01 (Admin Panel) — super_admin can impersonate, but not other super_admins
final class ImpersonationPolicy
{
    public function impersonate(User $agent, User $target): bool
    {
        return $agent->hasRole('super_admin')
            && !$target->hasRole('super_admin'); // Ring-fencing
    }
}
```

### 3.3 Hub Level — Service-Level Authorization

Hub services enforce **service-level** authorization: which scopes may call which operations.

```php
// HUB-04 (Identity) — scope-gated operations
interface IdentityServiceInterface
{
    #[RequiredScope('user:read')]
    public function findUser(string $userId): ?User;

    #[RequiredScope('user:write')]
    public function updateUser(string $userId, array $data): User;

    #[RequiredScope('admin:impersonate')]
    public function createImpersonationSession(string $agentId, string $targetId): ImpersonationSession;
}
```

### 3.4 Core Level — No Authorization

Core primitives have **no authorization logic**. They execute what they are told. If `CORE-19` receives a `DELETE FROM users` query, it executes it. The safety guard is at the Hub layer (parameterized queries, tenant scoping).

---

## 4. Tenant Isolation Architecture

Tenant isolation is the **primary security guarantee** of the Hub tier. A Pulse from Tenant A must never access Tenant B's data.

### 4.1 Isolation Layers

```
Layer 1: Database (Row-Level)
    Every tenant-scoped table has `tenant_id CHAR(26)`.
    Every query automatically includes `WHERE tenant_id = ?`.
    The `?` is injected by the Hub service proxy, not the Spoke.

Layer 2: Cache (Key Prefixing)
    Cache keys are prefixed: "tenant:{tenantId}:user:{userId}".
    A cache lookup without a tenant prefix returns null.

Layer 3: Search (Index Segregation)
    Each tenant has a dedicated search index or index alias.
    Cross-tenant search queries are rejected at the Hub layer.

Layer 4: Storage (Path Prefixing)
    Cloud storage paths are prefixed: "tenants/{tenantId}/uploads/{fileId}".
    Signed URLs include the tenant prefix in the signature.

Layer 5: Queue (Queue Namespacing)
    Queue names include tenant: "tenant.{tenantId}.emails".
    Workers process per-tenant queues with tenant context restored.
```

### 4.2 The TenantScope Enforcement

```php
final class TenantScope
{
    public function __construct(
        private ?string $currentTenantId = null,
    ) {}

    public function set(string $tenantId): void
    {
        if ($this->currentTenantId !== null && $this->currentTenantId !== $tenantId) {
            throw new SecurityException('Tenant context cannot be changed once set');
        }
        $this->currentTenantId = $tenantId;
    }

    public function current(): ?string
    {
        return $this->currentTenantId;
    }

    public function assertBelongs(string $resourceTenantId): void
    {
        if ($this->currentTenantId === null) {
            throw new SecurityException('No tenant context — super_admin must explicitly set tenant');
        }
        if ($this->currentTenantId !== $resourceTenantId) {
            throw new SecurityException('Cross-tenant access denied');
        }
    }

    public function isSuperAdminContext(): bool
    {
        return $this->currentTenantId === null;
    }
}
```

**Super Admin Exception:** `super_admin` users operate with `tenantId = null`. They may access any tenant, but every access is logged as a high-severity audit event (`HUB-06`). This is the **only** exception to tenant isolation.

---

## 5. The Audit Chain as a Security Primitive

`HUB-06` (Audit) is not merely a compliance tool — it is a **security primitive**. The cryptographic hash chain ensures that if an attacker gains database access, they cannot modify audit records without detection.

### 5.1 Tamper Evidence

```
Entry 1:  hash = SHA-256("1||null||system.boot||system||{}||{}||genesis")
Entry 2:  hash = SHA-256("2||tenant_a||user.login||user_123||{}||{ip:...}||<entry_1_hash>")
Entry 3:  hash = SHA-256("3||tenant_a||user.update||user_123||{before..., after...}||{ip:...}||<entry_2_hash>")
```

If an attacker modifies Entry 2, Entry 3's `prev_hash` no longer matches. `verifyChain()` detects the break at Entry 2.

### 5.2 Audit as a Canary

If an attacker deletes audit records entirely, the gap is detected by:
1. **Sequence gaps:** `seq` numbers are monotonic per tenant. A missing `seq` indicates deletion.
2. **Cross-reference:** Hub services log operational events to `CORE-09` independently. A missing `HUB-06` audit for a known `CORE-09` log entry indicates tampering.
3. **External backup:** Audit logs are streamed to an external SIEM in real-time (`ISPOKE-10` SOC integration).

### 5.3 Security-Critical Audit Events

| Event | Severity | Auto-Action |
|---|---|---|
| `user.login.failure` | Low | None (normal) |
| `user.login.failure` × 5 / 5 min | High | `HUB-07` rate limit + `HUB-12` alert |
| `user.login.success` from new IP | Medium | `HUB-12` email notification |
| `admin.impersonation.start` | Critical | `HUB-12` SMS to target user |
| `config.critical.change` | Critical | `HUB-12` alert to all super_admins |
| `cross_tenant.access_attempt` | Critical | `HUB-30` kill switch consideration |
| `audit.chain.break` | Critical | `HUB-30` global kill switch + `ISPOKE-10` page |

---

## 6. The Kill Switch (HUB-30)

`HUB-30` is the **emergency brake** of the wheel. It operates at the Hub layer and can stop Pulses from propagating.

### 6.1 Kill Switch Modes

| Mode | Scope | Effect |
|---|---|---|
| `SOFT` | Per-service | Stop new Pulses to a specific Hub service; allow in-flight to complete |
| `HARD` | Per-service | Immediately terminate all Pulses to a service; drop connections |
| `TENANT` | Per-tenant | Reject all Pulses for a specific tenant (e.g., non-payment, abuse) |
| `GLOBAL` | All | Reject all inbound Pulses at BRIDGE-01; return `503` |

### 6.2 Activation Triggers

```php
// Automatic triggers
if ($failedLoginRate > 1000 / min)     → SOFT on HUB-04 (Identity)
if ($auditChainBreakDetected)          → GLOBAL
if ($crossTenantAccessDetected)        → HARD on offending service
if ($DDoSVolume > threshold)           → TENANT on suspected tenant

// Manual triggers (ISPOKE-15 Control Tower)
Super admin clicks "Emergency Stop" → GLOBAL
Super admin clicks "Suspend Tenant" → TENANT
```

### 6.3 Kill Switch Propagation

```
ISPOKE-15 (Control Tower)
    │
    └──► HUB-01 (Config) — writes kill switch state
             │
             └──► HUB-16 (Orchestration) — pushes to all BRIDGE-01 replicas
                      │
                      └──► BRIDGE-01 — rejects matching Pulses
                               │
                               └──► HUB-15 (Health) — reports degraded status
```

**Propagation SLA:** Global kill switch must propagate to all BRIDGE-01 replicas within **5 seconds**.

---

## 7. Network Security

### 7.1 The Three-Subnet Model

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         NETWORK TOPOLOGY                                    │
│                                                                             │
│   ┌──────────────┐                                                          │
│   │   INTERNET   │                                                          │
│   └──────┬───────┘                                                          │
│          │ 443                                                              │
│          ▼                                                                  │
│   ┌──────────────┐     ┌──────────────┐     ┌──────────────┐               │
│   │  PUBLIC NET  │     │  PRIVATE NET │     │ ISOLATED NET │               │
│   │  (0.0.0.0/0) │     │ (10.0.1.0/24)│     │ (10.0.2.0/24)│               │
│   │              │     │              │     │              │               │
│   │  CDN / LB    │────►│  BRIDGE-01   │────►│  Hub Services│               │
│   │  ESPOKE pods │     │  (3 replicas)│     │  ISPOKE pods │               │
│   │              │     │              │     │  Datastores  │               │
│   └──────────────┘     └──────────────┘     └──────────────┘               │
│                                                                             │
│   Rules:                                                                    │
│   • Public → Private: Only 443 from CDN/LB to BRIDGE-01                    │
│   • Private → Isolated: Only BRIDGE-01 to Hub (port 8080)                  │
│   • Isolated → Private: None (no return path for data)                     │
│   • Public → Isolated: DENIED (Zero-Exposure enforced at network layer)    │
│   • ESPOKE → Hub direct: DENIED (must go through BRIDGE-01)                │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 7.2 mTLS (Mutual TLS) for Internal Communication

Between Hub services, all communication uses **mTLS**:
- Each Hub service has a client certificate issued by the internal CA
- Services verify each other's certificates on connection
- Certificate rotation happens automatically via `HUB-20` (Vault) + `DEPLOY-04`

---

## 8. Secret Management

### 8.1 Secret Tiers

| Tier | Examples | Storage | Rotation |
|---|---|---|---|
| **Runtime** | DB passwords, Redis auth, API keys | `HUB-20` (Vault) + env vars | Monthly |
| **Application** | JWT signing keys, encryption keys | `CORE-16` KeyManager + Vault | Quarterly |
| **Infrastructure** | AWS credentials, TLS private keys | Vault / AWS Secrets Manager | On-demand |
| **Build-time** | GitHub tokens, registry credentials | CI secrets | Per-job |

### 8.2 Secret Injection at Boot

```
Container Start
    │
    ├──► Read env vars (injected by K8s/Docker from Vault)
    │
    ├──► CORE-10 (Config) loads secrets into memory
    │         └── Secrets are marked as `sensitive` — never logged
    │
    ├──► CORE-16 (Crypto) loads signing keys from Vault
    │         └── Keys are held in memory only; never written to disk
    │
    └──► Container ready to serve Pulses
```

**Rule:** No secret is ever committed to Git. No secret is ever logged. No secret is ever returned in an API response.

---

## 9. Security Testing by Wheel Layer

| Layer | Test Type | Frequency | Tooling |
|---|---|---|---|
| Outer Rim | WAF rule validation | Per deploy | OWASP ZAP |
| Outer Rim | TLS configuration | Weekly | SSL Labs API |
| BRIDGE-01 | JWT forgery attempt | Per commit | Custom test suite |
| BRIDGE-01 | Rate limit bypass | Per commit | k6 load test |
| Hub | Cross-tenant access | Per commit | Parameterized integration tests |
| Hub | SQL injection | Per commit | PHPStan + custom rules |
| Core | Crypto implementation | Per commit | libsodium test vectors |
| Core | Memory safety | Per commit | Valgrind (CI) |
| All | Dependency audit | Daily | Composer audit + Snyk |
| All | Container scan | Per build | Trivy |
| All | Penetration test | Quarterly | External firm |

---

*End of Structure 03: Security & Trust Architecture*
