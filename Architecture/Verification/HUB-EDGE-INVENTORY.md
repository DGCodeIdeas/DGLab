# Hub Edge Inventory — HUB-DAG-EVIDENCE-75 (Phase 1)

**Task ID:** HUB-DAG-EVIDENCE-75
**Agent:** General-purpose (Hub DAG evidence reconciliation)
**Date:** 2026-10-01
**Authority:** ADR-021 §11 (two-DAG governance model) — DECLARED DAG (architectural intent) vs VERIFIED DAG (repository reality)
**Scope:** Hub-tier DAG evidence reconciliation ONLY. This is an **analysis document** (gitignored, in `download/`). DAG files (`Architecture/Hub/HUB-VERIFIED-DAG.md`, `HUB-DECLARED-DAG.md`) are **Phase 2** deliverables, not produced here.

---

## §0. Methodology (inherited from CORE-DAG-RECONCILIATION-8 + ADR-021 Amendment 2)

Per ADR-021 Amendment 2, every edge has 4 dimensions:
- **edge_type**: COMPILE | RUNTIME | INTEGRATION | CAPABILITY (exactly one)
- **requiredness**: REQUIRED | OPTIONAL (independent of edge_type)
- **declared/verified → status**: VERIFIED | DECLARED_ONLY | UNDECLARED_VERIFIED | INVALID (derived)
- **gates**: list of BUILD | RUNTIME | INTEGRATION | PRODUCTION

Per ADR-021 §11, the Hub tier has TWO authoritative DAGs:
- **DECLARED DAG** — from blueprint Upward/Downward/Transitive Core sections (architectural intent)
- **VERIFIED DAG** — from composer.json `require` + `use SovereignStack\*` source imports (repository reality)

This inventory is the **evidence base** for both DAGs. It does NOT decide direction corrections or build waves — those are Phase 2 deliverables after tech-lead review.

**Status definitions (per ADR-021):**
- `VERIFIED` — declared in blueprint AND verified in composer/source
- `DECLARED_ONLY` — declared in blueprint, not verified in code (target may not yet be implemented)
- `UNDECLARED_VERIFIED` — verified in composer/source, but not formally declared in blueprint's Upward list
- `INVALID` — declared AND verified AND target does not exist in active inventory (none expected for Hub tier)

---

## §1. Step 1 — Active Hub blueprints

Per `/home/z/my-project/Architecture/INDEX.md` §2.2 (canonical Hub map) and ADR-021 §12:

**Filesystem reality:** 31 Hub blueprint files exist at `/home/z/my-project/Architecture/Hub/HUB-01.md` through `HUB-31.md`.

**Tier-state reality (per ADR-021 + Task 74):**
- HUB-10 (Sovereign Queue) — **SUPERSEDED**, relocated to Runtime tier as RUNTIME-03
- HUB-25 (Sovereign Chronos / Scheduler) — **SUPERSEDED**, relocated to Runtime tier as RUNTIME-04
- HUB-32 (AI Inference Hub) — **ratified pending canonical publication** (no blueprint file at `Architecture/Hub/HUB-32.md`); worklog Task 72-73 (ELQ-DECISIONS-RATIFY-6.5) ratifies but defers file creation

**Active Hub blueprints for DAG purposes = 31 − 2 superseded = 29.**

The 29 active Hub blueprints (with their §2.2 names):

| ID | Name | Criticality |
|---|---|---|
| HUB-01 | Sovereign Hub Config & Flags | Critical |
| HUB-02 | Sovereign Cache & State | Critical |
| HUB-03 | Sovereign Asset Engine | High |
| HUB-04 | Sovereign Identity & Authentication | Critical |
| HUB-05 | Sovereign Guardian (RBAC) | Critical |
| HUB-06 | Sovereign Auditor | High |
| HUB-07 | Sovereign Throttle | High |
| HUB-08 | Sovereign Gateway | Critical |
| HUB-09 | Sovereign Signal (Event Bus) — *renamed from "Pulse"* | Critical |
| HUB-11 | Sovereign Cloud Storage | High |
| HUB-12 | Sovereign Notify | High |
| HUB-13 | Sovereign Translator | Medium |
| HUB-14 | Sovereign Search | High |
| HUB-15 | Sovereign Pulse (Health Check & Service Discovery) | High |
| HUB-16 | Sovereign Hub Weaver | Medium |
| HUB-17 | Sovereign Webhook Nexus | High |
| HUB-18 | Sovereign Media Forge | Medium |
| HUB-19 | Sovereign Guard (Validation) | Critical |
| HUB-20 | Sovereign Vault | Critical |
| HUB-21 | Sovereign Nexus (Tenancy) | Critical |
| HUB-22 | Sovereign Ledger (Billing) | High |
| HUB-23 | Sovereign Reporter | Medium |
| HUB-24 | Sovereign GraphQL Registry | High |
| HUB-26 | Sovereign UI (Elements) | High |
| HUB-27 | Sovereign Sentinel (Headers) | High |
| HUB-28 | Sovereign Versioner — *API versioning, not analytics* | Medium |
| HUB-29 | Sovereign Hub Spec (Testing) | High |
| HUB-30 | Sovereign Hub-CLI | High |
| HUB-31 | Sovereign Real-time Analytics | Medium |

**Total active Hub blueprints: 29** (INDEX.md §4 criticality counts: 9 Critical [10 − HUB-10] + 14 High [15 − HUB-25] + 6 Medium = 29 — confirmed).

---

## §2. Step 2 — Declared edges extracted from each Hub blueprint

For each active Hub blueprint, the `## Dependency Status` section was extracted via `awk '/^## Dependency Status/{flag=1;next} /^## /{flag=0} flag'`. Three blueprint formats were observed:

- **Format A** (HUB-01, 02, 04, 05, 07, 08, 09, 11, 12, 13, 14, 15, 16, 18, 19, 20): has `Upward` + `Downward` + (sometimes) `Runtime` subsections
- **Format B** (HUB-03, 06): has `Upward` + `Downward` + sometimes a correction note
- **Format C** (HUB-17, 18, 21, 22, 23, 24, 26, 27, 28, 29, 30, 31): has `Direct Hub` + `Transitive Core` + (sometimes) `Downward` subsections. `Direct Hub` = Hub-internal Upward; `Transitive Core` = Core-tier Upward.

**Edges are extracted as `(source = this Hub) → (target = each listed ID)` from Upward/Direct Hub/Transitive Core.** Downward entries are recorded as the *reverse* direction: `(source = listed consumer) → (target = this Hub)`. Edges that appear in BOTH the consumer's Upward AND the producer's Downward are recorded once with both declaring sources in the Evidence column.

### §2.1 Declared Upward edges per blueprint (one row per declared source→target)

#### HUB-01 (Config & Flags) — `Architecture/Hub/HUB-01.md` line 2
- **Upward:** CORE-02, CORE-10, CORE-19, HUB-02, CORE-09 *(soft — diagnostic emission on cache miss)*
- **Downward:** HUB-04, HUB-06, HUB-08, HUB-15, every Internal Spoke, every External Spoke, BRIDGE-01
- **Edges:** HUB-01→CORE-02, HUB-01→CORE-10, HUB-01→CORE-19, HUB-01→CORE-09, HUB-01→HUB-02 (5 upward) + HUB-04→HUB-01, HUB-06→HUB-01, HUB-08→HUB-01, HUB-15→HUB-01 (4 downward-only)

#### HUB-02 (Cache & State) — `Architecture/Hub/HUB-02.md` line 8
- **Upward:** `psr/simple-cache`, `psr/cache`, `ext-redis`, `ext-json` (all PSR/external — NOT Core blueprints); **Required at compile time:** CORE-15 (`SovereignStack\Core\Cache\AdapterInterface`); **Optional:** CORE-16 (`SovereignStack\Core\Crypto\EncrypterInterface` — only when `encrypt: true`)
- **Downward:** HUB-01, HUB-04, HUB-07, HUB-09, HUB-10 *(superseded source)*, HUB-15, HUB-20, every Spoke, BRIDGE-01
- **Edges:** HUB-02→CORE-15 *(REQUIRED),* HUB-02→CORE-16 *(OPTIONAL)* (2 upward). HUB-20→HUB-02 (1 downward-only; HUB-10→HUB-02 excluded as source superseded).

#### HUB-03 (Asset Engine) — `Architecture/Hub/HUB-03.md` line 14
- **Upward:** CORE-14, CORE-10, HUB-11 *(with DAG-correction note)*
- **Downward:** HUB-26
- **Edges:** HUB-03→CORE-14, HUB-03→CORE-10, HUB-03→HUB-11 (3 upward). HUB-26→HUB-03 already in HUB-26 Upward (duplicate).

#### HUB-04 (Identity) — `Architecture/Hub/HUB-04.md` line 28
- **Upward:** CORE-02, CORE-09 *(soft — audit trail)*, CORE-10, CORE-16, CORE-19, HUB-02, HUB-07, HUB-21
- **Downward:** BRIDGE-01, HUB-06, HUB-08, ISPOKE-01, ESPOKE-01
- **Edges:** HUB-04→CORE-02, HUB-04→CORE-09, HUB-04→CORE-10, HUB-04→CORE-16, HUB-04→CORE-19, HUB-04→HUB-02, HUB-04→HUB-07, HUB-04→HUB-21 (8 upward). HUB-06→HUB-04, HUB-08→HUB-04 already in their Upward (duplicates).

#### HUB-05 (Guardian / RBAC) — `Architecture/Hub/HUB-05.md` line 34
- **Upward:** HUB-04, CORE-19, HUB-02
- **Downward:** ISPOKE-01, every Spoke (UI/action role gating)
- **Edges:** HUB-05→HUB-04, HUB-05→CORE-19, HUB-05→HUB-02 (3 upward). No Hub-internal downward.

#### HUB-06 (Auditor) — `Architecture/Hub/HUB-06.md` line 40
- **Upward:** CORE-19, CORE-03, CORE-02, CORE-09, CORE-14, HUB-04, HUB-11 *(labelled "Queue" — see §7 Gap 1)*
- **Downward:** BRIDGE-01, ISPOKE-01, ISPOKE-10, HUB-15
- **Edges:** HUB-06→CORE-19, HUB-06→CORE-03, HUB-06→CORE-02, HUB-06→CORE-09, HUB-06→CORE-14, HUB-06→HUB-04, HUB-06→HUB-11 (7 upward, of which HUB-06→HUB-11 is a label-vs-INDEX contradiction). HUB-15→HUB-06 (1 downward-only).

