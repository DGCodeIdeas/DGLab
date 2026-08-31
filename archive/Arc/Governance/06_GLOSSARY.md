# 06 — Glossary & Reference Index

**Status:** Canonical
**Audience:** All contributors to the DGLab Sovereign Stack
**Last verified against `01_MASTER_INDEX.md`:** 2026-08-04

This document is the canonical terminology reference for the DGLab Sovereign Stack. If a term is not defined here, open a PR against this file before using it.

---

## §1. Project-Specific Terms (Architectural Concepts)

The 22 load-bearing terms of the architecture. Component-level terms (CORE-xx, HUB-xx) are catalogued in §1.1 and §1.2; technical primitives that appear as architectural patterns are catalogued in §5.

### A

**ADR (Architecture Decision Record)** — A short Markdown document in `docs/decisions/` recording a single architectural decision in Nygard format (Context, Decision, Status, Consequences). Mandated for any decision with a viable alternative (Master Index Rule 8). The initial set is ADR-001..010.

### B

**Bridge / Vanguard** — The single architectural enforcement layer (`BRIDGE-01`) that governs all traffic between Internal and External Spokes. Implements default-deny posture, DTO transformation, and audit mandate; the only permitted ingress path for public requests. The Bridge is a contract, not an application — External Spokes must be unreachable except through it (the Zero-Exposure Test).

### C

**Core Tier** — The 20 foundational infrastructure blueprints (CORE-01..20) on which every other tier depends. Includes the DI Container, Kernel, HTTP pipeline, DBAL, Cache, Filesystem, Encryption, SuperPHP, and the Developer CLI. Core components are pure — they have no knowledge of Hub, Spoke, or Bridge concerns.

### D

**Default-Deny Boundary** — The Bridge's security posture: every request, field, and header is rejected unless an explicit allow-rule permits it. Allow-rules are versioned in code and reviewed at PR time. Verified by the Zero-Exposure Test in DEPLOY-03.

**Dependency DAG** — The directed acyclic graph of cross-component dependencies defined in Master Index §5 and `hub-dependency-graph.md`. Cycles are a broken build (CI must fail). The only source for build ordering; ad-hoc ordering based on perceived urgency is forbidden.

**Deploy Tier** — The 5 deployment blueprints (DEPLOY-00..04): DEPLOY-00 docs site, DEPLOY-01 Core+Hub containers, DEPLOY-02 datastore provisioning, DEPLOY-03 Bridge + External Spokes with the Zero-Exposure Test, DEPLOY-04 dev→staging→prod promotion across 50+ repos.

**DTO Transformer** — A component at the Bridge boundary that converts internal data structures into public-safe Data Transfer Objects, preventing internal schema leakage. Any field not declared on the DTO is silently dropped before the response crosses the Bridge. Verified by a leakage suite asserting no internal field name appears in serialized output.

### E

**External Spoke** — A public-facing application (ESPOKE-01..15) accessible *only* through the Bridge. Examples include the Public CMS (ESPOKE-01) and the Public API (ESPOKE-02). Any External Spoke reachable by a non-Bridge path fails the Zero-Exposure Test and cannot ship.

### F

**Fidelity Bar** — The minimum content standard for an approved blueprint (Master Index §8). Requires PHP 8.3 interface contracts, a compilable class, SQL DDL where state is persisted, a Mermaid sequence diagram, dependency lists cross-referenced to §2, benchmark methodology, CI criteria, security invariants, migration notes, and SemVer impact. A blueprint failing this bar is rejected at PR review.

### H

**Hub Tier** — The 30 shared-service blueprints (HUB-01..30) consumed by all Spokes. Organised into 5 categories (Infrastructure, Integration, Data, Observability, Security) per `docs/hub-taxonomy/hub-categories.md`. Hub components depend only on Core; they never depend on Spoke or Bridge code.

### I

**Internal Spoke** — A staff-only application (ISPOKE-01..25) accessible via VPN or bastion, never through the public Bridge. Examples include the Admin Panel (ISPOKE-01) and the Audit Log Tracker (ISPOKE-02). ISPOKE-16..25 are placeholders (Finding 13) and must not be relied on for production.

