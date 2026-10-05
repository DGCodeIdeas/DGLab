# DGLab — Sovereign Stack

> **This project is developed by both humans and AI systems. Both are capable of producing confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning.**

> **Blind-Spot Awareness (per [`BLIND-SPOT-DOCTRINE.md`](Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md)):** This repository's architecture has undergone an integrity audit (92 known findings — see [`SHORTCOMINGS-REGISTER.md`](Architecture/Verification/SHORTCOMINGS-REGISTER.md)). The audit is a **starting point, not a complete inventory**. The number of findings found is not the number of findings that exist. Before relying on any architectural claim, verify it against the actual implementation.

---

## Overview

DGLab ("Sovereign Stack") is a from-scratch PHP 8.4 application framework and monorepo, built for full control over every layer of the stack — from the DI container to the HTTP pipeline to the deployment infrastructure. It is **not a product seeking adoption**: it is a developer's toolkit, built by one developer, for that developer's use. Every architectural decision is recorded; every contract is governed; every assumption is open to challenge.

This README is the **orientation layer** — it summarizes what DGLab is, why it exists, and where to find the authoritative specifications. It does not duplicate the canonical documents. For any single subject, exactly one document is authoritative; this README points to it.

---

## Why DGLab Exists

Most PHP application stacks assemble third-party libraries around a thin framework core. DGLab takes the opposite position: the framework core, the deployment stack, and the application composition boundary are all **owned** — written, tested, and versioned in one repo — so that every layer's contract is explicit and every layer's behavior is auditable.

The cost is a larger surface area to maintain. The benefit is that nothing in the critical path is opaque. When a request fails, the failure is traceable from the edge server (Caddy) through the load balancer (Tengine) through the application server (FrankenPHP) through the kernel through the router through the controller — every hop is in this repo, every contract is documented, every test is visible.

DGLab is governed by a solo-tech-lead methodology ([`SDLC-AGRD`](Architecture/CrossCutting/SDLC-AGRD.md)) calibrated for sustained development without burnout, and by a **two-DAG governance model** ([ADR-021](Architecture/ADRs/ADR-021-tier-stratified-build-order.md)) that distinguishes architectural *intent* from implementation *reality* and never silently merges the two.

---

## Architecture

DGLab is organized into five tiers. The tier boundary is enforced by lint, by autoloader, and by contract: nothing in an outer tier is imported by an inner tier.

```
┌──────────────────────────────────────────────────────────────────┐
│  Runtime Substrate (Anvil v3: Caddy + Tengine + FrankenPHP)      │
│  The deployment surface. Request enters here.                    │
├──────────────────────────────────────────────────────────────────┤
│  Core (CORE-01..20)                                              │
│  Pure libraries. PSR-7/11/14/15/3/6/16 + Kernel + Router +      │
│  Container + Crypto + Filesystem + DBAL + Cache + SuperPHP.     │
├──────────────────────────────────────────────────────────────────┤
│  Hub (HUB-01..32)                                                │
│  Aggregated services. Identity, Auditor, Gateway, Vault,       │
│  Cache/State, Pulse, Guard, plus 24 capability Hubs.            │
├──────────────────────────────────────────────────────────────────┤
│  Applications (ESPOKE + ISPOKE + Bridge)                         │
│  ESPOKE = public-facing app (1 per app, singular identity).     │
│  ISPOKE = staff-only worker (many per app, composable).         │
│  Bridge = Vanguard boundary enforcement (BRIDGE-01).             │
├──────────────────────────────────────────────────────────────────┤
│  Deploy + Tooling (DEPLOY-00..04 + CORE-13 CLI + CORE-20 Forge)  │
│  Provisioning, multi-environment promotion, developer CLI.      │
└──────────────────────────────────────────────────────────────────┘
```

### Architectural Layers

The full tier model, edge taxonomy, and multigraph semantics are specified in [ADR-021](Architecture/ADRs/ADR-021-tier-stratified-build-order.md) (Amendment 2). The five tiers and their membership are normative there; this README is descriptive only.

The structural model is also specified in nine [`STRUCTURE-NN`](Architecture/CrossCutting/) blueprints (Wheel, Pulse, Security, Events, Persistence, Boot, Testing, Deployment, Performance). The Pulse ([`PULSE-MODEL.md`](Architecture/CrossCutting/PULSE-MODEL.md)) is the canonical runtime unit of work — a request enters at the Runtime Rim, traverses inward through Hub and Core, and returns outward.