#### HUB-07 (Rate Limiter) — `Architecture/Hub/HUB-07.md` line 46
- **Upward:** HUB-02, CORE-04
- **Downward:** HUB-04, HUB-08, HUB-12
- **Edges:** HUB-07→HUB-02, HUB-07→CORE-04 (2 upward). HUB-04→HUB-07, HUB-08→HUB-07 already in their Upward (duplicates). HUB-12→HUB-07 (1 downward-only).

#### HUB-08 (Gateway) — `Architecture/Hub/HUB-08.md` line 53
- **Upward:** CORE-04, CORE-05, CORE-06, CORE-09, CORE-10, CORE-18, HUB-04, HUB-07
- **Downward:** Every Spoke, BRIDGE-01
- **Edges:** HUB-08→CORE-04, HUB-08→CORE-05, HUB-08→CORE-06, HUB-08→CORE-09, HUB-08→CORE-10, HUB-08→CORE-18, HUB-08→HUB-04, HUB-08→HUB-07 (8 upward). No Hub-internal downward.

#### HUB-09 (Signal / Event Bus) — `Architecture/Hub/HUB-09.md` line 59
- **Upward:** CORE-03, HUB-02, HUB-10 *(superseded — see §7 Gap 2)*
- **Downward:** HUB-17, HUB-22, any Spoke reacting to Hub-tier state changes
- **Edges:** HUB-09→CORE-03, HUB-09→HUB-02, HUB-09→HUB-10 (3 upward, of which HUB-09→HUB-10 has superseded target). HUB-17→HUB-09 already in HUB-17 Upward (duplicate). HUB-22→HUB-09 (1 downward-only).

#### HUB-11 (Cloud Storage) — `Architecture/Hub/HUB-11.md` line 66
- **Upward:** CORE-14, CORE-10
- **Downward:** HUB-03, HUB-18, HUB-23
- **Edges:** HUB-11→CORE-14, HUB-11→CORE-10 (2 upward). All Hub-internal downward already in their Upward (duplicates).

#### HUB-12 (Notify) — `Architecture/Hub/HUB-12.md` line 72
- **Upward:** HUB-04, HUB-10 *(superseded)*, CORE-12
- **Downward:** HUB-23, HUB-22, any Spoke sending user-facing notifications
- **Edges:** HUB-12→HUB-04, HUB-12→HUB-10 *(superseded)*, HUB-12→CORE-12 (3 upward). HUB-23→HUB-12 already in HUB-23 Upward (duplicate). HUB-22→HUB-12 (1 downward-only).

#### HUB-13 (Translator) — `Architecture/Hub/HUB-13.md` line 78
- **Upward:** CORE-10, HUB-02
- **Downward:** HUB-19, HUB-26
- **Edges:** HUB-13→CORE-10, HUB-13→HUB-02 (2 upward). All Hub-internal downward already in their Upward (duplicates).

#### HUB-14 (Search) — `Architecture/Hub/HUB-14.md` line 83
- **Upward:** CORE-19, HUB-10 *(superseded)*
- **Downward:** HUB-08, any Spoke implementing `SearchableInterface`
- **Edges:** HUB-14→CORE-19, HUB-14→HUB-10 *(superseded)* (2 upward). HUB-08→HUB-14 (1 downward-only).

#### HUB-15 (Pulse / Health Check) — `Architecture/Hub/HUB-15.md` line 89
- **Upward:** `psr/log`, `psr/event-dispatcher`, `psr/cache`, `psr/simple-cache` (via HUB-02), `ext-curl`; **Required at compile time:** CORE-02, CORE-10, HUB-02; **Optional:** CORE-19 (only when `hub_health_event_log` table is in use)
- **Downward (regular):** CORE-01, BRIDGE-01, ISPOKE-01, DEPLOY-01, HUB-08 *(future dynamic `ServiceRegistry` consults HUB-15 state)*
- **Downward (reverse — see §7 Gap 3):** "Every Hub service (HUB-01, HUB-02, HUB-04, HUB-06, HUB-08, HUB-19, HUB-20) — owns its own `/health` endpoint per the contract in this blueprint; HUB-15 polls it." *(literal text says HUB-15 polls — these are semantically Upward but declared in Downward section)*
- **Edges:** HUB-15→CORE-02 *(REQUIRED, COMPILE),* HUB-15→CORE-10 *(REQUIRED, COMPILE),* HUB-15→CORE-19 *(OPTIONAL, COMPILE),* HUB-15→HUB-02 *(REQUIRED, COMPILE)* (4 upward) + HUB-15→HUB-01, HUB-15→HUB-04, HUB-15→HUB-06, HUB-15→HUB-08, HUB-15→HUB-19, HUB-15→HUB-20 (6 reverse-downward) + HUB-08→HUB-15 (1 regular downward — creates bidirectional cycle HUB-08↔HUB-15 with HUB-15→HUB-08, see §7 Gap 4).

#### HUB-16 (Hub Weaver / Merge Gate) — `Architecture/Hub/HUB-16.md` line 95
- **Upward:** CORE-01 *(implemented)*, HUB-15 *(not implemented)*
- **Downward:** "every other Hub component — this is the 'Merge Gate' for the tier per the original design intent" *(too generic to enumerate — see §7 Gap 5)*
- **Edges:** HUB-16→CORE-01, HUB-16→HUB-15 (2 upward). Generic downward skipped.

#### HUB-17 (Webhook Nexus) — `Architecture/Hub/HUB-17.md` line 101
- **Direct Hub:** HUB-09, HUB-10 *(superseded)*, HUB-06, HUB-08
- **Transitive Core:** CORE-06, CORE-04, CORE-19, CORE-03
- **Downward:** HUB-22
- **Edges:** HUB-17→HUB-09, HUB-17→HUB-10 *(superseded)*, HUB-17→HUB-06, HUB-17→HUB-08, HUB-17→CORE-06, HUB-17→CORE-04, HUB-17→CORE-19, HUB-17→CORE-03 (8 upward). HUB-22→HUB-17 already in HUB-22 Upward (duplicate).

#### HUB-18 (Media Forge) — `Architecture/Hub/HUB-18.md` line 107
- **Direct Hub:** HUB-11, HUB-10 *(superseded)*, HUB-02
- **Transitive Core:** CORE-14, CORE-19, CORE-15
- **Downward:** (none declared)
- **Edges:** HUB-18→HUB-11, HUB-18→HUB-10 *(superseded)*, HUB-18→HUB-02, HUB-18→CORE-14, HUB-18→CORE-19, HUB-18→CORE-15 (6 upward).

#### HUB-19 (Validation) — `Architecture/Hub/HUB-19.md` line 112
- **Upward:** `php:^8.3`, `ext-filter`, `ext-mbstring`, `ext-pcre`; **Compile-time:** CORE-19 *(required only by `UniqueRule`)*; **Optional:** CORE-09 *(via `psr/log:^3.0`)*, HUB-13
- **Downward:** Every Hub controller (HUB-01, HUB-04, HUB-06, HUB-08, HUB-17, HUB-20), every Spoke controller, BRIDGE-01, CORE-08
- **Edges:** HUB-19→CORE-19 *(REQUIRED, COMPILE)*, HUB-19→CORE-09 *(OPTIONAL)*, HUB-19→HUB-13 *(OPTIONAL)* (3 upward). HUB-01→HUB-19, HUB-04→HUB-19, HUB-06→HUB-19, HUB-08→HUB-19, HUB-17→HUB-19, HUB-20→HUB-19 (6 downward-only). CORE-08→HUB-19 is CAPABILITY edge (Core→Hub) — not in Hub-tier DAG inventory but noted.

#### HUB-20 (Vault) — `Architecture/Hub/HUB-20.md` line 118
- **Upward:** CORE-16 *(hard)*, CORE-19 *(hard)*, CORE-02 *(hard)*, HUB-06 *(hard)*, HUB-04 *(hard)*, HUB-25 *(soft — superseded)*
- **Downward:** HUB-04, HUB-09, BRIDGE-01, HUB-22, HUB-25 *(superseded source)*
- **Edges:** HUB-20→CORE-16 *(REQUIRED)*, HUB-20→CORE-19 *(REQUIRED)*, HUB-20→CORE-02 *(REQUIRED)*, HUB-20→HUB-06 *(REQUIRED)*, HUB-20→HUB-04 *(REQUIRED)*, HUB-20→HUB-25 *(OPTIONAL, superseded)* (6 upward). HUB-04→HUB-20, HUB-09→HUB-20 (2 downward-only). HUB-22→HUB-20 already in HUB-22 Upward (duplicate). HUB-25→HUB-20 has superseded source — skip.

#### HUB-21 (Sovereign Nexus / Tenancy) — `Architecture/Hub/HUB-21.md` line 124
- **Direct Hub:** HUB-01, HUB-04, HUB-08
- **Transitive Core:** CORE-19, CORE-10, CORE-02
- **Downward:** HUB-01, HUB-02, HUB-11, ISPOKE-01, every tenant-scoped Spoke
- **Edges:** HUB-21→HUB-01, HUB-21→HUB-04, HUB-21→HUB-08, HUB-21→CORE-19, HUB-21→CORE-10, HUB-21→CORE-02 (6 upward). HUB-01→HUB-21, HUB-02→HUB-21, HUB-11→HUB-21 (3 downward-only — note: HUB-01→HUB-21 is a NEW cycle with HUB-21→HUB-01; see §7 Gap 6).

#### HUB-22 (Ledger / Billing) — `Architecture/Hub/HUB-22.md` line 131
- **Direct Hub:** HUB-21, HUB-20, HUB-06, HUB-17
- **Transitive Core:** CORE-19, CORE-03
- **Downward:** any Spoke gating features on subscription status
- **Edges:** HUB-22→HUB-21, HUB-22→HUB-20, HUB-22→HUB-06, HUB-22→HUB-17, HUB-22→CORE-19, HUB-22→CORE-03 (6 upward). No Hub-internal downward.

#### HUB-23 (Reporter) — `Architecture/Hub/HUB-23.md` line 137
- **Direct Hub:** HUB-11, HUB-10 *(superseded)*, HUB-12, HUB-25 *(superseded — Scheduler, added; was referenced in prose as "to be defined" but omitted from the formal list even after HUB-25 was written)*
- **Transitive Core:** CORE-19, CORE-14
- **Downward:** (none declared)
- **Edges:** HUB-23→HUB-11, HUB-23→HUB-10 *(superseded)*, HUB-23→HUB-12, HUB-23→HUB-25 *(superseded)*, HUB-23→CORE-19, HUB-23→CORE-14 (6 upward).