### L

**Loom** — Codename for CORE-01 (Polyrepo Orchestrator), the only release and version-bump tool in the stack. Loom is *not* the Kernel; it is a CI-time tool that runs outside the application process. Reference impl in `orchestrator/`; the only fully-tested Core component alongside CORE-03.

### S

**Sovereign Forge** — Codename for CORE-20 (Developer CLI Toolchain). The only sanctioned way to scaffold a new Hub service: generates package skeleton, `composer.json`, CI workflow, and blueprint stub from a Fidelity-Bar-compliant template. HUB-30 extends the Forge with Hub admin commands.

**Sovereign Stack** — The overall name for the DGLab architecture as defined by Vision B (polyrepo). Comprises 5 Core/Hub/Spoke/Bridge/Deploy tiers totalling 96 blueprints (Master Index §4). Anything in `docs/architecture/origin/` is *not* the Sovereign Stack — it is Vision A.

**Spoke Tier** — The 40 application blueprints split into Internal (ISPOKE-01..25) and External (ESPOKE-01..15) spokes. The only tier permitted to contain business logic; Core and Hub are infrastructure-only.

**SuperPHP** — The custom template language implemented across CORE-07 (Lexer), CORE-11 (Parser), and CORE-12 (Compiler). The pipeline is Lexer → Parser → Compiler, with the Compiler emitting cached PHP executed by the Kernel. The only sanctioned template engine for Hub-rendered UI (HUB-26) and Internal Spoke views.

### T

**Tier-Crossing** — Any interaction that crosses the Bridge boundary, i.e. an External Spoke calling a Hub service or vice versa. Every tier-crossing is audited by HUB-06 and must traverse the DTO Transformer. Direct tier-crossing without the Bridge is a security violation.

**Tier-Enforcement DAG** — The compile-time graph validated by Loom's dependency analyser asserting no Spoke depends on another Spoke, no Hub on a Spoke, no Core on a Hub. Violations fail CI with `TierViolationError`. The static counterpart to the runtime Bridge contract.

### V

**Vision A** — The deprecated monolith architecture in `docs/architecture/origin/`. A single-repo, framework-style rebuild that contradicts Vision B on every tier boundary (Findings 1, 5, 6, 7). No new work is permitted; preserved for historical reference only.

**Vision B** — The canonical polyrepo architecture in `docs/blueprints/`. The active development target; all blueprints, ADRs, and code in `packages/` and `orchestrator/` belong to Vision B. If a Vision A document contradicts a Vision B document, Vision B wins.

### Z

**Zero-Exposure Test** — The CI/deploy gate enforced by DEPLOY-03: every External Spoke must be unreachable via direct internet access, responding only when traffic arrives through the Bridge (Vanguard). Run as a synthetic external probe attempting direct connections to each External Spoke's pod IP; any successful connection fails the deploy.

---

### §1.1 CORE Component Names (CORE-01..20)

The 20 foundational blueprints. Codenames appear in the third column where defined. Implementation status is per Master Index §2.