### Composition Principle

Per the ESPOKE/ISPOKE composition model (ratified in ADR-021 §1 Tier 4):

- **One ESPOKE per application** — singular identity, public-facing. The ESPOKE owns its Application Manifest but does not own its ISPOKEs.
- **Many ISPOKEs per application** — staff-only workers, composable across ESPOKEs. ISPOKEs are **consumed** by ESPOKEs, not owned.
- **No CONSENT edges** — consumer-side composition only. An ESPOKE that needs an ISPOKE declares the dependency; the ISPOKE does not grant consent.
- **`reusable: false` is lint-enforced** — ISPOKEs cannot be marked reusable; reuse happens via composition, not declaration.
- **The Application Manifest is a first-class artifact** — every ESPOKE ships a manifest declaring its composed ISPOKEs and required Hub services.

### Request Flow

The composition root is [`public/index.php`](public/index.php) (per [SPEC-001 §44 Phase 3](download/SPEC-001-DGLab-Sovereign-Stack-Architecture.md)):

1. FrankenPHP worker mode loads `public/index.php` once as the worker bootstrap.
2. `public/index.php` delegates to `App\ApplicationFactory::create()` — this is the composition boundary.
3. `ApplicationFactory` constructs the container, registers service providers, wires the middleware pipeline, registers routes, boots the Kernel.
4. `ApplicationFactory::run()` enters the `frankenphp_handle_request()` loop (or falls back to single-request PHP-FPM handling).
5. Per request: Kernel receives `ServerRequestInterface`, dispatches through the middleware pipeline (Vanguard → application middleware → router → controller), the controller returns a `ResponseInterface`, the Kernel emits it.
6. The Vanguard (`BRIDGE-01`) is the outermost middleware — default-deny contract routing, WAF inspection, DTO transformation. JWT verification, rate limiting, and audit logging are pass-through stubs at depth 2 and are replaced by HUB-04/HUB-02/HUB-06 when those land.

The full Pulse lifecycle is specified in [`PULSE-MODEL.md`](Architecture/CrossCutting/PULSE-MODEL.md) and [ADR-017](Architecture/ADRs/ADR-017-fiber-based-cooperative-runtime.md) (Fiber-based cooperative runtime).

---

## Repository Structure

```
DGLab/
├── Architecture/          # All architectural documentation (canonical)
│   ├── ADRs/              # 21 Architecture Decision Records
│   ├── Core/              # 20 Core-tier blueprints + 4 DAG artifacts
│   ├── Hub/               # 32 Hub-tier blueprints + 3 DAG artifacts
│   ├── Spoke/             # ESPOKE (19) + ISPOKE (27) + Bridge (1) blueprints
│   ├── Deploy/            # 5 Deploy-tier blueprints
│   ├── CrossCutting/      # SDLC, doctrine, structure, memory, worklog
│   ├── Verification/      # Audit, register, integrity gate
│   ├── Migration/         # Historical migration plan (record-only)
│   ├── Critiques/         # Historical critique (record-only)
│   ├── Cooldown0/         # Cooldown artifacts (wireframes, taxonomy)
│   ├── INDEX.md           # Numbering & governance authority
│   ├── OPEN-DECISIONS.md  # Open decisions ledger
│   ├── FROZEN-CONTRACTS.md
│   └── AUTHORING_GUIDE.md
├── packages/              # Composer packages (the framework)
│   ├── core/              # 12 implemented + CORE-15 cache (on feature branch)
│   ├── hub/               # HUB-01 config, HUB-04 identity
│   ├── bridge/            # BRIDGE-01 Vanguard
│   └── spoke/             # ESPOKE-01 Canvas, ISPOKE-09 Codex, + 2 ISPOKE stubs
├── app/                   # Application tier (consumer of the framework)
│   └── ApplicationFactory.php  # The composition root
├── public/                # Public web entry point
│   └── index.php          # Thin executable; delegates to ApplicationFactory
├── orchestrator/          # CORE-01 Loom — SemVer automation tool
├── anvil/                 # Anvil v3 deployment stack (Caddy + Tengine + FrankenPHP)
├── .github/workflows/     # CI: packages-ci, architecture-lint, release, pr-title-lint
├── scripts/               # Architecture baseline generators, linters, fitness checks
└── tests/                 # Cross-package integration tests
```

---

## Core