#### HUB-24 (GraphQL Registry) — `Architecture/Hub/HUB-24.md` line 143
- **Direct Hub:** HUB-08, HUB-04, HUB-05
- **Transitive Core:** CORE-02, CORE-06, CORE-04
- **Downward:** (none declared)
- **Edges:** HUB-24→HUB-08, HUB-24→HUB-04, HUB-24→HUB-05, HUB-24→CORE-02, HUB-24→CORE-06, HUB-24→CORE-04 (6 upward).

#### HUB-26 (UI Elements) — `Architecture/Hub/HUB-26.md` line 148
- **Direct Hub:** HUB-03, HUB-13
- **Transitive Core:** CORE-11, CORE-12
- **Downward:** ISPOKE-01, ESPOKE-01, every Spoke
- **Edges:** HUB-26→HUB-03, HUB-26→HUB-13, HUB-26→CORE-11, HUB-26→CORE-12 (4 upward). No Hub-internal downward.

#### HUB-27 (Sentinel / Headers) — `Architecture/Hub/HUB-27.md` line 154
- **Direct Hub:** HUB-08, HUB-01
- **Transitive Core:** CORE-04, CORE-05
- **Downward:** (none declared)
- **Edges:** HUB-27→HUB-08, HUB-27→HUB-01, HUB-27→CORE-04, HUB-27→CORE-05 (4 upward).

#### HUB-28 (Versioner) — `Architecture/Hub/HUB-28.md` line 159
- **Direct Hub:** HUB-08, HUB-15
- **Transitive Core:** CORE-06, CORE-18
- **Downward:** (none declared)
- **Edges:** HUB-28→HUB-08, HUB-28→HUB-15, HUB-28→CORE-06, HUB-28→CORE-18 (4 upward).

#### HUB-29 (Hub Spec / Testing) — `Architecture/Hub/HUB-29.md` line 164
- **Direct Hub:** HUB-15, HUB-16
- **Transitive Core:** CORE-20, CORE-08
- **Downward:** (none declared)
- **Edges:** HUB-29→HUB-15, HUB-29→HUB-16, HUB-29→CORE-20, HUB-29→CORE-08 (4 upward).

#### HUB-30 (Hub CLI) — `Architecture/Hub/HUB-30.md` line 169
- **Direct Hub:** HUB-21, HUB-15, HUB-10 *(superseded)*, HUB-02
- **Transitive Core:** CORE-13, CORE-20
- **Downward:** (none declared)
- **Edges:** HUB-30→HUB-21, HUB-30→HUB-15, HUB-30→HUB-10 *(superseded)*, HUB-30→HUB-02, HUB-30→CORE-13, HUB-30→CORE-20 (6 upward).

#### HUB-31 (Real-time Analytics) — `Architecture/Hub/HUB-31.md` line 174
- **Direct Hub:** HUB-02, HUB-10 *(superseded — durable-write queue)*, HUB-25 *(superseded — scheduled rollup compaction)*, HUB-21
- **Transitive Core:** CORE-19, CORE-02, CORE-18
- **Downward:** ISPOKE-05, ISPOKE-12, ISPOKE-13
- **Edges:** HUB-31→HUB-02, HUB-31→HUB-10 *(superseded)*, HUB-31→HUB-25 *(superseded)*, HUB-31→HUB-21, HUB-31→CORE-19, HUB-31→CORE-02, HUB-31→CORE-18 (7 upward). No Hub-internal downward.

---

## §3. Step 3 — Hub implementation packages (filesystem reality)

`/home/z/my-project/packages/hub/` contains exactly **2 packages**:

| Package path | composer.json `name` | Blueprint ID (from `description`) | `src/` PHP files | `tests/` PHP files | Path-repo targets |
|---|---|---|---|---|---|
| `packages/hub/config/` | `sovereign-stack/hub-config` | HUB-01 (Sovereign Hub Config & Flags) | 13 | 5 | `../../core/container`, `../../core/config` |
| `packages/hub/identity/` | `sovereign-stack/hub-identity` | HUB-04 (Global Identity & Authentication) | 15 | 5 | *(none — only `require` entries)* |

**Total implemented Hub packages: 2** (representing 2 of 29 active Hub blueprints — 6.9% implementation depth).

Cross-checked against other directories:
- `/home/z/my-project/orchestrator/` → `sovereign-stack/orchestrator` description: "CORE-01: Polyrepo Orchestrator" (NOT a Hub package)
- `/home/z/my-project/packages/spoke/internal/` → 3 packages (lms, showcase, codex) — all ESPOKE/ISPOKE
- `/home/z/my-project/packages/spoke/external/` → 1 package (canvas) — ESPOKE-01
- `/home/z/my-project/packages/bridge/` → 1 package (vanguard) — BRIDGE-01

No Hub implementations exist outside `packages/hub/`. The 27 unimplemented Hub blueprints (HUB-02, HUB-03, HUB-05, HUB-06, HUB-07, HUB-08, HUB-09, HUB-11, HUB-12, HUB-13, HUB-14, HUB-15, HUB-16, HUB-17, HUB-18, HUB-19, HUB-20, HUB-21, HUB-22, HUB-23, HUB-24, HUB-26, HUB-27, HUB-28, HUB-29, HUB-30, HUB-31) have NO code on disk.

---

## §4. Step 4 — Verified edges (composer.json + source imports)

### §4.1 HUB-01 (config package) — verified edges

**`packages/hub/config/composer.json` `require` (lines 7–14):**
```
"sovereign-stack/core-container": "*",   → CORE-02
"sovereign-stack/core-config": "*",      → CORE-10
```
(plus `php`, `ext-json`, `ext-hash`, `psr/container` — not Core blueprints)

**Source imports (`rg "^use SovereignStack" packages/hub/config/src/`):**
```
packages/hub/config/src/HubConfigRegistry.php:7:use SovereignStack\Core\Config\ConfigInterface;
```
(1 source-level import → CORE-10)

**Verified Hub→Core edges for HUB-01 = 2** (both via composer.json; 1 also confirmed at source level)
- HUB-01 → CORE-02 (COMPILE — composer.json `require`)
- HUB-01 → CORE-10 (COMPILE — composer.json `require` + source `ConfigInterface`)

### §4.2 HUB-04 (identity package) — verified edges

**`packages/hub/identity/composer.json` `require` (lines 7–22):**
```
"sovereign-stack/core-container": "*",          → CORE-02
"sovereign-stack/core-config": "*",             → CORE-10
"sovereign-stack/core-crypto": "*",             → CORE-16
"sovereign-stack/core-dbal": "*",               → CORE-19
"sovereign-stack/core-http-message": "*",       → CORE-04
"sovereign-stack/core-kernel": "*",             → CORE-18
"sovereign-stack/core-event-dispatcher": "*",   → CORE-03
"sovereign-stack/core-logger": "*",             → CORE-09
```
(plus `php`, `ext-json`, `ext-openssl`, `psr/container`, `psr/http-message`, `psr/http-server-middleware`, `psr/log` — PSR contracts not Core blueprints per se)

**Source imports (`rg "^use SovereignStack\\Core" packages/hub/identity/src/`):**
```
packages/hub/identity/src/Application/Service/IdentityApplicationService.php:4:use SovereignStack\Core\Crypto\PasswordHasher;            → CORE-16
packages/hub/identity/src/Http/AuthMiddleware.php:8:use SovereignStack\Core\Kernel\RequestContext;                              → CORE-18
packages/hub/identity/src/Infrastructure/Persistence/MySQLUserRepository.php:5:use SovereignStack\Core\Database\ConnectionInterface;  → CORE-19
```
(3 distinct cross-package source imports into Core. CORE-10 is composer-only — no direct source import into Core\Config namespace exists, though `psr/container` is imported.)

**Verified Hub→Core edges for HUB-04 = 8** (all via composer.json; 3 also confirmed at source level)
- HUB-04 → CORE-02 (COMPILE — composer.json `require` core-container)
- HUB-04 → CORE-10 (COMPILE — composer.json `require` core-config)
- HUB-04 → CORE-16 (COMPILE — composer.json `require` core-crypto + source `PasswordHasher`)
- HUB-04 → CORE-19 (COMPILE — composer.json `require` core-dbal + source `ConnectionInterface`)
- HUB-04 → CORE-04 (COMPILE — composer.json `require` core-http-message)
- HUB-04 → CORE-18 (COMPILE — composer.json `require` core-kernel + source `RequestContext`)
- HUB-04 → CORE-03 (COMPILE — composer.json `require` core-event-dispatcher)
- HUB-04 → CORE-09 (COMPILE — composer.json `require` core-logger)

### §4.3 Hub→Hub verified edges

`rg "sovereign-stack/hub-" packages/hub/*/composer.json` returned only the two `name` fields (no `require` entries). Source-level search `rg "^use SovereignStack\\\\Hub\\\\" packages/hub/{identity,config}/src/` returned ONLY intra-package imports (`SovereignStack\Hub\Identity\*` inside identity package, `SovereignStack\Hub\Config\*` inside config package).

**Verified Hub→Hub edges = 0.**

No Hub package's `composer.json` requires any other Hub package, and no Hub package's source code imports any `SovereignStack\Hub\<OtherPackage>\*` class. The Hub tier is currently a contract-coupled set of independent packages — every cross-Hub dependency is declared in blueprint prose but NOT expressed in code or composer.

---

## §5. Step 5 — Master edge inventory