| ID | Component | Codename | Description |
|---|---|---|---|
| CORE-01 | Polyrepo Orchestrator | Loom | CI-time tool: dependency-aware build order, Conventional Commits parsing, SemVer bumps, cross-repo PRs. Only fully-tested Core component besides CORE-03. |
| CORE-02 | Dependency Injection Container | — | PSR-11 autowiring container at the root of the dependency graph. Currently a stub (`.gitkeep`) — blocking defect (Finding 8). |
| CORE-03 | PSR-14 Event Dispatcher | — | Synchronous in-process dispatcher; reference impl in `packages/core/event-dispatcher/`. Used by Kernel boot/shutdown and HUB-06. |
| CORE-04 | PSR-7 HTTP Message & Factory | — | Immutable request/response message types and PSR-17 factories. Base vocabulary for all HTTP work. |
| CORE-05 | PSR-15 Middleware & Request Handler | — | Middleware pipeline wrapping CORE-04 messages; Kernel dispatches through this chain. |
| CORE-06 | Attribute-Based Router | — | PHP 8.3 native-attribute router mapping method+path to controllers. Consumed by HUB-08. |
| CORE-07 | SuperPHP Lexer | — | Tokeniser for SuperPHP; hand-written, emits a token stream for CORE-11. |
| CORE-08 | Global Error & Exception Handler | — | Converts PHP errors to `ErrorException`, registers shutdown handlers, integrates with CORE-09. |
| CORE-09 | PSR-3 Logging Service | — | Structured JSON PSR-3 logger. Used by CORE-03 to log listener exceptions. |
| CORE-10 | Configuration & Environment Loader | — | Loads `.env`/JSON config, validates types, exposes a typed read API. The only sanctioned configuration source. |
| CORE-11 | SuperPHP Parser | — | Builds an AST from the CORE-07 token stream; consumed by CORE-12 only. |
| CORE-12 | SuperPHP Compiler | — | Compiles the CORE-11 AST into cached, opcache-friendly PHP source. |
| CORE-13 | CLI Engine (Console) | — | Symfony-Console-style CLI framework. Runtime for Loom, Forge, HUB-30. |
| CORE-14 | Filesystem Abstraction | — | Uniform interface over local, in-memory, S3-compatible backends. The only permitted file-access API. |
| CORE-15 | Cache Abstraction (PSR-6/16) | — | PSR-6/16 façade over APCu, Redis, in-memory drivers. Implements Probabilistic Early Expiration. |
| CORE-16 | Binary Encryption Envelope | — | AES-256-GCM with envelope-wrapped DEKs. The only sanctioned cryptographic primitive. |
| CORE-17 | Service Provider System | — | Boot-time registration of bindings, tags, boot callbacks. The only sanctioned extension point. |
| CORE-18 | Core Kernel & Lifecycle | — | Application entry point: boots container, dispatches boot events via CORE-03, routes through CORE-06, terminates. Only component allowed to call `exit()`. |
| CORE-19 | Database Abstraction Layer (DBAL) | — | PostgreSQL-primary (ADR-007) abstraction: prepared statements, transaction nesting, schema introspection. Not an ORM. |
| CORE-20 | Developer CLI Toolchain | Sovereign Forge | Scaffolds new Hub services, Spokes, and blueprints from Fidelity-Bar-compliant templates. The only sanctioned way to start a new repository. |

### §1.2 HUB Component Names (HUB-01..30)

The 30 shared-service blueprints. Category abbreviations: **Infra** = Infrastructure, **Integ** = Integration, **Data** = Data, **Obsv** = Observability, **Sec** = Security.

