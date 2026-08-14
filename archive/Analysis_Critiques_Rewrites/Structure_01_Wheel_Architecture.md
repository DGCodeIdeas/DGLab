# DGLab Wheel Architecture
## Structure 01: Application Structure

> **Repository:** https://github.com/DGCodeIdeas/DGLab  
> **Framework:** Custom PHP MVC Framework  
> **Pattern:** Concentric Wheel with Pulse Flow

---

## 1. The Wheel Metaphor

The DGLab system is architected as a **wheel** — not a flat layered cake. This is a living, rotating structure where energy (requests, data, events) flows radially.

```
    OUTER RIM  ←── Entity touches here
       │
       ▼
  Outer Spokes  (Thin)  ←── ESPOKEs — public-facing
       │
       ▼
   INNER RIM  ←── BRIDGE-01 Vanguard — the gate
       │
       ▼
  Inner Spokes  (Thick) ←── ISPOKEs — staff/internal
       │
       ▼
      HUB  ←── Shared Services Ring
       │
       ▼
     CORE  ←── The Nucleus
       │
       ▼
   DEPLOY  ←── The Frame (holds everything)
```

### Why a Wheel?

| Layer | Metaphor | Reality |
|---|---|---|
| **Core** | The axle/nucleus | Foundational PHP primitives — DI, events, HTTP messages, routing. Zero business logic. |
| **Hub** | The inner ring | Shared services consumed by all spokes — identity, cache, audit, queue, search. |
| **Inner Spokes** | Thick structural spokes | Heavy-duty staff applications (Admin, SOC, Ops, Content Studio). Rich UI, deep Hub access. |
| **Inner Rim** | The boundary ring | BRIDGE-01 (Vanguard) — the single gateway that separates internal from external. |
| **Outer Spokes** | Thin spokes | Lightweight public surfaces (CMS, API, Forum, Status Page). Minimal logic, mostly presentation. |
| **Outer Rim** | The tire edge | CDN, DNS, TLS termination, edge cache — where entities (users, bots, IoT devices) physically touch. |
| **Deploy** | The frame, bearings, road | Terraform, K8s, CI/CD — the physical infrastructure that lets the wheel spin. |

---

## 2. The Pulse — Request Flow Mechanics

> *"An Entity touches the Outer Rim, a Pulse flows towards the core, how deep it goes depends, then flows back to the Outer Rim."*

A **Pulse** is any unit of work: an HTTP request, a queue job, a scheduled task, an event broadcast. It always follows this radial path:

```
Entity ──► Outer Rim ──► Outer Spoke ──► Inner Rim ──► [Inner Spoke] ──► Hub ──► Core
                                                                              │
                                                                              ▼
Entity ◄── Outer Rim ◄── Outer Spoke ◄── Inner Rim ◄── [Inner Spoke] ◄── Hub ◄── Core
```

### 2.1 Depth of Penetration

Not every Pulse reaches the Core. The depth depends on the work being done:

| Depth | Layers Touched | Example |
|---|---|---|
| **Surface** | Outer Rim → Outer Spoke → Inner Rim → *cached response* | Static CMS page served from CDN cache. Pulse never enters the Hub. |
| **Shallow** | Outer Rim → Outer Spoke → Inner Rim → Hub (Cache hit) | User profile read — data in `HUB-02` Redis. No DB query. |
| **Medium** | Outer Rim → Outer Spoke → Inner Rim → Hub → Core (DBAL query) | Forum post creation — writes to `CORE-19` DB, fires `HUB-09` event. |
| **Deep** | Full stack + Inner Spoke | Admin panel user impersonation — traverses entire wheel, audited at every layer. |
| **System** | Core → Hub → *broadcast* | `HUB-24` scheduled job — originates at Core, fans out to Hub services, no Rim touch. |