**One row per unique `(source, target)` tuple.** Edges to superseded HUB-10/HUB-25 are included for completeness and flagged. Downward-only declarations (producer-acknowledged consumer that consumer doesn't formally declare) are marked with `↓-only` in the Evidence column. Edges with target as a non-Hub/non-Core (e.g., BRIDGE-01, ISPOKE, ESPOKE) are out of scope for Hub-tier DAG and omitted.

### §5.1 Hub→Hub edges (87 unique)

| # | Source | Target | Edge Type | Required-ness | Declared By | Verified | Status | Evidence |
|---|---|---|---|---|---|---|---|---|
| 1 | HUB-01 | HUB-02 | UNKNOWN | UNKNOWN | HUB-01 Up | ✗ | DECLARED_ONLY | HUB-01.md line 2 |
| 2 | HUB-01 | HUB-19 | UNKNOWN | UNKNOWN | HUB-19 Down (↓-only) | ✗ | DECLARED_ONLY | HUB-19.md line 113 |
| 3 | HUB-01 | HUB-21 | UNKNOWN | UNKNOWN | HUB-21 Down (↓-only) — also cycle w/ row 56 | ✗ | DECLARED_ONLY | HUB-21.md line 126 |
| 4 | HUB-02 | HUB-21 | UNKNOWN | UNKNOWN | HUB-21 Down (↓-only) | ✗ | DECLARED_ONLY | HUB-21.md line 126 |
| 5 | HUB-03 | HUB-11 | UNKNOWN | UNKNOWN | HUB-03 Up | ✗ | DECLARED_ONLY | HUB-03.md line 14 |
| 6 | HUB-04 | HUB-01 | UNKNOWN | UNKNOWN | HUB-01 Down (↓-only) | ✗ | DECLARED_ONLY | HUB-01.md line 3 |
| 7 | HUB-04 | HUB-02 | UNKNOWN | UNKNOWN | HUB-04 Up + HUB-02 Down | ✗ | DECLARED_ONLY | HUB-04.md line 28; HUB-02.md line 9 |
| 8 | HUB-04 | HUB-07 | UNKNOWN | UNKNOWN | HUB-04 Up + HUB-07 Down | ✗ | DECLARED_ONLY | HUB-04.md line 28; HUB-07.md line 47 |
| 9 | HUB-04 | HUB-19 | UNKNOWN | UNKNOWN | HUB-19 Down (↓-only) | ✗ | DECLARED_ONLY | HUB-19.md line 113 |
| 10 | HUB-04 | HUB-20 | UNKNOWN | UNKNOWN | HUB-20 Down (↓-only) | ✗ | DECLARED_ONLY | HUB-20.md line 119 |
| 11 | HUB-04 | HUB-21 | UNKNOWN | UNKNOWN | HUB-04 Up + HUB-21 Down | ✗ | DECLARED_ONLY | HUB-04.md line 28; HUB-21.md line 126 |
| 12 | HUB-05 | HUB-02 | UNKNOWN | UNKNOWN | HUB-05 Up | ✗ | DECLARED_ONLY | HUB-05.md line 34 |
| 13 | HUB-05 | HUB-04 | UNKNOWN | UNKNOWN | HUB-05 Up | ✗ | DECLARED_ONLY | HUB-05.md line 34 |
| 14 | HUB-06 | HUB-01 | UNKNOWN | UNKNOWN | HUB-01 Down (↓-only) | ✗ | DECLARED_ONLY | HUB-01.md line 3 |
| 15 | HUB-06 | HUB-04 | UNKNOWN | UNKNOWN | HUB-06 Up + HUB-04 Down | ✗ | DECLARED_ONLY | HUB-06.md line 40; HUB-04.md line 29 |
| 16 | HUB-06 | HUB-11 | UNKNOWN | UNKNOWN | HUB-06 Up *(labelled "Queue" — see §7 Gap 1)* | ✗ | DECLARED_ONLY | HUB-06.md line 40 |
| 17 | HUB-06 | HUB-19 | UNKNOWN | UNKNOWN | HUB-19 Down (↓-only) | ✗ | DECLARED_ONLY | HUB-19.md line 113 |
| 18 | HUB-07 | HUB-02 | UNKNOWN | UNKNOWN | HUB-07 Up + HUB-02 Down | ✗ | DECLARED_ONLY | HUB-07.md line 46; HUB-02.md line 9 |
| 19 | HUB-08 | HUB-01 | UNKNOWN | UNKNOWN | HUB-01 Down (↓-only) | ✗ | DECLARED_ONLY | HUB-01.md line 3 |
| 20 | HUB-08 | HUB-04 | UNKNOWN | UNKNOWN | HUB-08 Up + HUB-04 Down | ✗ | DECLARED_ONLY | HUB-08.md line 53; HUB-04.md line 29 |
| 21 | HUB-08 | HUB-07 | UNKNOWN | UNKNOWN | HUB-08 Up + HUB-07 Down | ✗ | DECLARED_ONLY | HUB-08.md line 53; HUB-07.md line 47 |
| 22 | HUB-08 | HUB-14 | UNKNOWN | UNKNOWN | HUB-14 Down (↓-only) | ✗ | DECLARED_ONLY | HUB-14.md line 84 |
| 23 | HUB-08 | HUB-15 | UNKNOWN | UNKNOWN | HUB-15 Down (↓-only) — cycle w/ row 38 | ✗ | DECLARED_ONLY | HUB-15.md line 90 |
| 24 | HUB-08 | HUB-19 | UNKNOWN | UNKNOWN | HUB-19 Down (↓-only) | ✗ | DECLARED_ONLY | HUB-19.md line 113 |
| 25 | HUB-09 | HUB-02 | UNKNOWN | UNKNOWN | HUB-09 Up + HUB-02 Down | ✗ | DECLARED_ONLY | HUB-09.md line 59; HUB-02.md line 9 |
| 26 | HUB-09 | HUB-10 *(SUPERSEDED)* | UNKNOWN | UNKNOWN | HUB-09 Up | ✗ | DECLARED_ONLY *(target superseded)* | HUB-09.md line 59 |
| 27 | HUB-09 | HUB-20 | UNKNOWN | UNKNOWN | HUB-20 Down (↓-only) | ✗ | DECLARED_ONLY | HUB-20.md line 119 |
| 28 | HUB-11 | HUB-21 | UNKNOWN | UNKNOWN | HUB-21 Down (↓-only) | ✗ | DECLARED_ONLY | HUB-21.md line 126 |
| 29 | HUB-12 | HUB-04 | UNKNOWN | UNKNOWN | HUB-12 Up | ✗ | DECLARED_ONLY | HUB-12.md line 72 |
| 30 | HUB-12 | HUB-07 | UNKNOWN | UNKNOWN | HUB-07 Down (↓-only) | ✗ | DECLARED_ONLY | HUB-07.md line 47 |
| 31 | HUB-12 | HUB-10 *(SUPERSEDED)* | UNKNOWN | UNKNOWN | HUB-12 Up | ✗ | DECLARED_ONLY *(target superseded)* | HUB-12.md line 72 |
| 32 | HUB-13 | HUB-02 | UNKNOWN | UNKNOWN | HUB-13 Up + HUB-02 Down | ✗ | DECLARED_ONLY | HUB-13.md line 78; HUB-02.md line 9 |
| 33 | HUB-14 | HUB-10 *(SUPERSEDED)* | UNKNOWN | UNKNOWN | HUB-14 Up | ✗ | DECLARED_ONLY *(target superseded)* | HUB-14.md line 83 |
| 34 | HUB-15 | HUB-01 | UNKNOWN | UNKNOWN | HUB-15 reverse-Down + HUB-01 Down | ✗ | DECLARED_ONLY | HUB-15.md line 90; HUB-01.md line 3 |
| 35 | HUB-15 | HUB-02 | UNKNOWN | REQUIRED | HUB-15 Up + HUB-02 Down + HUB-15 reverse-Down | ✗ | DECLARED_ONLY | HUB-15.md line 89; HUB-02.md line 9 |
| 36 | HUB-15 | HUB-04 | UNKNOWN | UNKNOWN | HUB-15 reverse-Down | ✗ | DECLARED_ONLY | HUB-15.md line 90 |
| 37 | HUB-15 | HUB-06 | UNKNOWN | UNKNOWN | HUB-06 Down + HUB-15 reverse-Down | ✗ | DECLARED_ONLY | HUB-06.md line 41; HUB-15.md line 90 |
| 38 | HUB-15 | HUB-08 | UNKNOWN | UNKNOWN | HUB-15 reverse-Down — cycle w/ row 23 | ✗ | DECLARED_ONLY *(cycle)* | HUB-15.md line 90 |
| 39 | HUB-15 | HUB-19 | UNKNOWN | UNKNOWN | HUB-15 reverse-Down | ✗ | DECLARED_ONLY | HUB-15.md line 90 |
| 40 | HUB-15 | HUB-20 | UNKNOWN | UNKNOWN | HUB-15 reverse-Down | ✗ | DECLARED_ONLY | HUB-15.md line 90 |
| 41 | HUB-16 | HUB-15 | UNKNOWN | UNKNOWN | HUB-16 Up | ✗ | DECLARED_ONLY | HUB-16.md line 95 |
| 42 | HUB-17 | HUB-06 | UNKNOWN | UNKNOWN | HUB-17 Direct Hub | ✗ | DECLARED_ONLY | HUB-17.md line 101 |
| 43 | HUB-17 | HUB-08 | UNKNOWN | UNKNOWN | HUB-17 Direct Hub | ✗ | DECLARED_ONLY | HUB-17.md line 101 |
| 44 | HUB-17 | HUB-09 | UNKNOWN | UNKNOWN | HUB-17 Direct Hub + HUB-09 Down | ✗ | DECLARED_ONLY | HUB-17.md line 101; HUB-09.md line 60 |
| 45 | HUB-17 | HUB-10 *(SUPERSEDED)* | UNKNOWN | UNKNOWN | HUB-17 Direct Hub | ✗ | DECLARED_ONLY *(target superseded)* | HUB-17.md line 101 |
| 46 | HUB-17 | HUB-19 | UNKNOWN | UNKNOWN | HUB-19 Down (↓-only) | ✗ | DECLARED_ONLY | HUB-19.md line 113 |
| 47 | HUB-18 | HUB-02 | UNKNOWN | UNKNOWN | HUB-18 Direct Hub | ✗ | DECLARED_ONLY | HUB-18.md line 107 |
| 48 | HUB-18 | HUB-10 *(SUPERSEDED)* | UNKNOWN | UNKNOWN | HUB-18 Direct Hub | ✗ | DECLARED_ONLY *(target superseded)* | HUB-18.md line 107 |
| 49 | HUB-18 | HUB-11 | UNKNOWN | UNKNOWN | HUB-18 Direct Hub + HUB-11 Down | ✗ | DECLARED_ONLY | HUB-18.md line 107; HUB-11.md line 67 |
| 50 | HUB-19 | HUB-13 | UNKNOWN | OPTIONAL | HUB-19 Up + HUB-13 Down | ✗ | DECLARED_ONLY | HUB-19.md line 112; HUB-13.md line 79 |
| 51 | HUB-20 | HUB-02 | UNKNOWN | UNKNOWN | HUB-02 Down (↓-only) | ✗ | DECLARED_ONLY | HUB-02.md line 9 |
| 52 | HUB-20 | HUB-04 | UNKNOWN | REQUIRED | HUB-20 Up + HUB-20 Down *(self-acknowledgment)* | ✗ | DECLARED_ONLY | HUB-20.md line 118, 119 |
| 53 | HUB-20 | HUB-06 | UNKNOWN | REQUIRED | HUB-20 Up | ✗ | DECLARED_ONLY | HUB-20.md line 118 |
| 54 | HUB-20 | HUB-19 | UNKNOWN | UNKNOWN | HUB-19 Down (↓-only) | ✗ | DECLARED_ONLY | HUB-19.md line 113 |
| 55 | HUB-20 | HUB-25 *(SUPERSEDED)* | UNKNOWN | OPTIONAL | HUB-20 Up | ✗ | DECLARED_ONLY *(target superseded)* | HUB-20.md line 118 |
| 56 | HUB-21 | HUB-01 | UNKNOWN | UNKNOWN | HUB-21 Direct Hub + HUB-21 Down — cycle w/ row 3 | ✗ | DECLARED_ONLY *(cycle)* | HUB-21.md line 124, 126 |
| 57 | HUB-21 | HUB-04 | UNKNOWN | UNKNOWN | HUB-21 Direct Hub | ✗ | DECLARED_ONLY | HUB-21.md line 124 |
| 58 | HUB-21 | HUB-08 | UNKNOWN | UNKNOWN | HUB-21 Direct Hub | ✗ | DECLARED_ONLY | HUB-21.md line 124 |
| 59 | HUB-22 | HUB-06 | UNKNOWN | UNKNOWN | HUB-22 Direct Hub | ✗ | DECLARED_ONLY | HUB-22.md line 131 |
| 60 | HUB-22 | HUB-09 | UNKNOWN | UNKNOWN | HUB-09 Down (↓-only) | ✗ | DECLARED_ONLY | HUB-09.md line 60 |
| 61 | HUB-22 | HUB-12 | UNKNOWN | UNKNOWN | HUB-12 Down (↓-only) | ✗ | DECLARED_ONLY | HUB-12.md line 73 |
| 62 | HUB-22 | HUB-17 | UNKNOWN | UNKNOWN | HUB-22 Direct Hub + HUB-17 Down | ✗ | DECLARED_ONLY | HUB-22.md line 131; HUB-17.md line 103 |
| 63 | HUB-22 | HUB-20 | UNKNOWN | UNKNOWN | HUB-22 Direct Hub + HUB-20 Down | ✗ | DECLARED_ONLY | HUB-22.md line 131; HUB-20.md line 119 |
| 64 | HUB-22 | HUB-21 | UNKNOWN | UNKNOWN | HUB-22 Direct Hub | ✗ | DECLARED_ONLY | HUB-22.md line 131 |
| 65 | HUB-23 | HUB-10 *(SUPERSEDED)* | UNKNOWN | UNKNOWN | HUB-23 Direct Hub | ✗ | DECLARED_ONLY *(target superseded)* | HUB-23.md line 137 |
| 66 | HUB-23 | HUB-11 | UNKNOWN | UNKNOWN | HUB-23 Direct Hub + HUB-11 Down | ✗ | DECLARED_ONLY | HUB-23.md line 137; HUB-11.md line 67 |
| 67 | HUB-23 | HUB-12 | UNKNOWN | UNKNOWN | HUB-23 Direct Hub + HUB-12 Down | ✗ | DECLARED_ONLY | HUB-23.md line 137; HUB-12.md line 73 |
| 68 | HUB-23 | HUB-25 *(SUPERSEDED)* | UNKNOWN | UNKNOWN | HUB-23 Direct Hub | ✗ | DECLARED_ONLY *(target superseded)* | HUB-23.md line 137 |
| 69 | HUB-24 | HUB-04 | UNKNOWN | UNKNOWN | HUB-24 Direct Hub | ✗ | DECLARED_ONLY | HUB-24.md line 143 |
| 70 | HUB-24 | HUB-05 | UNKNOWN | UNKNOWN | HUB-24 Direct Hub | ✗ | DECLARED_ONLY | HUB-24.md line 143 |
| 71 | HUB-24 | HUB-08 | UNKNOWN | UNKNOWN | HUB-24 Direct Hub | ✗ | DECLARED_ONLY | HUB-24.md line 143 |
| 72 | HUB-26 | HUB-03 | UNKNOWN | UNKNOWN | HUB-26 Direct Hub + HUB-03 Down | ✗ | DECLARED_ONLY | HUB-26.md line 148; HUB-03.md line 17 |
| 73 | HUB-26 | HUB-13 | UNKNOWN | UNKNOWN | HUB-26 Direct Hub + HUB-13 Down | ✗ | DECLARED_ONLY | HUB-26.md line 148; HUB-13.md line 79 |
| 74 | HUB-27 | HUB-01 | UNKNOWN | UNKNOWN | HUB-27 Direct Hub | ✗ | DECLARED_ONLY | HUB-27.md line 154 |
| 75 | HUB-27 | HUB-08 | UNKNOWN | UNKNOWN | HUB-27 Direct Hub | ✗ | DECLARED_ONLY | HUB-27.md line 154 |
| 76 | HUB-28 | HUB-08 | UNKNOWN | UNKNOWN | HUB-28 Direct Hub | ✗ | DECLARED_ONLY | HUB-28.md line 159 |
| 77 | HUB-28 | HUB-15 | UNKNOWN | UNKNOWN | HUB-28 Direct Hub | ✗ | DECLARED_ONLY | HUB-28.md line 159 |
| 78 | HUB-29 | HUB-15 | UNKNOWN | UNKNOWN | HUB-29 Direct Hub | ✗ | DECLARED_ONLY | HUB-29.md line 164 |
| 79 | HUB-29 | HUB-16 | UNKNOWN | UNKNOWN | HUB-29 Direct Hub | ✗ | DECLARED_ONLY | HUB-29.md line 164 |
| 80 | HUB-30 | HUB-02 | UNKNOWN | UNKNOWN | HUB-30 Direct Hub | ✗ | DECLARED_ONLY | HUB-30.md line 169 |
| 81 | HUB-30 | HUB-10 *(SUPERSEDED)* | UNKNOWN | UNKNOWN | HUB-30 Direct Hub | ✗ | DECLARED_ONLY *(target superseded)* | HUB-30.md line 169 |
| 82 | HUB-30 | HUB-15 | UNKNOWN | UNKNOWN | HUB-30 Direct Hub | ✗ | DECLARED_ONLY | HUB-30.md line 169 |
| 83 | HUB-30 | HUB-21 | UNKNOWN | UNKNOWN | HUB-30 Direct Hub | ✗ | DECLARED_ONLY | HUB-30.md line 169 |
| 84 | HUB-31 | HUB-02 | UNKNOWN | UNKNOWN | HUB-31 Direct Hub | ✗ | DECLARED_ONLY | HUB-31.md line 174 |
| 85 | HUB-31 | HUB-10 *(SUPERSEDED)* | UNKNOWN | UNKNOWN | HUB-31 Direct Hub | ✗ | DECLARED_ONLY *(target superseded)* | HUB-31.md line 174 |
| 86 | HUB-31 | HUB-21 | UNKNOWN | UNKNOWN | HUB-31 Direct Hub | ✗ | DECLARED_ONLY | HUB-31.md line 174 |
| 87 | HUB-31 | HUB-25 *(SUPERSEDED)* | UNKNOWN | UNKNOWN | HUB-31 Direct Hub | ✗ | DECLARED_ONLY *(target superseded)* | HUB-31.md line 174 |

**Decomposition of Hub→Hub edges:**
- 61 declared in some Upward list (Upward or Direct Hub)
- 26 declared ONLY in Downward lists (producer-acknowledged but consumer-undeclared — asymmetric drift)
- 22 of the 87 are declared from BOTH sides (consumer Up + producer Down) — consistent declarations
- 11 of the 87 have superseded targets (8 to HUB-10, 3 to HUB-25) — see §7 Gap 2
- 0 of the 87 are verified in code

### §5.2 Hub→Core edges (74 unique: 71 declared + 3 undeclared-verified)

| # | Source | Target | Edge Type | Required-ness | Declared By | Verified | Status | Evidence |
|---|---|---|---|---|---|---|---|---|
| 1 | HUB-01 | CORE-02 | COMPILE | UNKNOWN | HUB-01 Up | ✓ (composer) | **VERIFIED** | packages/hub/config/composer.json line 12 |
| 2 | HUB-01 | CORE-09 | UNKNOWN | OPTIONAL | HUB-01 Up *(soft)* | ✗ | DECLARED_ONLY | HUB-01.md line 2 |
| 3 | HUB-01 | CORE-10 | COMPILE | UNKNOWN | HUB-01 Up | ✓ (composer + src) | **VERIFIED** | packages/hub/config/composer.json line 13; src/HubConfigRegistry.php:7 |
| 4 | HUB-01 | CORE-19 | UNKNOWN | UNKNOWN | HUB-01 Up | ✗ | DECLARED_ONLY | HUB-01.md line 2 |
| 5 | HUB-02 | CORE-15 | COMPILE | REQUIRED | HUB-02 Up | ✗ | DECLARED_ONLY | HUB-02.md line 8 |
| 6 | HUB-02 | CORE-16 | UNKNOWN | OPTIONAL | HUB-02 Up | ✗ | DECLARED_ONLY | HUB-02.md line 8 |
| 7 | HUB-03 | CORE-10 | UNKNOWN | UNKNOWN | HUB-03 Up | ✗ | DECLARED_ONLY | HUB-03.md line 14 |
| 8 | HUB-03 | CORE-14 | UNKNOWN | UNKNOWN | HUB-03 Up | ✗ | DECLARED_ONLY | HUB-03.md line 14 |
| 9 | HUB-04 | CORE-02 | COMPILE | UNKNOWN | HUB-04 Up | ✓ (composer) | **VERIFIED** | packages/hub/identity/composer.json line 15 |
| 10 | HUB-04 | CORE-03 | COMPILE | UNKNOWN | *(not in formal Upward — UNDECLARED)* | ✓ (composer) | **UNDECLARED_VERIFIED** | packages/hub/identity/composer.json line 21 *(mentioned in HUB-04 body Runtime line 30)* |
| 11 | HUB-04 | CORE-04 | COMPILE | UNKNOWN | *(not in formal Upward — UNDECLARED)* | ✓ (composer) | **UNDECLARED_VERIFIED** | packages/hub/identity/composer.json line 19 |
| 12 | HUB-04 | CORE-09 | COMPILE | OPTIONAL | HUB-04 Up *(soft)* | ✓ (composer) | **VERIFIED** | packages/hub/identity/composer.json line 22 |
| 13 | HUB-04 | CORE-10 | COMPILE | UNKNOWN | HUB-04 Up | ✓ (composer) | **VERIFIED** | packages/hub/identity/composer.json line 16 |
| 14 | HUB-04 | CORE-16 | COMPILE | UNKNOWN | HUB-04 Up | ✓ (composer + src) | **VERIFIED** | packages/hub/identity/composer.json line 17; src/Application/Service/IdentityApplicationService.php:4 |
| 15 | HUB-04 | CORE-18 | COMPILE | UNKNOWN | *(not in formal Upward — UNDECLARED)* | ✓ (composer + src) | **UNDECLARED_VERIFIED** | packages/hub/identity/composer.json line 20; src/Http/AuthMiddleware.php:8 |
| 16 | HUB-04 | CORE-19 | COMPILE | UNKNOWN | HUB-04 Up | ✓ (composer + src) | **VERIFIED** | packages/hub/identity/composer.json line 18; src/Infrastructure/Persistence/MySQLUserRepository.php:5 |
| 17 | HUB-05 | CORE-19 | UNKNOWN | UNKNOWN | HUB-05 Up | ✗ | DECLARED_ONLY | HUB-05.md line 34 |
| 18 | HUB-06 | CORE-02 | UNKNOWN | UNKNOWN | HUB-06 Up | ✗ | DECLARED_ONLY | HUB-06.md line 40 |
| 19 | HUB-06 | CORE-03 | UNKNOWN | UNKNOWN | HUB-06 Up | ✗ | DECLARED_ONLY | HUB-06.md line 40 |
| 20 | HUB-06 | CORE-09 | UNKNOWN | UNKNOWN | HUB-06 Up | ✗ | DECLARED_ONLY | HUB-06.md line 40 |
| 21 | HUB-06 | CORE-14 | UNKNOWN | UNKNOWN | HUB-06 Up | ✗ | DECLARED_ONLY | HUB-06.md line 40 |
| 22 | HUB-06 | CORE-19 | UNKNOWN | UNKNOWN | HUB-06 Up | ✗ | DECLARED_ONLY | HUB-06.md line 40 |
| 23 | HUB-07 | CORE-04 | UNKNOWN | UNKNOWN | HUB-07 Up | ✗ | DECLARED_ONLY | HUB-07.md line 46 |
| 24 | HUB-08 | CORE-04 | UNKNOWN | UNKNOWN | HUB-08 Up | ✗ | DECLARED_ONLY | HUB-08.md line 53 |
| 25 | HUB-08 | CORE-05 | UNKNOWN | UNKNOWN | HUB-08 Up | ✗ | DECLARED_ONLY | HUB-08.md line 53 |
| 26 | HUB-08 | CORE-06 | UNKNOWN | UNKNOWN | HUB-08 Up | ✗ | DECLARED_ONLY | HUB-08.md line 53 |
| 27 | HUB-08 | CORE-09 | UNKNOWN | UNKNOWN | HUB-08 Up | ✗ | DECLARED_ONLY | HUB-08.md line 53 |
| 28 | HUB-08 | CORE-10 | UNKNOWN | UNKNOWN | HUB-08 Up | ✗ | DECLARED_ONLY | HUB-08.md line 53 |
| 29 | HUB-08 | CORE-18 | UNKNOWN | UNKNOWN | HUB-08 Up | ✗ | DECLARED_ONLY | HUB-08.md line 53 |
| 30 | HUB-09 | CORE-03 | UNKNOWN | UNKNOWN | HUB-09 Up | ✗ | DECLARED_ONLY | HUB-09.md line 59 |
| 31 | HUB-11 | CORE-10 | UNKNOWN | UNKNOWN | HUB-11 Up | ✗ | DECLARED_ONLY | HUB-11.md line 66 |
| 32 | HUB-11 | CORE-14 | UNKNOWN | UNKNOWN | HUB-11 Up | ✗ | DECLARED_ONLY | HUB-11.md line 66 |
| 33 | HUB-12 | CORE-12 | UNKNOWN | UNKNOWN | HUB-12 Up | ✗ | DECLARED_ONLY | HUB-12.md line 72 |
| 34 | HUB-13 | CORE-10 | UNKNOWN | UNKNOWN | HUB-13 Up | ✗ | DECLARED_ONLY | HUB-13.md line 78 |
| 35 | HUB-14 | CORE-19 | UNKNOWN | UNKNOWN | HUB-14 Up | ✗ | DECLARED_ONLY | HUB-14.md line 83 |
| 36 | HUB-15 | CORE-02 | COMPILE | REQUIRED | HUB-15 Up | ✗ | DECLARED_ONLY | HUB-15.md line 89 |
| 37 | HUB-15 | CORE-10 | COMPILE | REQUIRED | HUB-15 Up | ✗ | DECLARED_ONLY | HUB-15.md line 89 |
| 38 | HUB-15 | CORE-19 | UNKNOWN | OPTIONAL | HUB-15 Up | ✗ | DECLARED_ONLY | HUB-15.md line 89 |
| 39 | HUB-16 | CORE-01 | UNKNOWN | UNKNOWN | HUB-16 Up | ✗ | DECLARED_ONLY | HUB-16.md line 95 |
| 40 | HUB-17 | CORE-03 | UNKNOWN | UNKNOWN | HUB-17 Transitive Core | ✗ | DECLARED_ONLY | HUB-17.md line 102 |
| 41 | HUB-17 | CORE-04 | UNKNOWN | UNKNOWN | HUB-17 Transitive Core | ✗ | DECLARED_ONLY | HUB-17.md line 102 |
| 42 | HUB-17 | CORE-06 | UNKNOWN | UNKNOWN | HUB-17 Transitive Core | ✗ | DECLARED_ONLY | HUB-17.md line 102 |
| 43 | HUB-17 | CORE-19 | UNKNOWN | UNKNOWN | HUB-17 Transitive Core | ✗ | DECLARED_ONLY | HUB-17.md line 102 |
| 44 | HUB-18 | CORE-14 | UNKNOWN | UNKNOWN | HUB-18 Transitive Core | ✗ | DECLARED_ONLY | HUB-18.md line 108 |
| 45 | HUB-18 | CORE-15 | UNKNOWN | UNKNOWN | HUB-18 Transitive Core | ✗ | DECLARED_ONLY | HUB-18.md line 108 |
| 46 | HUB-18 | CORE-19 | UNKNOWN | UNKNOWN | HUB-18 Transitive Core | ✗ | DECLARED_ONLY | HUB-18.md line 108 |
| 47 | HUB-19 | CORE-09 | UNKNOWN | OPTIONAL | HUB-19 Up | ✗ | DECLARED_ONLY | HUB-19.md line 112 |
| 48 | HUB-19 | CORE-19 | COMPILE | REQUIRED | HUB-19 Up *(compile-time)* | ✗ | DECLARED_ONLY | HUB-19.md line 112 |
| 49 | HUB-20 | CORE-02 | UNKNOWN | REQUIRED | HUB-20 Up *(hard)* | ✗ | DECLARED_ONLY | HUB-20.md line 118 |
| 50 | HUB-20 | CORE-16 | UNKNOWN | REQUIRED | HUB-20 Up *(hard)* | ✗ | DECLARED_ONLY | HUB-20.md line 118 |
| 51 | HUB-20 | CORE-19 | UNKNOWN | REQUIRED | HUB-20 Up *(hard)* | ✗ | DECLARED_ONLY | HUB-20.md line 118 |
| 52 | HUB-21 | CORE-02 | UNKNOWN | UNKNOWN | HUB-21 Transitive Core | ✗ | DECLARED_ONLY | HUB-21.md line 125 |
| 53 | HUB-21 | CORE-10 | UNKNOWN | UNKNOWN | HUB-21 Transitive Core | ✗ | DECLARED_ONLY | HUB-21.md line 125 |
| 54 | HUB-21 | CORE-19 | UNKNOWN | UNKNOWN | HUB-21 Transitive Core | ✗ | DECLARED_ONLY | HUB-21.md line 125 |
| 55 | HUB-22 | CORE-03 | UNKNOWN | UNKNOWN | HUB-22 Transitive Core | ✗ | DECLARED_ONLY | HUB-22.md line 132 |
| 56 | HUB-22 | CORE-19 | UNKNOWN | UNKNOWN | HUB-22 Transitive Core | ✗ | DECLARED_ONLY | HUB-22.md line 132 |
| 57 | HUB-23 | CORE-14 | UNKNOWN | UNKNOWN | HUB-23 Transitive Core | ✗ | DECLARED_ONLY | HUB-23.md line 139 |
| 58 | HUB-23 | CORE-19 | UNKNOWN | UNKNOWN | HUB-23 Transitive Core | ✗ | DECLARED_ONLY | HUB-23.md line 139 |
| 59 | HUB-24 | CORE-02 | UNKNOWN | UNKNOWN | HUB-24 Transitive Core | ✗ | DECLARED_ONLY | HUB-24.md line 144 |
| 60 | HUB-24 | CORE-04 | UNKNOWN | UNKNOWN | HUB-24 Transitive Core | ✗ | DECLARED_ONLY | HUB-24.md line 144 |
| 61 | HUB-24 | CORE-06 | UNKNOWN | UNKNOWN | HUB-24 Transitive Core | ✗ | DECLARED_ONLY | HUB-24.md line 144 |
| 62 | HUB-26 | CORE-11 | UNKNOWN | UNKNOWN | HUB-26 Transitive Core | ✗ | DECLARED_ONLY | HUB-26.md line 149 |
| 63 | HUB-26 | CORE-12 | UNKNOWN | UNKNOWN | HUB-26 Transitive Core | ✗ | DECLARED_ONLY | HUB-26.md line 149 |
| 64 | HUB-27 | CORE-04 | UNKNOWN | UNKNOWN | HUB-27 Transitive Core | ✗ | DECLARED_ONLY | HUB-27.md line 155 |
| 65 | HUB-27 | CORE-05 | UNKNOWN | UNKNOWN | HUB-27 Transitive Core | ✗ | DECLARED_ONLY | HUB-27.md line 155 |
| 66 | HUB-28 | CORE-06 | UNKNOWN | UNKNOWN | HUB-28 Transitive Core | ✗ | DECLARED_ONLY | HUB-28.md line 160 |
| 67 | HUB-28 | CORE-18 | UNKNOWN | UNKNOWN | HUB-28 Transitive Core | ✗ | DECLARED_ONLY | HUB-28.md line 160 |
| 68 | HUB-29 | CORE-08 | UNKNOWN | UNKNOWN | HUB-29 Transitive Core | ✗ | DECLARED_ONLY | HUB-29.md line 165 |
| 69 | HUB-29 | CORE-20 | UNKNOWN | UNKNOWN | HUB-29 Transitive Core | ✗ | DECLARED_ONLY | HUB-29.md line 165 |
| 70 | HUB-30 | CORE-13 | UNKNOWN | UNKNOWN | HUB-30 Transitive Core | ✗ | DECLARED_ONLY | HUB-30.md line 170 |
| 71 | HUB-30 | CORE-20 | UNKNOWN | UNKNOWN | HUB-30 Transitive Core | ✗ | DECLARED_ONLY | HUB-30.md line 170 |
| 72 | HUB-31 | CORE-02 | UNKNOWN | UNKNOWN | HUB-31 Transitive Core | ✗ | DECLARED_ONLY | HUB-31.md line 178 |
| 73 | HUB-31 | CORE-18 | UNKNOWN | UNKNOWN | HUB-31 Transitive Core | ✗ | DECLARED_ONLY | HUB-31.md line 178 |
| 74 | HUB-31 | CORE-19 | UNKNOWN | UNKNOWN | HUB-31 Transitive Core | ✗ | DECLARED_ONLY | HUB-31.md line 178 |

**Decomposition of Hub→Core edges:**
- 71 declared in some Upward/Transitive Core list
- 3 verified but UNDECLARED (HUB-04 → CORE-03, CORE-04, CORE-18 — see §7 Gap 7)
- 7 declared AND verified (VERIFIED) — 2 from HUB-01 (CORE-02, CORE-10), 5 from HUB-04 (CORE-02, CORE-09, CORE-10, CORE-16, CORE-19)
- 64 declared but NOT verified (DECLARED_ONLY) — the 27 unimplemented Hub packages plus 2 unverified-declared edges from HUB-01 (CORE-09, CORE-19)

---

## §6. Step 6 — Summary counts

```
Active Hub blueprints: 29
Implemented Hub packages: 2 (HUB-01 config + HUB-04 identity) — 6.9% implementation depth

Total declared edges: 158
  - Hub→Hub declared: 87
  - Hub→Core declared: 71

Total verified edges: 10
  - Hub→Hub verified: 0
  - Hub→Core verified: 10 (8 from HUB-04 identity + 2 from HUB-01 config)

VERIFIED (declared + verified): 7
  - All Hub→Core: 2 from HUB-01 (CORE-02, CORE-10) + 5 from HUB-04 (CORE-02, CORE-09, CORE-10, CORE-16, CORE-19)

DECLARED_ONLY (declared, not verified): 151
  - Hub→Hub: 87 (all DECLARED_ONLY)
  - Hub→Core: 64 (27 unimplemented Hubs' 64 edges + 2 from HUB-01: CORE-09, CORE-19)
  - Of which 11 have superseded targets (Hub→HUB-10 or Hub→HUB-25)

UNDECLARED_VERIFIED (verified, not declared): 3
  - All Hub→Core: HUB-04 → CORE-03 (composer.json core-event-dispatcher)
  - HUB-04 → CORE-04 (composer.json core-http-message)
  - HUB-04 → CORE-18 (composer.json core-kernel + source import of RequestContext)

INVALID: 0
  - (No edges declared to active targets that don't exist in active inventory)
  - (11 edges declared to superseded targets are DECLARED_ONLY, not INVALID, because they are not verified in code)

Total unique edges in inventory: 161
  - VERIFIED (7) + DECLARED_ONLY (151) + UNDECLARED_VERIFIED (3) + INVALID (0) = 161 ✓
```

---

## §7. Step 7 — Honest gaps

### Gap 1 — HUB-06 → HUB-11 labelled "Queue" — internal blueprint contradiction

`Architecture/Hub/HUB-06.md` line 40 declares in Upward: "`HUB-11` (Queue) — optional async write path for non-critical events (see Integration Strategy)."

Per `INDEX.md` §2.2 (canonical Hub map), **HUB-11 = Sovereign Cloud Storage** (NOT Queue). **HUB-10 = Sovereign Queue**.

The label "Queue" attached to HUB-11 is internally contradictory — either:
- (a) the target should be HUB-10 (Queue) — but HUB-10 was superseded (relocated to Runtime as RUNTIME-03 per ADR-021 §12), so the declared edge would point to a now-superseded Hub
- (b) the descriptor "Queue" is wrong and the target HUB-11 (Cloud Storage) is the actual intent — but this is semantically odd for "optional async write path for non-critical events" (which is a queue pattern, not cloud-storage)

**Recorded as:** Row 16 in §5.1 — `HUB-06 → HUB-11`, DECLARED_ONLY, evidence `HUB-06.md line 40`, with note about the label-vs-INDEX contradiction.

**Phase 2 recommendation:** Determine whether the actual intent was HUB-10 (Queue, now RUNTIME-03) or HUB-11 (Cloud Storage). If the former, the edge should be re-expressed as `HUB-06 → RUNTIME-03` (Hub → Runtime tier, INTEGRATION edge type) and removed from the Hub DAG. If the latter, the descriptor "Queue" should be corrected to "Cloud Storage" in the blueprint.

### Gap 2 — 11 declared edges point to superseded HUB-10 (8) and HUB-25 (3)

Across 8 blueprints, 11 edges are declared with HUB-10 or HUB-25 as targets (now relocated to Runtime per ADR-021 §12):
- **To HUB-10 (now RUNTIME-03):** HUB-09, HUB-12, HUB-14, HUB-17, HUB-18, HUB-23, HUB-30, HUB-31 (8 edges, see §5.1 rows 26, 31, 33, 45, 48, 65, 81, 85)
- **To HUB-25 (now RUNTIME-04):** HUB-20, HUB-23, HUB-31 (3 edges, see §5.1 rows 55, 68, 87)

**Recorded as:** DECLARED_ONLY with target-superseded flag in the Status column.

**Phase 2 recommendation:** These should be reinterpreted as Hub → Runtime tier INTEGRATION edges (`HUB-XX → RUNTIME-03` or `RUNTIME-04`), and removed from the Hub-tier DAG. Per ADR-021 §11, the Hub DAG only contains Hub-internal + Hub→Core edges. These are inter-tier edges and belong in either (a) the Runtime-tier DAG as incoming edges, or (b) a separate cross-tier integration inventory. ADR-021 doesn't explicitly resolve which DAG owns these — that's a Phase 2 governance decision.

### Gap 3 — HUB-15's "reverse Downward" list — semantic-direction inconsistency

`Architecture/Hub/HUB-15.md` line 90 contains within its **Downward** section:

> "Every Hub service (HUB-01, HUB-02, HUB-04, HUB-06, HUB-08, HUB-19, HUB-20) — owns its own `/health` endpoint per the contract in this blueprint; **HUB-15 polls it**."

The literal text says HUB-15 polls the other Hub services — this is a semantically **Upward** relationship (HUB-15 consumes them), but it is placed in the **Downward** section (which by INDEX §5.1 convention means "consumes this component"). This is an internal placement inconsistency in HUB-15's blueprint.

**Recorded as:** Six reverse-downward edges in §5.1 rows 34, 36, 37, 38, 39, 40 (`HUB-15 → HUB-01/HUB-04/HUB-06/HUB-08/HUB-19/HUB-20`), with evidence tagged as `HUB-15 reverse-Down`.

**Phase 2 recommendation:** Move these 6 entries from HUB-15's Downward section to its Upward section in the blueprint. Until then, treat them as DECLARED_ONLY with the placement inconsistency flagged.

### Gap 4 — Bidirectional cycle HUB-08 ↔ HUB-15 — ADR-004 acyclic rule violation

Two separate edges form a cycle:
- `HUB-08 → HUB-15` (§5.1 row 23) — declared in HUB-15 Downward: "HUB-08 (Gateway — future dynamic `ServiceRegistry` consults HUB-15 state, replacing the static config-loaded registry)"
- `HUB-15 → HUB-08` (§5.1 row 38) — declared in HUB-15 reverse-Downward: "Every Hub service ... HUB-08 ... HUB-15 polls it"

Together they violate ADR-004's acyclic-tier rule. Both directions are semantically meaningful (HUB-08 consults HUB-15's state at runtime; HUB-15 polls HUB-08's HTTP `/health` endpoint). They are different operations on different code paths.

**Phase 2 recommendation:** Per ADR-021 Amendment 2 §8.5 (Multigraph Semantics), resolve the cycle by giving each direction a different `edge_type`:
- `HUB-08 → HUB-15` as RUNTIME (Gateway's ServiceRegistry consults Health service at runtime — not a compile dep, just runtime data lookup)
- `HUB-15 → HUB-08` as RUNTIME (Health polls Gateway's `/health` HTTP endpoint — also runtime data flow)

Both being RUNTIME means neither is a COMPILE dep, so the build-order DAG remains acyclic. The runtime-call DAG may still have the cycle, but that's a documented property of the tier's operational behavior, not a build-order violation.

### Gap 5 — HUB-16 Downward is generic ("every other Hub component") — not enumerable

`Architecture/Hub/HUB-16.md` line 96 declares in Downward: "every other Hub component — this is the 'Merge Gate' for the tier per the original design intent."

This is too generic to enumerate as concrete edges. The blueprint doesn't specify which Hubs consume HUB-16.

**Recorded as:** Skipped from inventory — no concrete downward edges extracted from HUB-16.

**Phase 2 recommendation:** Author HUB-16's Downward section to enumerate the specific Hub consumers (likely all 28 other active Hubs, since HUB-16 is the "Merge Gate" — but this should be explicit in the blueprint, not inferred).

### Gap 6 — Bidirectional cycle HUB-21 ↔ HUB-01 — second ADR-004 acyclic rule violation

Two edges form a cycle:
- `HUB-21 → HUB-01` (§5.1 row 56) — declared in HUB-21 Direct Hub (Upward): "HUB-01" listed as a Direct Hub dependency. The Downward list of HUB-21 (line 126) also says "HUB-01" — meaning HUB-21 itself declares both directions.
- `HUB-01 → HUB-21` (§5.1 row 3) — declared in HUB-21 Downward: "HUB-01 (tenant config overrides reference this tenant-ID format)"

This cycle is more concerning than Gap 4 because both directions appear in the SAME blueprint (HUB-21), and both are placed in the formal Direct Hub / Downward lists (not in body prose).

**Phase 2 recommendation:** Resolve direction. The likely intent is: HUB-21 (Tenancy) consumes HUB-01 (Config) for tenant-ID format lookup — i.e., `HUB-21 → HUB-01` is the real edge, and `HUB-01 → HUB-21` (tenant config overrides referencing tenant-ID format) is actually an INVERTED relationship (HUB-01's tenant-overrides reference the tenant-ID format that HUB-21 defines — that's HUB-01 depending on HUB-21, but only at the data-schema level, not at the composer/call level). Per ADR-021 Amendment 2, classify `HUB-01 → HUB-21` as RUNTIME (data-schema reference, not compile dep) and `HUB-21 → HUB-01` as COMPILE (HUB-21's composer would require sovereign-stack/hub-config).

### Gap 7 — HUB-04 → CORE-03/CORE-04/CORE-18 — 3 UNDECLARED_VERIFIED edges

`packages/hub/identity/composer.json` requires 8 sovereign-stack/core-* packages. The blueprint's formal Upward list (`Architecture/Hub/HUB-04.md` line 28) declares only 5 of those 8:
- Declared (and verified): CORE-02, CORE-09, CORE-10, CORE-16, CORE-19 (5 edges VERIFIED)
- Declared (not verified): HUB-02, HUB-07, HUB-21 (3 edges DECLARED_ONLY — target packages don't exist yet)

Three additional Core packages appear in `composer.json require` but NOT in the formal Upward list:
- `sovereign-stack/core-event-dispatcher` → CORE-03 (UNDECLARED_VERIFIED; mentioned in HUB-04 body Runtime line 30 as the dispatch mechanism for events, but not in Upward list)
- `sovereign-stack/core-http-message` → CORE-04 (UNDECLARED_VERIFIED; HUB-04 uses PSR-7 interfaces that CORE-04 provides)
- `sovereign-stack/core-kernel` → CORE-18 (UNDECLARED_VERIFIED; HUB-04's `Http/AuthMiddleware.php` imports `SovereignStack\Core\Kernel\RequestContext`)

**Recorded as:** Rows 10, 11, 15 in §5.2 with status `UNDECLARED_VERIFIED`.

**Phase 2 recommendation:** Three options for each edge:
- (a) Add the missing target to HUB-04's formal Upward list in the blueprint (declaration reconciliation)
- (b) Remove the `sovereign-stack/core-*` entry from `composer.json require` (code reconciliation — but this would break compilation since source imports reference these packages)
- (c) Re-classify as RUNTIME edges (CORE-04 provides PSR-7 implementations HUB-04 uses at runtime; CORE-18 provides `RequestContext` HUB-04 uses at runtime; CORE-03 provides event-dispatch HUB-04 uses at runtime) — but this is questionable because composer `require` is a COMPILE-time artifact

Most likely (a) is correct: HUB-04's blueprint Upward list is incomplete and should be updated to include CORE-03, CORE-04, CORE-18.

### Gap 8 — 26 asymmetric downward-only declarations (blueprint drift)

26 of 87 Hub→Hub edges (30%) are declared in a producer's Downward list but NOT acknowledged in the consumer's Upward list. This is "blueprint drift" — producers claim consumers that consumers don't formally acknowledge.

Affected source Hubs (consumers whose Upward is incomplete):
- HUB-01 (missing 4 edges: → HUB-04, HUB-06, HUB-08, HUB-15 — all declared by HUB-01's own Downward as consumers of HUB-01... wait that's reversed)

Let me restate: 26 edges are where producer P declares "consumer C depends on me" but C doesn't list P in its Upward. The 26 are listed in §5.1 with `↓-only` in Evidence:
- Rows 2, 3, 4, 6, 9, 10, 14, 17, 19, 22, 23, 24, 27, 28, 30, 34, 36, 38, 39, 40, 46, 51, 54, 60, 61 — but several of these have multiple sources (e.g., row 34 HUB-15 → HUB-01 has both HUB-15 reverse-Down + HUB-01 Down, neither of which is HUB-15's Upward, so it's downward-only).

In particular, the 6 reverse-Down edges from HUB-15 (rows 34, 36, 37, 38, 39, 40) are downward-only because HUB-15's Upward list doesn't formally declare HUB-01/HUB-04/HUB-06/HUB-08/HUB-19/HUB-20 as dependencies (Gap 3).

**Phase 2 recommendation:** Reconcile each downward-only edge by either:
- (a) Adding the missing target to the consumer's Upward list (declaration reconciliation — most likely correct for the 6 HUB-15 reverse-Down edges and the 6 HUB-19 Downward edges for Hub-controllers)
- (b) Removing the producer's Downward claim (downward reconciliation — if the producer is incorrect about who consumes it)
- (c) Reclassifying the relationship as RUNTIME/CAPABILITY (if it's not a build-time COMPILE dep)

### Gap 9 — Edge type (COMPILE/RUNTIME/INTEGRATION) is underspecified in 25 of 29 blueprints

Only 4 blueprints explicitly use compile-time/optional/hard/soft labels in their formal Upward/Transitive Core lists:
- HUB-02: "Required at compile time: ... Optional: ..."
- HUB-15: "Required at compile time: ... Optional: ..."
- HUB-19: "Compile-time: ... Optional: ..."
- HUB-20: "hard" / "soft" labels

The other 25 blueprints just list IDs without classification. For Phase 2, edge_type must be assigned. The default heuristic should be:
- COMPILE for Hub→Core edges where the Hub's composer.json would require the Core package (the conventional case)
- COMPILE for Hub→Hub edges where the Hub's composer.json would require the Hub package
- RUNTIME for cases where only an interface is consumed via PSR contract (no direct composer require)
- INTEGRATION for external systems (MySQL, Redis, S3, SMTP — these are in Runtime lines of blueprints, not Upward lists, so out of scope for this inventory)

For this inventory I've recorded edge_type as UNKNOWN for most declared edges, and as COMPILE for verified edges (where composer.json confirms the compile-time dependency).

### Gap 10 — 2 of 29 active Hub blueprints implemented (6.9% implementation depth)

This is the dominant fact about the Hub tier: 27 of 29 active Hub blueprints have ZERO code on disk. Consequences:
- 87 of 87 Hub→Hub edges (100%) are DECLARED_ONLY
- 64 of 71 Hub→Core edges (90.1%) are DECLARED_ONLY
- 0 of 87 Hub→Hub edges (0%) are verified
- The VERIFIED DAG would be nearly empty: only HUB-01 and HUB-04 nodes with edges into Core
- A topological build order derived from VERIFIED edges alone would have very low wave depth (most Hubs unimplemented, so no verified ordering constraints among them)

### Gap 11 — Zero Hub→Hub verified edges — the Hub tier is currently contract-coupled independent packages

No Hub package's `composer.json require` lists another `sovereign-stack/hub-*` package. No Hub package's source code imports any `SovereignStack\Hub\<OtherPackage>\*` class. All `SovereignStack\Hub\*` imports found in source are intra-package (`SovereignStack\Hub\Identity\*` inside identity package, `SovereignStack\Hub\Config\*` inside config package).

This means the Hub tier today is a set of independent packages with no compile-time or source-level coupling. Every cross-Hub dependency is purely architectural intent in blueprint prose. Once the 27 unimplemented Hubs are built, the verified Hub→Hub edge count will grow — but as of today, the VERIFIED Hub DAG has 0 Hub-internal edges.

**Phase 2 implication:** The VERIFIED Hub DAG cannot be used for build-order decisions among Hub packages — there's nothing to order. The DECLARED Hub DAG (87 edges) IS the only available build-order input, and it has cycles (Gap 4, Gap 6) plus 11 edges to superseded targets (Gap 2) plus 26 asymmetric drift edges (Gap 8) — it requires substantial reconciliation before being usable for Kahn's algorithm.

---

## §8. Cross-checks performed

### §8.1 Active Hub count
- INDEX.md §2.2 lists HUB-01..31 (31 entries) ✓
- ADR-021 §12 relocates HUB-10 + HUB-25 → 29 active ✓
- Filesystem: 31 files in `Architecture/Hub/` (HUB-01 through HUB-31) ✓
- HUB-32 file does NOT exist (consistent with "ratified pending canonical publication") ✓

### §8.2 Implemented Hub package count
- `ls packages/hub/` → 2 directories (config, identity) ✓
- composer.json `description` fields explicitly map to HUB-01 and HUB-04 ✓
- No Hub implementations found in orchestrator/, packages/spoke/, or packages/bridge/ ✓

### §8.3 Verified edge count
- `rg "sovereign-stack/hub-" packages/hub/*/composer.json` → 0 require entries (only `name` matches) ✓
- `rg "^use SovereignStack\\\\Core" packages/hub/{identity,config}/src/` → 4 source imports (CORE-10, CORE-16, CORE-18, CORE-19) ✓
- `rg "^use SovereignStack\\\\Hub\\\\" packages/hub/{identity,config}/src/` → all intra-package imports ✓
- Verified Hub→Hub edges = 0 ✓
- Verified Hub→Core edges = 10 (2 from HUB-01 + 8 from HUB-04) ✓

### §8.4 Declared edge count
- 87 Hub→Hub unique edges (61 Upward-declared + 26 Downward-only) ✓
- 71 Hub→Core declared edges (sum of all Upward/Transitive Core lists) ✓
- 3 UNDECLARED_VERIFIED Hub→Core edges (HUB-04 → CORE-03/CORE-04/CORE-18) ✓
- Math: 7 VERIFIED + 151 DECLARED_ONLY + 3 UNDECLARED_VERIFIED = 161 unique ✓
- Math: 87 + 74 = 161 unique ✓
- Math: 158 declared + 3 undeclared-verified − 7 (overlap) = 154? No — 158 declared total − 7 (verified subset) = 151 declared-only; 10 verified − 7 (also declared) = 3 undeclared-verified; 7 + 151 + 3 + 0 = 161 ✓

---

## §9. Deliverable handoff to Phase 2

This inventory is the **evidence base** for the Phase 2 deliverables `Architecture/Hub/HUB-VERIFIED-DAG.md` and `Architecture/Hub/HUB-DECLARED-DAG.md` (analogous to the Core DAG files committed in PR #283 + Amendment PR #288).

**Recommended Phase 2 inputs from this inventory:**
1. **DECLARED DAG** input: all 158 declared edges (87 Hub→Hub + 71 Hub→Core) — but Phase 2 must first resolve Gap 2 (11 edges to superseded targets — reclassify as INTEGRATION edges to Runtime tier or drop), Gap 4 (HUB-08 ↔ HUB-15 cycle — split into RUNTIME edges), Gap 6 (HUB-21 ↔ HUB-01 cycle — split into RUNTIME/COMPILE edges), Gap 8 (26 asymmetric drift edges — reconcile direction).
2. **VERIFIED DAG** input: the 10 verified edges from HUB-01 + HUB-04 (all Hub→Core; 0 Hub→Hub). This DAG will be tiny — only 2 Hub nodes with edges into Core.
3. **Cross-DAG reconciliation table**: VERIFIED (7 edges) vs DECLARED_ONLY (151) vs UNDECLARED_VERIFIED (3) — see §5 master table.
4. **Cycle resolution proposals** (Gaps 4, 6) for tech-lead decision before DAG derivation.
5. **Target-superseded proposal** (Gap 2) for tech-lead decision: relocate 11 edges to Runtime-tier DAG.
6. **Reverse-Downward placement fix** (Gap 3) for HUB-15 blueprint update — Phase 2 prerequisite.
7. **HUB-04 Upward list completion** (Gap 7) — add CORE-03, CORE-04, CORE-18 to the formal Upward list in HUB-04.md.

**No DAG files were created by this task.** Phase 2 starts after tech-lead review of this inventory.