| ID | Component | Codename | Cat. | Description |
|---|---|---|---|---|
| HUB-01 | Hub Config & Flags | — | Infra | Multi-tenant configuration and feature-flag service extending CORE-10; per-tenant overrides + probabilistic rollout. |
| HUB-02 | Hub Cache | — | Integ | Shared cache coordination with Cache Tags and Atomic Locks, extending CORE-15; Redis-backed (ADR-006). |
| HUB-03 | Asset Engine | — | Infra | PHP-only asset pipeline: CSS/JS minification, fingerprinting, versioned manifests; eliminates Node.js. |
| HUB-04 | Sovereign Identity | — | Sec | User lifecycle, sessions, Argon2id hashing, OAuth2/OIDC foundation. The only JWT issuer. |
| HUB-05 | Sovereign Guardian | — | Sec | RBAC engine with Roles, Permissions, dynamic Policies; the single authorisation decision point. |
| HUB-06 | Sovereign Auditor | — | Obsv | Tamper-evident audit log with row-level hashing; the only append-only log in the stack. |
| HUB-07 | Sovereign Throttle | — | Integ | Rate-limiting engine: Token Bucket, Leaky Bucket, Fixed Window algorithms. |
| HUB-08 | Sovereign Gateway | — | Integ | Unified API entry point: internal service mesh + public gateway. Consumed by BRIDGE-01. |
| HUB-09 | Sovereign Pulse (Event Bus) | — | Integ | Distributed pub/sub extending CORE-03 to cross-process delivery via Redis Streams. |
| HUB-10 | Sovereign Queue | — | Integ | Async job system with Redis/SQS drivers, delayed jobs, retries, priority queuing. |
| HUB-11 | Sovereign Cloud Storage | — | Data | Cloud filesystem abstraction over S3/R2/GCS; multi-disk with local↔cloud switching. |
| HUB-12 | Sovereign Notify | — | Obsv | Multi-channel notifications (Email, In-app, Webhook, SMS) with CORE-12 template rendering. |
| HUB-13 | Sovereign Translator | — | Data | i18n/l10n service: translations, number/date formatting, pluralization. BCP 47 / CLDR. |
| HUB-14 | Sovereign Search | — | Data | Full-text search abstraction over Database, Meilisearch, Elasticsearch backends. |
| HUB-15 | Sovereign Pulse (Health) | — | Infra | Centralised health-monitoring and service-discovery registry for all Hub services. |
| HUB-16 | Sovereign Hub Weaver | Weaver | Infra | Orchestration hooks for CORE-01 (Loom); exposes `pre-release`, `post-release`, `pre-deploy`. |
| HUB-17 | Sovereign Webhook Nexus | — | Integ | Webhook ingestion with signature verification, idempotent processing, retry logic. |
| HUB-18 | Sovereign Media Forge | — | Data | Media processing: thumbnails, optimisation, transcoding, metadata extraction. |
| HUB-19 | Sovereign Guard (Validation) | — | Data | Validation and sanitisation engine with complex rule-sets and XSS prevention. |
| HUB-20 | Sovereign Vault | — | Sec | Secrets management: key rotation, encrypted field storage. Extends CORE-16. |
| HUB-21 | Sovereign Nexus (Tenancy) | — | Data | Multi-tenant coordination: tenant resolution, DB-connection switching, scope isolation. |
| HUB-22 | Sovereign Ledger (Billing) | — | Data | Provider-agnostic billing/subscription layer abstracting Stripe, Paddle, custom engines. |
| HUB-23 | Sovereign Reporter | — | Data | CSV/Excel/PDF export and reporting service with background processing. |
| HUB-24 | Sovereign GraphQL Registry | — | Integ | Pure PHP GraphQL schema registry unifying service-registered fragments into one API. |
| HUB-25 | Sovereign Chronos (Scheduler) | — | Infra | Scheduler replacing cron with a PHP fluent interface; uses HUB-02 Atomic Locks. |
| HUB-26 | Sovereign UI (Elements) | — | Sec | PHP-rendered UI component library for visual consistency across Internal Spokes; built with SuperPHP. |
| HUB-27 | Sovereign Sentinel (Headers) | — | Sec | CORS and HTTP security-header management against common web attacks. |
| HUB-28 | Sovereign Versioner | — | Infra | API versioning: URL-based, Header-based, Accept-header schemes. |
| HUB-29 | Sovereign Hub Spec (Testing) | — | Infra | Integration/E2E testing harness, mock drivers, shared fixtures for Hub services. |
| HUB-30 | Sovereign Hub-CLI | — | Infra | CLI for Hub admins: tenant mgmt, cache clearing, queue inspection, health. Extends Forge. |

---

## §2. PSR References

Every PHP-FIG standard referenced in the codebase.

| PSR | Name | Used by | Status |
|---|---|---|---|
| PSR-3 | Logger Interface | CORE-09, CORE-03 (listener failure logging), CORE-08 (error→log bridge) | Planned (contract in CORE-09 blueprint) |
| PSR-6 | Caching Interface | CORE-15, HUB-02 (Cache Tags wrap PSR-6 pool) | Planned |
| PSR-7 | HTTP Message Interface | CORE-04, CORE-05, HUB-08 | Planned |
| PSR-11 | Container Interface | CORE-02 (primary), every autowired service | Planned (stub in `packages/core/container/`) |
| PSR-14 | Event Dispatcher | CORE-03, CORE-18 (boot events), HUB-06, HUB-09 | ✅ Implemented (`packages/core/event-dispatcher/`) |
| PSR-15 | HTTP Server Request Handler | CORE-05, CORE-18 (Kernel pipeline) | Planned |
| PSR-16 | Simple Cache | CORE-15, HUB-02 (consumer API) | Planned |
| PSR-17 | HTTP Factory | CORE-04, CORE-05 | Planned |
| PSR-18 | HTTP Client | CORE-01 (CIMonitor), HUB-17, HUB-04 (OAuth2) | ✅ Implemented (Loom CIMonitor) |
| PSR-12 | Extended Coding Style | All repositories | Enforced via `php-cs-fixer` in CI |