### 2.2 Pulse Directionality

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        INBOUND PULSE (Request)                              │
│                                                                             │
│   Entity          Outer Rim        Outer Spoke      Inner Rim               │
│     │                │                  │              │                     │
│     │  HTTPS 443     │   Route match    │   JWT verify │                     │
│     ├───────────────►├─────────────────►├─────────────►│                     │
│     │                │                  │              │                     │
│     │                │                  │              │   Rate limit        │
│     │                │                  │              │   Tenant resolve    │
│     │                │                  │              │   Scope check       │
│     │                │                  │              │                     │
│                        ◄─── 401/403/429 if rejected ───                     │
│                                                                             │
│   [If accepted]                                                             │
│     │                │                  │              │                     │
│     │                │                  │              ├──────────► Inner Spoke (ISPOKE)
│     │                │                  │              │              │       │
│     │                │                  │              │              │   Auth check
│     │                │                  │              │              │   Role check
│     │                │                  │              │              │       │
│     │                │                  │              │              └──► Hub
│     │                │                  │              │                   │
│     │                │                  │              │              ┌────┴────┐
│     │                │                  │              │              │  Core   │
│     │                │                  │              │              │ DBAL    │
│     │                │                  │              │              │ Crypto  │
│     │                │                  │              │              └────┬────┘
│     │                │                  │              │                   │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                       OUTBOUND PULSE (Response)                             │
│                                                                             │
│   Core ──► Hub ──► [Inner Spoke] ──► Inner Rim ──► Outer Spoke ──► Entity  │
│                                                                             │
│   DB result      Event fired        Audit logged      Response built        │
│   Cache warmed   Notification       Transform          CDN cache            │
│   Log written    queued             applied            header set           │
│                                                                             │
│   Each layer may add metadata to the Pulse as it returns outward:           │
│   • Core: query timing, connection ID                                       │
│   • Hub: cache warming instructions, event dispatch list                    │
│   • Inner Rim: audit entry ID, rate-limit remaining                         │
│   • Outer Spoke: response headers, ETag, Cache-Control                      │
│   • Outer Rim: edge cache TTL, compression                                  │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Directory Structure — Wheel Mapped to Filesystem