The Core tier is the pure-library substrate: 20 blueprints (`CORE-01` through `CORE-20`), each a self-contained PSR-conformant or domain-specific package. Core packages are contract-coupled, not class-coupled — only the Kernel (`CORE-18`) imports sibling Core namespaces in `src/`, and that import is verified by the [`CORE-VERIFIED-DAG.md`](Architecture/Core/CORE-VERIFIED-DAG.md).

**Canonical documents:**
- [`Architecture/Core/`](Architecture/Core/) — 20 Core blueprints + 4 DAG artifacts.
- [`Architecture/ADRs/ADR-002-psr11-container-scope.md`](Architecture/ADRs/ADR-002-psr11-container-scope.md) — Container scope decision.
- [`Architecture/ADRs/ADR-017-fiber-based-cooperative-runtime.md`](Architecture/ADRs/ADR-017-fiber-based-cooperative-runtime.md) — Fiber-based cooperative runtime.
- [`Architecture/CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](Architecture/CrossCutting/NUCLEAR-GRADE-DOCTRINE.md) — Binding engineering doctrine for the Core tier (12 principles, 5-class error taxonomy, hard resource ceilings, circuit breakers, hash-chained audit, chaos tests, §9 merge gate).

**Shape C `pulse()` contract:** The container's `pulse($abstract, $value)` is a request-time, Fiber-local value binding. It accepts literal values (objects, strings, scalars, null, arrays, class-strings as literal strings); it rejects Closures. The full contract — 12 edge cases, the state-transition table, and the `make()` precedence rule — is specified in [`Architecture/Core/CORE-02.md`](Architecture/Core/CORE-02.md) and enforced by `tests/Unit/PulseShapeCTest.php` + `tests/Integration/WorkerContaminationTest.php` in the container package.

---

## Hub

The Hub tier is 32 aggregated services. Each Hub is a coherent capability boundary (Identity, Auditor, Gateway, Vault, Cache/State, Pulse, Guard, plus 25 capability Hubs). HUB-10 (Queue) and HUB-25 (Cron) relocated to the Runtime tier per [ADR-021 §1](Architecture/ADRs/ADR-021-tier-stratified-build-order.md); HUB-32 (AI Inference Hub) was ratified as canonical depth 1.

**Canonical documents:**
- [`Architecture/Hub/`](Architecture/Hub/) — 32 Hub blueprints + 3 DAG artifacts (DECLARED, VERIFIED, BUILD-ORDER).
- [`Architecture/Hub/HUB-BUILD-ORDER.md`](Architecture/Hub/HUB-BUILD-ORDER.md) — **generated** topological build waves. Status-driven; regenerated from the DAGs on each merge. Link here for "what's currently buildable", not to specific wave content (waves change).
- [`Architecture/Hub/HUB-32.md`](Architecture/Hub/HUB-32.md) — AI Inference Hub, canonical depth 1 (interface declared, implementation deferred).
- [`Architecture/ADRs/ADR-006-redis-over-memcached.md`](Architecture/ADRs/ADR-006-redis-over-memcached.md) — Redis as the primary cache backend (consumed by HUB-02, not by CORE-15).
- [`Architecture/ADRs/ADR-008-argon2id-password-hashing.md`](Architecture/ADRs/ADR-008-argon2id-password-hashing.md) — Argon2id for HUB-04 Identity.
- [`Architecture/ADRs/ADR-011-hub-31-real-time-analytics.md`](Architecture/ADRs/ADR-011-hub-31-real-time-analytics.md) — HUB-31 Real-Time Analytics & Metrics Ledger.

---

## Application

The Application tier is consumer-side composition. An Application is one ESPOKE + many ISPOKEs + a Bridge. The composition root is `App\ApplicationFactory` (in `app/`), which the public entry point delegates to.

**Canonical documents:**
- [`Architecture/Spoke/External/`](Architecture/Spoke/External/) — 19 ESPOKE blueprints. [`ESPOKE-19.md`](Architecture/Spoke/External/ESPOKE-19.md) (Eloq — Private AI Writing Assistant) is canonical depth 1.
- [`Architecture/Spoke/Internal/`](Architecture/Spoke/Internal/) — 27 ISPOKE blueprints.
- [`Architecture/Spoke/Bridge/BRIDGE-01.md`](Architecture/Spoke/Bridge/BRIDGE-01.md) — The Vanguard (architectural enforcement layer).
- [`app/ApplicationFactory.php`](app/ApplicationFactory.php) — The composition root (per SPEC-001 §44 Phase 3).
- [`Architecture/ADRs/ADR-016-library-app-boundary-split.md`](Architecture/ADRs/ADR-016-library-app-boundary-split.md) — Library/Application boundary (Proposed, not yet Accepted).

**APIfy:** Per [OD-12](Architecture/OPEN-DECISIONS.md), declarative API exposure for Hub capabilities is recorded as an open decision; implementation deferred. When shipped, an ESPOKE will be able to declare its public API surface in its Application Manifest, and the Bridge will enforce it.

---

## Runtime

The Runtime substrate is **Anvil v3** — a three-tier deployment stack: Caddy (edge, TLS/HTTP/3) + Tengine (internal LB, dynamic upstream, health checks) + FrankenPHP (app server, Fiber-based worker mode per [ADR-017](Architecture/ADRs/ADR-017-fiber-based-cooperative-runtime.md)). RUNTIME-03 (Queue Worker, relocated from HUB-10) and RUNTIME-04 (Chronos, relocated from HUB-25) live here.

**Canonical documents:**
- [`anvil/`](anvil/) — Anvil v3 deployment stack (provisioning scripts, Caddyfile templates, Tengine upstream configs, FrankenPHP worker mode).
- [`Architecture/CrossCutting/DGLAB-AS-OS.md`](Architecture/CrossCutting/DGLAB-AS-OS.md) — DGLab as Operating System (conceptual model).
- [`Architecture/CrossCutting/DGLAB-AS-OS-RUNTIME.md`](Architecture/CrossCutting/DGLAB-AS-OS-RUNTIME.md) — Runtime implementation roadmap (accepted, gated by ADR-017).
- [`Architecture/ADRs/ADR-010-opcache-preload-strategy.md`](Architecture/ADRs/ADR-010-opcache-preload-strategy.md) — OPcache preload strategy for Core-tier classes.

---

## Architecture Governance

DGLab's governance model is binding. Every architectural decision is recorded; every contract is governed; every assumption is open to challenge. The four governance pillars:

### Two-DAG Model

Per [ADR-021 Amendment 1](Architecture/ADRs/ADR-021-tier-stratified-build-order.md), each tier owns **two authoritative DAGs** with different scopes:

- **Declared Architecture DAG** — architectural intent, from blueprints and ADRs. Answers "what does the architecture intend to depend on?"
- **Verified Implementation DAG** — repository reality, from `composer.json` and source imports. Answers "what does the implementation actually depend on?"

Both are authoritative for different purposes. They are never silently merged. When they disagree, that disagreement is a finding, not a defect to fix.

Per ADR-021 Amendment 2, every edge has four orthogonal dimensions: `edge_type` (COMPILE / RUNTIME / INTEGRATION / CAPABILITY), `requiredness` (REQUIRED / OPTIONAL), `declared/verified` status, and `gates` (a list, not a single gate). Multigraph semantics: edge identity is `source + target + edge_type`.

The canonical DAGs live next to their tier blueprints:
- [`Architecture/Core/CORE-DECLARED-DAG.md`](Architecture/Core/CORE-DECLARED-DAG.md) (45 edges) + [`CORE-VERIFIED-DAG.md`](Architecture/Core/CORE-VERIFIED-DAG.md) (13 edges).
- [`Architecture/Hub/HUB-DECLARED-DAG.md`](Architecture/Hub/HUB-DECLARED-DAG.md) (76 edges) + [`HUB-VERIFIED-DAG.md`](Architecture/Hub/HUB-VERIFIED-DAG.md) (10 edges).

### Eligibility and Build Order

The SDLC admission rule uses an `Eligible(X)` formula: a capability X is build-ready when (1) all its REQUIRED dependencies are at depth ≥ 2, (2) its blueprint is ratified at depth 1, (3) the architecture gate is passed, (4) the SDLC admission criteria are met. Per [ADR-021 §11](Architecture/ADRs/ADR-021-tier-stratified-build-order.md).

Build orders are **generated artifacts**, not independently authored. They are derived from the two DAGs + SDLC admission state + governance resolutions. They are regenerated on each merge; do not link to specific wave content — link to the artifact.

- [`Architecture/Core/CORE-BUILD-ORDER.md`](Architecture/Core/CORE-BUILD-ORDER.md) — Core-tier topological waves.
- [`Architecture/Hub/HUB-BUILD-ORDER.md`](Architecture/Hub/HUB-BUILD-ORDER.md) — Hub-tier topological waves.

### Integrity Gate

Per [`Architecture/Verification/INTEGRITY-GATE.md`](Architecture/Verification/INTEGRITY-GATE.md), the integrity phase converged on **2026-10-05** with seven gate criteria all met:

1. Zero FATAL findings Open, all FATAL findings have verification evidence.
2. All HIGH findings dispositioned (Deferred / Accepted / Closed).
3. Governance consistency (two-DAG model ratified, edge dimensions applied).
4. Targeted verification conditions met (not "CI green" — specific verification conditions).
5. CI + lint pass.
6. Convergence: zero new FATAL/HIGH findings without disposition.
7. Traceability: every finding has a register entry with evidence pointer.

**Status: PASSED (2026-10-05).** This is a binding governance gate, not a status report. The roadmap may proceed past it; it does not get reopened without a tech-lead directive.

The 92 known findings live in [`Architecture/Verification/SHORTCOMINGS-REGISTER.md`](Architecture/Verification/SHORTCOMINGS-REGISTER.md) (V2 — reconciled). HIGH dispositions in [`HIGH-DISPOSITION-MATRIX.md`](Architecture/Verification/HIGH-DISPOSITION-MATRIX.md).

### Blind-Spot Doctrine

Per [`Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md`](Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md), six binding rules govern how findings are closed and how audits are interpreted:

1. An audit is a starting point, not a complete inventory. The number of findings found is not the number of findings that exist.
2. A finding is closed only when its verification condition passes — not when code changes, not when CI is green.
3. Every architectural document carries a blind-spot awareness note (this README included).
4. Both humans and AI systems produce confident, coherent, technically sophisticated work while still being unaware of important shortcomings in their own reasoning.
5. The Dunning-Kruger failure mode is about the blind spots — the shortcomings you DON'T know about, not just the ones you do.
6. CI green ≠ verification conditions met. (This pattern recurred three times during the integrity phase — S-054, S-033, S-077.)

The doctrine is **binding governance**: it constrains how findings are closed and how audits are interpreted. It is not advisory.

---

## Current State

**Version:** `v1.2.0.0+<sha>` — post-MUWV (stable), per [ADR-019](Architecture/ADRs/ADR-019-pre-muwv-version-scheme.md). MUWV flipped to `1` on 2026-09-18 after all 8 Milestone 0 blueprints shipped and the full Pulse trace was verified end-to-end.

### Implemented (depth ≥ 2)

**Core tier — 12 packages on `main`:**

| Package | Blueprint | Notes |
|---|---|---|
| `core/container` | [CORE-02](Architecture/Core/CORE-02.md) | PSR-11 + Shape C `pulse()` contract (A2 fix landed; S-048 remediation landed) |
| `core/event-dispatcher` | [CORE-03](Architecture/Core/CORE-03.md) | PSR-14 |
| `core/http-message` | [CORE-04](Architecture/Core/CORE-04.md) | PSR-7 + 6 PSR-17 factories |
| `core/middleware` | [CORE-05](Architecture/Core/CORE-05.md) | PSR-15 cursor-based pipeline |
| `core/router` | [CORE-06](Architecture/Core/CORE-06.md) | Attribute-based, PCRE-compiled |
| `core/config` | [CORE-10](Architecture/Core/CORE-10.md) | Env interpolation + dot-notation |
| `core/logger` | [CORE-09](Architecture/Core/CORE-09.md) | PSR-3 + multi-handler broadcast |
| `core/error-handler` | [CORE-08](Architecture/Core/CORE-08.md) | Error→Exception conversion + shutdown guard |
| `core/kernel` | [CORE-18](Architecture/Core/CORE-18.md) | Lifecycle + state machine + Nuclear-Grade §4.5 pilot (all 6 items implemented) |
| `core/dbal` | [CORE-19](Architecture/Core/CORE-19.md) | MySQL + SQLite drivers, tenant context |
| `core/crypto` | [CORE-16](Architecture/Core/CORE-16.md) | Argon2id + envelope encryption + key registry |
| `core/filesystem` | [CORE-14](Architecture/Core/CORE-14.md) | Path-traversal guard + atomic writer |

**Hub tier — 2 packages on `main`:**

| Package | Blueprint |
|---|---|
| `hub/config` | [HUB-01](Architecture/Hub/HUB-01.md) — Sovereign Hub Config & Flags |
| `hub/identity` | [HUB-04](Architecture/Hub/HUB-04.md) — Sovereign Identity & Authentication |

**Bridge tier — 1 package on `main`:**

| Package | Blueprint |
|---|---|
| `bridge/vanguard` | [BRIDGE-01](Architecture/Spoke/Bridge/BRIDGE-01.md) — default-deny contract routing + WAF inspection |

**Spoke tier — 4 packages on `main`:**

| Package | Blueprint |
|---|---|
| `spoke/external/canvas` | [ESPOKE-01](Architecture/Spoke/External/ESPOKE-01.md) — Sovereign Canvas (depth 2) |
| `spoke/internal/codex` | [ISPOKE-09](Architecture/Spoke/Internal/ISPOKE-09.md) — Sovereign Codex (depth 1-2) |
| `spoke/internal/showcase` | ISPOKE (depth 3) — DDD showcase (Entity, ValueObject, Repository, Application) |
| `spoke/internal/lms` | ISPOKE (depth 3) — DDD LMS (Course, Module, Enrollment, Progress) |

### In Progress

- **[CORE-15 Cache](Architecture/Core/CORE-15.md)** — PSR-6 + PSR-16 cache abstraction. Implementation landed on `feat/core-15-cache` branch (commit `0528e53`): 116 test methods across 4 test files, 8 src files. This is the master blocker — unblocks 75% of the Hub tier via the HUB-02 cascade. Not yet merged to `main`.
- **D-1 / S-093 follow-up** — The HUB-BUILD-ORDER.md Wave 0 fix (PR [#309](https://github.com/DGCodeIdeas/DGLab/pull/309)) removed HUB-30 from Wave 0, but propagation to the rest of the file's wave-iteration text is incomplete. Targeted correction pending.

### Planned

Per the generated Hub build order ([`HUB-BUILD-ORDER.md`](Architecture/Hub/HUB-BUILD-ORDER.md)):

- **Wave 1** (Hub deps satisfied, no Core blockers): HUB-06 (Auditor), HUB-11 (File Storage Abstraction), HUB-14 (Search), HUB-19 (Guard).
- **Wave 2**: HUB-03 (Asset Pipeline), HUB-20 (Vault).
- **Blocked — 21 Hubs**: HUB-02 (Cache & State) blocks 15 of 21 via the CORE-15 cascade; HUB-12 (Notification) blocked by CORE-12; HUB-26 (UI Library) blocked by CORE-11 + CORE-12; HUB-29 (Testing Harness) blocked by CORE-20.

For the live state, see the generated artifacts directly (they are status-driven):

- [Core build order](Architecture/Core/CORE-BUILD-ORDER.md)
- [Hub build order](Architecture/Hub/HUB-BUILD-ORDER.md)

---

## Documentation

### Architecture

| Document | Purpose |
|---|---|
| [`Architecture/INDEX.md`](Architecture/INDEX.md) | Numbering & governance authority |
| [`Architecture/README.md`](Architecture/README.md) | Architecture-tree orientation |
| [`Architecture/OPEN-DECISIONS.md`](Architecture/OPEN-DECISIONS.md) | Open decisions ledger |
| [`Architecture/FROZEN-CONTRACTS.md`](Architecture/FROZEN-CONTRACTS.md) | Frozen interface registry |
| [`Architecture/AUTHORING_GUIDE.md`](Architecture/AUTHORING_GUIDE.md) | Blueprint authoring rules |
| [`Architecture/DEPRECATED_TAGS.md`](Architecture/DEPRECATED_TAGS.md) | Historical tag register (tags deleted from git) |

### Core, Hub, Spoke, Deploy

Each tier has its own directory under `Architecture/` with blueprints + DAG artifacts. See [Repository Structure](#repository-structure) above.

### Governance (CrossCutting)

| Document | Purpose |
|---|---|
| [`SDLC-AGRD.md`](Architecture/CrossCutting/SDLC-AGRD.md) | The development methodology (Spiral Deepening) |
| [`NUCLEAR-GRADE-DOCTRINE.md`](Architecture/CrossCutting/NUCLEAR-GRADE-DOCTRINE.md) | Binding engineering doctrine for Core tier |
| [`BLIND-SPOT-DOCTRINE.md`](Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md) | Audit governance doctrine (6 rules) |
| [`THREAT_MODEL.md`](Architecture/CrossCutting/THREAT_MODEL.md) | STRIDE threat model |
| [`OBSERVABILITY.md`](Architecture/CrossCutting/OBSERVABILITY.md) | Four-pillar observability spec |
| [`GLOSSARY.md`](Architecture/CrossCutting/GLOSSARY.md) | Canonical terminology reference |
| [`PROMPTS.md`](Architecture/CrossCutting/PROMPTS.md) | AI agent operating instructions (SDLC companion) |
| [`MEMORY.md`](Architecture/CrossCutting/MEMORY.md) | Agent entry-point memory file |
| [`PULSE-MODEL.md`](Architecture/CrossCutting/PULSE-MODEL.md) | Runtime unit of work |
| [`STRUCTURE-01-Wheel.md`](Architecture/CrossCutting/STRUCTURE-01-Wheel.md) through `STRUCTURE-09-Performance.md` | 9 structural blueprints |
| [`DGLAB-AS-OS.md`](Architecture/CrossCutting/DGLAB-AS-OS.md) | DGLab as Operating System (conceptual) |
| [`DGLAB-AS-OS-RUNTIME.md`](Architecture/CrossCutting/DGLAB-AS-OS-RUNTIME.md) | Runtime implementation roadmap |
| [`VISUAL-DESIGN-SYSTEM.md`](Architecture/CrossCutting/VISUAL-DESIGN-SYSTEM.md) | Visual language for diagrams/badges |
| [`WORKLOG.md`](Architecture/CrossCutting/WORKLOG.md) | CrossCutting-authoring append-only log (separate from root `worklog.md`) |

### SDLC

The SDLC is [`SDLC-AGRD`](Architecture/CrossCutting/SDLC-AGRD.md) (Spiral Deepening for a Solo Tech Lead), ratified as [ADR-014](Architecture/ADRs/ADR-014-ratify-agrd-canonical-sdlc.md). Key concepts: Spiral Deepening (depth 1 → 6 across laps), Interface Freeze (a blueprint's contract freezes the first time it's implemented), Laps (one pass through the build order), Cooldowns (2-week between-lap reconciliation).

### Runtime

Runtime documentation lives in [`anvil/`](anvil/) (operator-facing) and [`Architecture/CrossCutting/DGLAB-AS-OS-RUNTIME.md`](Architecture/CrossCutting/DGLAB-AS-OS-RUNTIME.md) (architectural roadmap). Runbooks: [`RUNBOOK-ANVIL-DNS.md`](Architecture/CrossCutting/RUNBOOK-ANVIL-DNS.md), [`RUNBOOK-BLUETOOTH.md`](Architecture/CrossCutting/RUNBOOK-BLUETOOTH.md).

### Verification

| Document | Purpose |
|---|---|
| [`INTEGRITY-GATE.md`](Architecture/Verification/INTEGRITY-GATE.md) | The integrity gate (PASSED 2026-10-05) |
| [`SHORTCOMINGS-REGISTER.md`](Architecture/Verification/SHORTCOMINGS-REGISTER.md) | The 92-finding living register |
| [`SHORTCOMINGS-AUDIT.md`](Architecture/Verification/SHORTCOMINGS-AUDIT.md) | The original 47-finding audit |
| [`HIGH-DISPOSITION-MATRIX.md`](Architecture/Verification/HIGH-DISPOSITION-MATRIX.md) | HIGH finding dispositions |
| [`HUB-EDGE-INVENTORY.md`](Architecture/Verification/HUB-EDGE-INVENTORY.md) | Per-edge evidence per ADR-021 Amendment 2 |

---

## Development

### Requirements

- PHP 8.4+
- Composer 2.x
- Docker Engine + Compose plugin (for dev stack)
- `ext-mbstring`, `ext-pcre`, `ext-fileinfo`
- Optional: `ext-redis` (for `packages/core/cache` RedisAdapter)

### Installation

```bash
git clone https://github.com/DGCodeIdeas/DGLab.git
cd DGLab
composer install
```

For the deployment stack (Anvil v3):

```bash
cd anvil
sudo ./install.sh --bootstrap  # dev stack only (Docker, dnsmasq, mkcert, sass)
# or
sudo ./install.sh --trio       # production trio (Caddy + Tengine + FrankenPHP)
```

### Running

The application is served by FrankenPHP (worker mode) behind Caddy + Tengine. For local development:

```bash
# Boot the dev stack
cd anvil && sudo ./install.sh --bootstrap

# Run the application directly (PHP-FPM fallback)
php -S 0.0.0.0:8000 -t public/
```

The public entry point is [`public/index.php`](public/index.php), which delegates to `App\ApplicationFactory::create()->run()`.

### Testing

Each package has its own PHPUnit 11 test suite. Run a single package:

```bash
cd packages/core/container
composer install
vendor/bin/phpunit --testdox
```

Run all packages (monorepo CI matrix):

```bash
# See .github/workflows/packages-ci.yml for the full matrix
```

Cross-package integration tests live in [`tests/`](tests/) and in each package's `tests/Integration/` directory.

### Static Analysis

PHPStan 2.x at level max is configured per-package (`phpstan.neon` in each package root).

```bash
cd packages/core/container
vendor/bin/phpstan analyse
```

### Architecture Verification

The architecture is verified by three CI workflows:

- [`architecture-lint.yml`](.github/workflows/architecture-lint.yml) — namespace root lint, prefix-numbering lint, blind-spot-awareness lint.
- [`architecture-boundary-lint.yml`](.github/workflows/architecture-boundary-lint.yml) — tier-boundary enforcement (no outer-tier imports inner-tier).
- [`architecture-fitness.yml`](.github/workflows/architecture-fitness.yml) — blueprint fidelity, naming drift, pulse consistency.

The fitness scripts live in [`scripts/fitness/`](scripts/fitness/) and the lint scripts in [`scripts/`](scripts/). Generated DAG artifacts (CORE-VERIFIED-DAG, HUB-VERIFIED-DAG, etc.) are reproducible from these scripts.

---

## Contributing

DGLab is a personal scaffold built by a solo tech lead. Contributions are welcome but should follow the established methodology and architecture. See [`CONTRIBUTING.md`](CONTRIBUTING.md).

Key constraints every contributor must respect:

1. **Two-DAG governance** — never silently merge declared intent with verified reality. If they disagree, file a finding in [`SHORTCOMINGS-REGISTER.md`](Architecture/Verification/SHORTCOMINGS-REGISTER.md).
2. **Blind-Spot Doctrine** — every architectural document carries a blind-spot awareness note. New documents must include one.
3. **Interface Freeze** — a blueprint's public contract freezes the first time it's implemented. Changing a frozen interface is an ADR-gated event.
4. **Tier boundaries** — nothing in an outer tier is imported by an inner tier. Enforced by lint.
5. **Nuclear-Grade Doctrine** — Core-tier packages are bound by [`NUCLEAR-GRADE-DOCTRINE.md`](Architecture/CrossCutting/NUCLEAR-GRADE-DOCTRINE.md). Where the doctrine and a per-package blueprint disagree, the doctrine wins.
6. **Worklog protocol** — every agent (human or AI) appends to [`worklog.md`](worklog.md). Never overwrite prior entries.

---

## Roadmap

The roadmap is **derived** from the build orders, not authored independently. See:

- [Core build order](Architecture/Core/CORE-BUILD-ORDER.md) — generated waves for the Core tier.
- [Hub build order](Architecture/Hub/HUB-BUILD-ORDER.md) — generated waves for the Hub tier.

**Current focus:** CORE-15 Cache (the master blocker) → merge to `main` → regenerate Hub eligibility → Wave 1 Hub packages (HUB-06, HUB-11, HUB-14, HUB-19).

**Open decisions:** See [`OPEN-DECISIONS.md`](Architecture/OPEN-DECISIONS.md) for the live ledger. Notable open items: OD-12 (APIfy — declarative API exposure), ADR-015 (Hospitality Vertical promotion — Proposed), ADR-016 (Library/Application boundary split — Proposed).

---

## License

MIT — see [LICENSE](LICENSE).

---

## Project Status

**Active development. Post-MUWV (stable).** All 8 Milestone 0 blueprints shipped; full Pulse trace verified end-to-end. Integrity Gate PASSED (2026-10-05). Current focus: CORE-15 Cache merge → Hub tier Wave 1.

The release workflow is fully functional end-to-end — first real `core-v*` tag (`core-v0.2.0.0+684eaed`) created 2026-09-23 after a 4-PR silent no-op fix series. Per-tier releases follow [ADR-018](Architecture/ADRs/ADR-018-centralized-per-tier-releases.md); version scheme per [ADR-019](Architecture/ADRs/ADR-019-pre-muwv-version-scheme.md).