---

## §3. External Standards & References

The standards and specifications external to PHP-FIG that the Sovereign Stack depends on. These are normative references: a deviation is a defect.

| Standard | Version | Used for | Link |
|---|---|---|---|
| Semantic Versioning | 2.0.0 | All package versioning (CORE-01 SemVer Bump Engine) | https://semver.org |
| Conventional Commits | 1.0.0 | Commit message format (CORE-01 Loom parses `feat:`, `fix:`, `BREAKING CHANGE`) | https://conventionalcommits.org |
| ULID | spec | All entity IDs (26-char lexicographically sortable) | https://github.com/ulid/spec |
| JWT (RFC 7519) | | HUB-04 Identity tokens (access + refresh) | https://datatracker.ietf.org/doc/html/rfc7519 |
| JWS (RFC 7515) | | ES256 JWT signing in HUB-04 | https://datatracker.ietf.org/doc/html/rfc7515 |
| W3C Trace Context | | Distributed tracing (propagated through CORE-05 middleware) | https://www.w3.org/TR/trace-context/ |
| OpenTelemetry | 1.x | Metrics, logs, traces (observability backbone) | https://opentelemetry.io/ |
| OWASP ASVS | 4.0 L2 | Security verification baseline for the Bridge and all External Spokes | https://owasp.org/www-project-application-security-verification-standard/ |
| STRIDE | | Threat-modeling framework (used in BRIDGE-01 and DEPLOY-03 design reviews) | https://learn.microsoft.com/en-us/azure/security/develop/threat-modeling-tool-threats |
| AES-256-GCM (NIST SP 800-38D) | | CORE-16 encryption (the only symmetric cipher permitted) | https://nvlpubs.nist.gov/nistpubs/Legacy/SP/nistspecialpublication800-38d.pdf |
| Argon2id (RFC 9106) | | HUB-04 password hashing (memory-hard, side-channel resistant) | https://datatracker.ietf.org/doc/html/rfc9106 |
| HKDF (RFC 5869) | | Key derivation within CORE-16/HUB-20 envelope encryption | https://datatracker.ietf.org/doc/html/rfc5869 |
| PostgreSQL | 16+ | Primary RDBMS per ADR-007 (CORE-19, HUB-06, HUB-19, HUB-21) | https://www.postgresql.org/docs/16/ |
| Redis | 7+ | Cache + queue + session store per ADR-006 (HUB-02, HUB-09, HUB-10) | https://redis.io/docs/ |
| OCI Image Spec | 1.1 | Container images produced by DEPLOY-01 | https://github.com/opencontainers/image-spec |
| Kubernetes | 1.29+ | Container orchestration (DEPLOY-01, DEPLOY-03) | https://kubernetes.io/ |
| BCP 47 | | Language tags for HUB-13 (Translator) | https://www.rfc-editor.org/info/bcp47 |
| CLDR | latest | Locale data backing HUB-13 number/date/plural formatting | https://cldr.unicode.org/ |

---

## §4. Acronyms

Alphabetical. Definition is the in-stack meaning.