```
DGLab/
├── 📁 core/                          ★ CORE — The Nucleus
│   ├── src/
│   │   ├── Container/                CORE-02  DI Container
│   │   │   ├── Container.php
│   │   │   ├── ContainerInterface.php
│   │   │   └── CompilerPassInterface.php
│   │   ├── Kernel/                   CORE-18  Kernel & Lifecycle
│   │   │   ├── Kernel.php
│   │   │   ├── KernelInterface.php
│   │   │   └── Environment.php
│   │   ├── Http/                     CORE-04, CORE-05, CORE-06
│   │   │   ├── Message/              PSR-7 implementations
│   │   │   ├── Middleware/           PSR-15 pipeline
│   │   │   └── Routing/              Attribute router
│   │   ├── Config/                   CORE-10  Config Loader
│   │   ├── Logging/                  CORE-09  Logger
│   │   ├── ErrorHandler/             CORE-08  Error Handler
│   │   ├── EventDispatcher/          CORE-03  Event Dispatcher
│   │   ├── Crypto/                   CORE-16  Crypto Envelope
│   │   ├── Database/                 CORE-19  DBAL
│   │   └── Providers/                CORE-17  Service Provider System
│   ├── tests/
│   └── composer.json
│
├── 📁 hub/                           ★ HUB — Shared Services Ring
│   ├── src/
│   │   ├── Config/                   HUB-01  Config & Flags
│   │   ├── Cache/                    HUB-02  Cache
│   │   ├── Identity/                 HUB-04  Identity
│   │   ├── RBAC/                     HUB-05  RBAC
│   │   ├── Audit/                    HUB-06  Audit
│   │   ├── RateLimiter/              HUB-07  Rate Limiter
│   │   ├── Gateway/                  HUB-08  Gateway
│   │   ├── EventBus/                 HUB-09  Event Bus
│   │   ├── Queue/                    HUB-10  Queue
│   │   ├── CloudStorage/             HUB-11  Cloud Storage
│   │   ├── Notify/                   HUB-12  Notify
│   │   ├── Search/                   HUB-14  Search
│   │   ├── Health/                   HUB-15  Health
│   │   ├── Orchestration/            HUB-16  Orchestration
│   │   ├── Scheduler/                HUB-24  Scheduler
│   │   ├── Vault/                    HUB-20  Vault
│   │   ├── Billing/                  HUB-22  Billing
│   │   ├── Reporter/                 HUB-23  Reporter
│   │   └── ... (remaining Hub services)
│   ├── tests/
│   └── composer.json
│
├── 📁 bridge/                        ★ INNER RIM — The Gate
│   ├── src/
│   │   └── Vanguard/                 BRIDGE-01  Vanguard
│   │       ├── Vanguard.php
│   │       ├── JwtAuthMiddleware.php
│   │       ├── RateLimitMiddleware.php
│   │       ├── TenantContextMiddleware.php
│   │       └── ZeroExposureTest.php
│   ├── tests/
│   └── composer.json
│
├── 📁 spokes/
│   ├── 📁 internal/                  ★ INNER SPOKES — Thick (Staff)
│   │   ├── admin/                    ISPOKE-01  Admin Panel
│   │   ├── devportal/                ISPOKE-02  Developer Portal
│   │   ├── healthdash/               ISPOKE-03  Health Dashboard
│   │   ├── staffid/                  ISPOKE-04  Staff Identity
│   │   ├── insight/                  ISPOKE-05  Insight Analytics
│   │   ├── apimgmt/                  ISPOKE-06  API Management
│   │   ├── studio/                   ISPOKE-07  Content Studio
│   │   ├── support/                  ISPOKE-08  Support Desk
│   │   ├── codex/                    ISPOKE-09  Knowledge Base
│   │   ├── sentinel/                 ISPOKE-10  SOC Dashboard
│   │   ├── ops/                      ISPOKE-11  Ops Center
│   │   ├── forge/                    ISPOKE-12  Data Forge
│   │   ├── qualab/                   ISPOKE-13  QA Lab
│   │   ├── media/                    ISPOKE-14  Media Vault
│   │   ├── tower/                    ISPOKE-15  Control Tower
│   │   ├── comms/                    ISPOKE-16  Comms Hub
│   │   ├── incident/                 ISPOKE-17  Incident Command
│   │   ├── labs/                     ISPOKE-18  Feature Labs
│   │   ├── vaultops/                 ISPOKE-19  Vault Ops
│   │   ├── compliance/               ISPOKE-20  Compliance
│   │   ├── partners/                 ISPOKE-21  Partners
│   │   ├── finops/                   ISPOKE-22  FinOps
│   │   ├── flow/                     ISPOKE-23  Flow Studio
│   │   ├── catalog/                  ISPOKE-24  Data Catalog
│   │   └── mobileops/                ISPOKE-25  Mobile Ops
│   │
│   └── 📁 external/                  ★ OUTER SPOKES — Thin (Public)
│       ├── canvas/                   ESPOKE-01  Public CMS
│       ├── api/                      ESPOKE-02  Public API
│       ├── account/                  ESPOKE-03  Account Hub
│       ├── devhub/                   ESPOKE-04  Dev Hub
│       ├── exchange/                 ESPOKE-05  Exchange
│       ├── discover/                 ESPOKE-06  Discover
│       ├── forum/                    ESPOKE-07  Forum
│       ├── status/                   ESPOKE-08  Status Page
│       ├── bulletin/                 ESPOKE-09  Bulletin
│       ├── mobileapi/                ESPOKE-10  Mobile API
│       ├── hooks/                    ESPOKE-11  Webhook Mgmt
│       ├── toolkit/                  ESPOKE-12  Toolkit
│       ├── integrate/                ESPOKE-13  Integrate
│       ├── metrics/                  ESPOKE-14  Public Metrics
│       └── voice/                    ESPOKE-15  Voice
│
├── 📁 deploy/                        ★ DEPLOY — The Frame
│   ├── infrastructure/
│   │   ├── terraform/                DEPLOY-02  Datastores
│   │   ├── kubernetes/               DEPLOY-01, DEPLOY-03
│   │   └── pipeline/                 DEPLOY-04  Promotion Pipeline
│   └── scripts/
│
├── 📁 loom/                          CORE-01  Polyrepo Orchestrator (Build-time)
│   ├── src/
│   └── composer.json
│
├── 📁 public/                        ★ OUTER RIM — Entity Touch-Point
│   └── index.php                     Single entry point
│
├── 📁 bin/                           CLI entry points
│   ├── console                       Admin CLI
│   └── worker                        Queue worker daemon
│
├── 📁 config/                        Global configuration
│   ├── app.php
│   ├── database.php
│   ├── cache.php
│   └── providers.php
│
├── 📁 storage/                       Runtime storage
│   ├── logs/
│   ├── cache/
│   ├── sessions/
│   └── uploads/
│
├── 📁 tests/                         Cross-cutting integration tests
│   ├── e2e/
│   ├── integration/
│   └── fixtures/
│
├── composer.json                     Root orchestrator
└── README.md
```