| Acronym | Expansion | Definition |
|---|---|---|
| ADR | Architecture Decision Record | Documented decision with Context, Decision, Status, Consequences. Lives in `docs/decisions/`. |
| AEAD | Authenticated Encryption with Associated Data | Encryption mode used by CORE-16 (AES-256-GCM provides AEAD). |
| APCu | Alternative PHP Cache (user cache) | In-memory key-value cache for single-node PHP. One of the CORE-15 drivers. |
| ASVS | Application Security Verification Standard | OWASP security verification framework; L2 is the Sovereign Stack baseline. |
| CDN | Content Delivery Network | Edge caching layer fronting the Bridge in DEPLOY-03. |
| CI/CD | Continuous Integration / Continuous Deployment | Automated build, test, deploy pipeline. Loom orchestrates the CD half. |
| CLDR | Common Locale Data Repository | Unicode locale database backing HUB-13. |
| DAG | Directed Acyclic Graph | Used by CORE-01 for tier ordering and by the Tier-Enforcement DAG. |
| DBAL | Database Abstraction Layer | CORE-19. The uniform interface over PostgreSQL. |
| DEK | Data Encryption Key | Per-payload symmetric key wrapped by the KEK in envelope encryption. |
| DI | Dependency Injection | CORE-02. The autowiring container pattern. |
| DTO | Data Transfer Object | Public-safe object produced by the Bridge transformer; never contains internal schema. |
| ECDSA | Elliptic Curve Digital Signature Algorithm | Used by ES256 JWT signing in HUB-04. |
| FQDN | Fully Qualified Domain Name | Used in DEPLOY-03 ingress rules and HUB-08 routing. |
| HKDF | HMAC-based Key Derivation Function | Used by CORE-16/HUB-20 to derive subkeys from the master KEK. |
| IaC | Infrastructure as Code | Terraform/Pulumi manifests in DEPLOY-02 and DEPLOY-03. |
| IDP | Identity Provider | HUB-04 (Sovereign Identity). The only IDP in the stack. |
| JWT | JSON Web Token | HUB-04 authentication token (RFC 7519). |
| KEK | Key Encryption Key | Master key in HUB-20 (Vault) wrapping per-payload DEKs. |
| KDF | Key Derivation Function | Any function deriving keys from secrets; HKDF is the sanctioned KDF. |
| mTLS | mutual TLS | Used between Hub services in Phase 2 (DEPLOY-01). |
| OCI | Open Container Initiative | Container image standard (spec v1.1) produced by DEPLOY-01. |
| OIDC | OpenID Connect | OAuth2-based identity layer supported by HUB-04. |
| OPcache | Opcache (PHP opcode cache) | PHP's built-in bytecode cache. Required for production (DEPLOY-01). |
| OTel | OpenTelemetry | Observability framework; metrics/traces/logs backend for HUB-06 and CORE-09. |
| PSR | PHP Standards Recommendation | PHP-FIG standards; see §2 for the full list. |
| RBAC | Role-Based Access Control | HUB-05 (Sovereign Guardian). |
| RED | Rate, Errors, Duration | Metrics framework used by HUB-15 health checks. |
| SLO | Service Level Objective | Per-service reliability target documented in DEPLOY-01. |
| SOC2 | Service Organization Control 2 | Compliance framework targeted by the HUB-06 audit trail. |
| SSE | Server-Sent Events | Used by ISPOKE-01 (Admin Panel) audit log viewer for live tail. |
| SSRF | Server-Side Request Forgery | Threat mitigated by HUB-08 egress filtering and HUB-19 URL validation. |
| STRIDE | Spoofing, Tampering, Repudiation, Info disclosure, DoS, Elevation of privilege | Threat-modeling framework applied to BRIDGE-01. |
| ULID | Universally Unique Lexicographically Sortable Identifier | 26-char sortable ID; the only sanctioned primary-key format. |
| USE | Utilization, Saturation, Errors | Metrics framework used alongside RED for resource monitoring. |
| VPC | Virtual Private Cloud | Network isolation boundary for Internal Spokes (DEPLOY-01). |
| WAF | Web Application Firewall | Edge security layer fronting the Bridge in DEPLOY-03. |
| WORM | Write Once, Read Many | Audit log storage mode enforced by HUB-06 (append-only, hash-chained). |

---

## §5. Architectural Pattern Glossary

Patterns in active use across the codebase. Each entry names the pattern, where applied, and the invariant it preserves.

**Hub-and-Spoke** — Top-level topology: a Hub tier of shared services serves a Spoke tier of applications, mediated by the Bridge for public traffic. The invariant is that no Spoke calls another Spoke directly; all cross-Spoke communication transits the Hub.

**Default-Deny Boundary** — Applied at BRIDGE-01: every request, header, and field is rejected unless an explicit allow-rule permits it. No undocumented field can cross the Bridge, even by accident.