---

## 4. Pulse Flow Examples

### Example A: Surface Pulse — Cached CMS Page

```
User ──► CDN (Outer Rim)
            │
            ├── Cache HIT ──► Return HTML
            │
            └── Cache MISS ──► ESPOKE-01 (Outer Spoke)
                                   │
                                   ├── HUB-02 (Cache check — miss)
                                   │
                                   └── HUB-14 (Search index lookup)
                                            │
                                            └── Return content
                                                    │
                                                    └──► HUB-02 (Cache warm)
                                                    └──► CDN (Cache populate)
                                                    └──► User
```

**Depth:** Surface. Never touches Core DBAL. Fastest path.

---

### Example B: Medium Pulse — User Login

```
User ──► CDN ──► BRIDGE-01 (Inner Rim)
                    │
                    ├── Rate limit check (HUB-07)
                    ├── JWT validation (HUB-04)
                    └── Tenant resolution (HUB-21)
                         │
                         └── ESPOKE-03 (Outer Spoke — Account Hub)
                                  │
                                  └── HUB-04 (Identity — credential verify)
                                           │
                                           └── CORE-16 (Password hash verify)
                                           └── CORE-19 (DB query)
                                           │
                                           └── HUB-06 (Audit: login.success)
                                           └── HUB-12 (Notify: new device email)
                                           └── HUB-02 (Cache: session token)
                                           │
                                           └── Response: JWT + refresh token
```

**Depth:** Medium. Touches Core (DBAL, Crypto) but no Inner Spoke.

---

### Example C: Deep Pulse — Admin Impersonation

```
Staff ──► CDN ──► BRIDGE-01 (Inner Rim)
                     │
                     ├── JWT + MFA validation (HUB-04)
                     ├── Role check: super_admin (HUB-05)
                     └── Tenant context: null (ISPOKE-01 privilege)
                          │
                          └── ISPOKE-01 (Inner Spoke — Admin Panel)
                                   │
                                   ├── ISPOKE-08 (Support Desk — ticket context)
                                   │        └── HUB-04 (User lookup)
                                   │
                                   └── HUB-04 (Impersonation session create)
                                            │
                                            └── CORE-16 (Session token encrypt)
                                            └── CORE-19 (Audit log write)
                                            │
                                            └── HUB-06 (Audit: impersonation.start)
                                            └── HUB-12 (Notify: user alerted)
                                            │
                                            └── Response: Impersonation JWT
```

**Depth:** Deep. Full stack traversal. Maximum audit coverage.

---

### Example D: System Pulse — Scheduled Backup Job

```
HUB-24 (Scheduler) ──► HUB-10 (Queue)
                            │
                            └── Worker process
                                     │
                                     └── ISPOKE-19 (Vault Ops — backup trigger)
                                              │
                                              └── DEPLOY-02 API (infrastructure)
                                                       │
                                                       └── AWS RDS snapshot
                                                       │
                                                       └── Response: snapshot ID
                                                                │
                                                                └── HUB-06 (Audit)
                                                                └── HUB-12 (Alert)
                                                                └── HUB-23 (Report)
```