**DTO Transformation** — Applied at the Bridge boundary and at every Hub→Spoke response. The invariant is that internal schema names never appear in external responses; the transformer is the only path by which data leaves the system.

**Tier-Enforcement DAG** — Applied at compile time by Loom's dependency analyser. Dependency direction always flows Core → Hub → Spoke; any reverse or lateral dependency fails CI.

**Compiler Pass** — Applied within CORE-02 (DI Container) and CORE-12 (SuperPHP Compiler): the build is split into discrete passes that each transform one representation into the next. Each pass is pure and idempotent, enabling caching and parallelism.

**Service Provider** — Applied within CORE-17: each subsystem registers its bindings, tags, and boot callbacks via a provider class rather than a central registration file. Adding a subsystem never requires editing a global file.

**Probabilistic Early Expiration** — Applied within CORE-15 (Cache): a randomised subset of readers recomputes near-expiry entries, while others receive stale values. The invariant is that cache regeneration load is spread across time, eliminating thundering-herd spikes.

**Envelope Encryption** — Applied within CORE-16 and HUB-20: a per-payload DEK is wrapped by a master KEK that never leaves the Vault. The invariant is that compromise of one payload never compromises others, and KEK rotation does not require re-encrypting payloads.

**Append-Only Audit Log** — Applied within HUB-06: rows are hash-chained and immutable after insert. The audit trail cannot be silently rewritten; tampering is detectable by recomputing the chain.

**Reactive-First Server Rendering** — Applied within SuperPHP (CORE-07/11/12) and HUB-26: the server renders complete HTML, then hydrates only interactive islands. The invariant is that non-interactive content ships with zero JavaScript, keeping the initial payload minimal.

**Probabilistic Rollout** — Applied within HUB-01 feature flags: a percentage-based flag releases a feature to a random cohort of users, identified by hashing user ID against the flag key. The cohort is stable across requests for the same user.

---

## §6. Repository Path Glossary

Quick reference for where things live in the canonical Vision B tree.

| Path | Contents |
|---|---|
| `docs/blueprints/Core/` | 20 CORE blueprint files (CORE-01..20), one `.md` per component |
| `docs/blueprints/Hub/` | 30 HUB blueprint files (HUB-01..30), one `.md` per service |
| `docs/blueprints/Spoke/Internal/` | 25 ISPOKE files (ISPOKE-16..25 are placeholders per Finding 13) |
| `docs/blueprints/Spoke/External/` | 15 ESPOKE files (ESPOKE-01..15) |
| `docs/blueprints/Spoke/Bridge/` | BRIDGE-01 (Vanguard) — the only Bridge blueprint |
| `docs/blueprints/Deploy/` | DEPLOY-00..04 (00, 02, 03, 04 are new per Master Index §6) |
| `docs/decisions/` | ADRs (ADR-001..010 initially; new — empty in source repo) |
| `docs/evaluation/` | Stale evaluation docs (archived per Rule 3; do not use for planning) |
| `docs/hub-taxonomy/` | Hub classification docs: categories, navigation, taxonomy, dependency graph |
| `docs/internal-spokes/` | ISPOKE-16..25 placeholder stubs (will migrate to `docs/blueprints/Spoke/Internal/`) |
| `docs/architecture/origin/` | Vision A monolith docs (archived; no new work permitted) |
| `orchestrator/` | CORE-01 (Loom) reference implementation — tested |
| `packages/core/container/` | CORE-02 (DI Container) — stub only (`.gitkeep`); blocking defect |
| `packages/core/event-dispatcher/` | CORE-03 reference implementation — tested |
| `packages/core/` | Parent of all Core-tier package impls; only 02 and 03 exist today |
| `02_ADR/` | Initial 10 ADRs shipped in this Blueprint v2 bundle |
| `blueprints/Deploy/DEPLOY-01-core-hub.md` | Full DEPLOY-01 spec (replaces docs-only original) |

---

## §7. Change Log

| Date | Change | Author |
|---|---|---|
| 2026-08-04 | Initial glossary: 22 architectural terms, 20 CORE components, 30 HUB components, 10 PSRs, 18 external standards, 38 acronyms, 11 patterns, 17 repository paths | Task 1-e |