**Depth:** System. No Rim touch. Originates at Hub, touches Deploy.

---

## 5. The Frame — Deploy as Infrastructure

The wheel does not float. It is held by a **frame** that provides:

| Deploy Component | Frame Function | Wheel Equivalent |
|---|---|---|
| `DEPLOY-02` Datastores | The axle bearings | Smooth rotation (DB connections) |
| `DEPLOY-01` Hub Deployment | The hub mount | Keeps the Hub ring centered |
| `DEPLOY-03` Edge Deployment | The rim clamps | Secures the Outer Rim to the road |
| `DEPLOY-04` Promotion Pipeline | The maintenance lift | Lifts the wheel, swaps parts safely |

```
        ┌─────────────────────────────────────┐
        │           CLOUD PROVIDER            │
        │  ┌───────────────────────────────┐  │
        │  │      DEPLOY-02 Datastores     │  │
        │  │   RDS Primary ◄──► Replica    │  │
        │  │   Redis Cluster               │  │
        │  └───────────────────────────────┘  │
        │              ▲                      │
        │         ┌────┴────┐                 │
        │         │  HUB    │                 │
        │         │ Services│                 │
        │         └────┬────┘                 │
        │              │                      │
        │    ┌─────────┴─────────┐            │
        │    │   BRIDGE-01       │            │
        │    │   (Load Balancer) │            │
        │    └─────────┬─────────┘            │
        │              │                      │
        │    ┌─────────┴─────────┐            │
        │    │   CDN / Edge      │            │
        │    │   (Outer Rim)     │            │
        │    └───────────────────┘            │
        │                                     │
        │  ┌───────────────────────────────┐  │
        │  │   DEPLOY-04 Pipeline          │  │
        │  │   (Builds & deploys all)      │  │
        │  └───────────────────────────────┘  │
        └─────────────────────────────────────┘
```

---

## 6. Blueprint-to-Wheel Mapping Reference

| Blueprint ID | Wheel Position | Spoke Thickness | Access |
|---|---|---|---|
| CORE-01 through CORE-20 | **Core** (Nucleus) | N/A | System only |
| HUB-01 through HUB-31 | **Hub** (Inner Ring) | N/A | Internal services |
| BRIDGE-01 | **Inner Rim** (The Gate) | N/A | Traffic controller |
| ISPOKE-01 through ISPOKE-25 | **Inner Spokes** | **Thick** | Staff-only (authenticated + authorized) |
| ESPOKE-01 through ESPOKE-15 | **Outer Spokes** | **Thin** | Public (anonymous or authenticated) |
| DEPLOY-01 through DEPLOY-04 | **Frame** (The Deploy) | N/A | Infrastructure |

---

## 7. Key Architectural Principles

1. **Radial Dependency:** Dependencies flow inward only. A Spoke may depend on the Hub or Core. The Hub may depend on the Core. Nothing depends outward.

2. **Pulse Isolation:** A Pulse carries its own context (tenant ID, request ID, user ID, trace ID). This context is injected at the Rim and propagated through every layer. No layer re-derives context.

3. **Rim Enforcement:** The Inner Rim (BRIDGE-01) is the **only** path between Outer and Inner. No Outer Spoke may call an Inner Spoke directly. No Inner Spoke may be exposed to the public internet.

4. **Depth Proportionality:** The deeper a Pulse goes, the more expensive it is. Surface Pulses (CDN cache) are microseconds. Deep Pulses (DB + audit + notify) are milliseconds. Design for shallow Pulses.

5. **Spoke Independence:** Each Spoke is a separate composer package with its own `ServiceProvider`. It can be developed, tested, and deployed independently. The wheel keeps spinning even if one spoke is removed.

6. **Frame Immutability:** Deploy infrastructure is version-controlled Terraform. No manual changes. The frame is rebuilt, not patched.

---

*End of Structure 01: Application Structure*
