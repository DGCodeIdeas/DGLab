# DGLab / Sovereign Stack — Master Blueprint Index (v2)

**Status:** Canonical. Supersedes all documents listed in "Archived Predecessors" below.
**Applies to:** the polyrepo, tier-isolated architecture (Core → Hub → Bridge → Spokes).
**Resolves:** Findings 1–13 in `00_CRITIQUE.md`.

---

## 1. Single-source-of-truth declaration

This index formally designates **one** architecture as canonical, ending the two-architecture
ambiguity in Finding 1.

**Canonical (this document tree + the following existing paths):**
- `docs/blueprints/Core/CORE-01..20`
- `docs/blueprints/Hub/HUB-01..30`
- `docs/blueprints/Spoke/Internal/ISPOKE-01..25` (see §4 for the corrected count)
- `docs/blueprints/Spoke/External/ESPOKE-01..15`
- `docs/blueprints/Spoke/Bridge/BRIDGE-01`
- `docs/blueprints/Deploy/*` (expanded — see §6)
- `orchestrator/` (CORE-01 reference implementation)
- `packages/core/*` (CORE-tier reference implementations)

**Archived (Vision A — the CMS-Studio monolith rebuild). Kept for historical reference only, moved
under `docs/archive/vision-a-monolith/`, and prefixed with a banner pointing here:**
- `docs/architecture/origin/HUB_AND_SPOKE.md`
- `docs/architecture/origin/STRATEGIC_OVERVIEW.md`
- `docs/architecture/origin/DETAILED_SYSTEM_ANALYSIS.md`
- `docs/architecture/origin/ComponentBlueprints/**`
- `docs/architecture/origin/PhasedBlueprints/**`
- `docs/architecture/origin/Strategic/**`
- `docs/architecture/origin/Sovereign_Stack_Blueprint/**` and its byte-identical
  `Mobile_Optimized/` twin (delete the twin outright — see §7)
- `Legacy/**` (the actual code for Vision A; already named `Legacy`, which is correct — just needs the
  docs pointing at it archived alongside it instead of living next to the active docs)

**Rule going forward:** nothing may be added under `docs/architecture/origin/` without a header
banner stating which vision it belongs to. In practice, new work should not be added there at all —
new canonical content goes under `docs/blueprints/`.

---

## 2. Corrected Core-tier map (resolves Finding 2)

The table below is the **authoritative** CORE-01..20 mapping. Anything in
`docs/evaluation/BLUEPRINT_RANKINGS.md` or `EVALUATION_SUMMARY.md` that contradicts this table is
stale and is superseded by this document. (Those two files should be deleted or explicitly marked
`ARCHIVED — describes a pre-renumbering Core tier` rather than edited in place, so there is no
ambiguity about which version is authoritative going forward.)

| ID | Component | Real implementation | Build status |
|----|-----------|---------------------|---------------|
| CORE-01 | Polyrepo Orchestrator ("Loom") | `orchestrator/` | ✅ Implemented + tested |
| CORE-02 | Dependency Injection Container | `packages/core/container/` | ❌ **Stub only — `.gitkeep`, blocking** |
| CORE-03 | PSR-14 Event Dispatcher | `packages/core/event-dispatcher/` | ✅ Implemented + tested |
| CORE-04 | PSR-7 HTTP Message & Factory | — | 📝 Not started |
| CORE-05 | PSR-15 Middleware & Request Handler | — | 📝 Not started |
| CORE-06 | Attribute-Based Router | — | 📝 Not started |
| CORE-07 | SuperPHP Lexer | — | 📝 Not started |
| CORE-08 | Global Error & Exception Handler | — | 📝 Not started |
| CORE-09 | PSR-3 Logging Service | — | 📝 Not started |
| CORE-10 | Configuration & Environment Loader | — | 📝 Not started |
| CORE-11 | SuperPHP Parser | — | 📝 Not started |
| CORE-12 | SuperPHP Compiler | — | 📝 Not started |
| CORE-13 | CLI Engine (Console) | — | 📝 Not started |
| CORE-14 | Filesystem Abstraction | — | 📝 Not started |
| CORE-15 | Cache Abstraction (PSR-6/16) | — | 📝 Not started |
| CORE-16 | Binary Encryption Envelope | — | 📝 Not started |
| CORE-17 | Service Provider System | — | 📝 Not started |
| CORE-18 | Core Kernel & Lifecycle | — | 📝 Not started |
| CORE-19 | Database Abstraction Layer | — | 📝 Not started |
| CORE-20 | Developer CLI Toolchain ("Sovereign Forge") | — | 📝 Not started |

**Critical-path correction:** the true build-blocking dependency is **CORE-02**, not the kernel
(CORE-18) and not the orchestrator (CORE-01, already done). Every Hub blueprint that lists CORE-02 as
a dependency (nearly all of them, per `hub-taxonomy/hub-blueprint-taxonomy.md`) is currently blocked.
§5 of this index makes closing CORE-02 the top priority, ahead of continuing Hub-tier documentation.

---

## 3. Cross-reference corrections (resolves Finding 3, Finding 15)

What began as a single fix (`BRIDGE-01`'s `CORE-09`) turned out to be eight recurring, independently-
introduced mislabeling patterns once the full Spoke tiers were read. This table is now the single
correction reference for all of them — every `02_EXEMPLARS/*SPOKE-*.md` file applies it.

| Pattern | Wrong reference | Corrected reference | Files affected |
|---|---|---|---|
| A | `CORE-09: Cryptography & Hashing` | `CORE-16: Binary Encryption Envelope` | `BRIDGE-01`, `ISPOKE-05`,`06`,`10`,`13`,`15`, `ESPOKE-02`,`03`,`07`,`08`,`09`,`10`,`13`,`14` |
| B | `HUB-28: Distributed Ledger & Analytics Engine` | Split three ways — see §4 | `ISPOKE-05`,`10`,`12`,`13`,`15`, `ESPOKE-05` |
| C | Queue attributed to `HUB-11` or `HUB-14` | `HUB-10: Queue & Job Dispatcher` | `SOLUTIONS_TO_WEAKNESSES.md`, `ISPOKE-07`,`09`, `ESPOKE-06`,`13` |
| D | `HUB-12` used for Event Bus/pub-sub | `HUB-09: Event Bus / Message Broker` | `ISPOKE-07`,`08`,`15`, `ESPOKE-06` |
| E | `HUB-13`/`HUB-14` swapped for Search/Media | `HUB-14`=Search, Media is `HUB-18` | `ISPOKE-09`,`14`, `ESPOKE-04` |
| F | `CORE-07` used for Event Dispatcher or HTTP Message | `CORE-07` is SuperPHP Lexer; Event Dispatcher is `CORE-03`, HTTP Message is `CORE-04` | `ESPOKE-02`,`13`,`15` |
| G | Reference to a Core "phase" that was never real | No mapping exists — dropped, not redirected | `ESPOKE-02` (`CORE-08` as HTTP Client), `ESPOKE-15` (`CORE-15` as Process Management) |
| H | Wrong Internal Spoke ID referenced from External Spokes | `ISPOKE-05`=Insight (Analytics), `ISPOKE-13`=Ledger (Billing), `ISPOKE-14`=Nexus (Tenancy) | `ESPOKE-09`,`10` (said `ISPOKE-05`="Ledger"), `ESPOKE-14` (said `ISPOKE-14`="Insight") |

Any future audit of this kind should be re-run whenever a Core/Hub/Spoke-tier ID is reassigned — a
one-line CI check (`grep -R "CORE-[0-9]\+\|HUB-[0-9]\+\|.SPOKE-[0-9]\+" docs/blueprints |
validate-against(§2/§4 tables)`) is specified in §8 to make this class of bug impossible to
reintroduce silently.

---

## 4. Corrected tier inventory (resolves Finding 13, Finding 14)

| Tier | Documented | Placeholder-only | **True total** |
|------|-----------|-------------------|-----------------|
| Core | 20 | 0 | 20 |
| Hub | 30 | **1 (`HUB-31`, new — see below)** | **31** |
| Internal Spoke | 15 | 10 (`ISPOKE-16`–`25`) | **25** |
| External Spoke | 15 | 0 | 15 |
| Bridge | 1 | 0 | 1 |
| Deploy | 1 | expand — see §6 | see §6 |

**`HUB-31` (new, provisional): Real-Time Analytics & Metrics Ledger.** Registered per
`00_CRITIQUE.md` Finding 14. Only **three** of the five original `HUB-28` references actually need
this: `ISPOKE-05` (BI dashboards), `ISPOKE-12` (live experiment-rollout impact monitoring), and
`ISPOKE-13` (real-time MRR/Churn/LTV). The other two are different situations, not more instances of
the same gap — `ISPOKE-10`'s reference is a mislabeled pointer to the already-real `HUB-23` (Reporter;
its actual need is async PDF/CSV export, which `HUB-23` already covers), and `ISPOKE-15`'s reference is
unused in its own design section and is dropped rather than mapped anywhere pending an actual decision.
`HUB-23` (Reporter) remains scoped to async batch export and is *not* redirected to cover `HUB-31`'s
real-time scope — the two are legitimately different jobs.

All roadmap week-counts and "32-week total timeline" claims in `BLUEPRINT_RANKINGS.md` assumed 15
Internal Spokes; with the corrected count of 25, Phase 3 (Internal Spokes) should be re-estimated at
roughly 1.6× its previous duration, or explicitly scoped to ship the 15 documented spokes first and
treat `ISPOKE-16`–`25` as a distinct Phase 3b. The Hub-tier estimate should add one phase for `HUB-31`.

---

## 5. Revised implementation sequence

Unlike the stale sequence in `BLUEPRINT_RANKINGS.md` (built on the old CORE numbering), this sequence
is derived from the **current** dependency table and from what is *actually* built vs. stubbed:

1. **Close CORE-02** (DI Container) — every Hub blueprint depends on it; it is the one truly blocking
   gap in the entire system today. This is the single highest-leverage next PR.
2. **CORE-10, CORE-09, CORE-08** (Config, Logging, Error Handling) — no interdependencies among these
   three beyond CORE-02; can be parallelized once CORE-02 lands.
3. **CORE-18** (Kernel) — depends on Config + Error Handler + Container being real, not stubs.
4. **CORE-04/05/06** (HTTP Message → Middleware → Router) — the request pipeline, in that literal
   order (Router depends on Middleware depends on Message).
5. **CORE-19, CORE-15, CORE-14, CORE-16** (DBAL, Cache, Filesystem, Encryption) — the data/IO layer.
6. **CORE-07/11/12** (SuperPHP Lexer → Parser → Compiler) — only needed once a Spoke that renders UI
   is scheduled; can slip behind Hub-tier work if the first Spokes are API-only.
7. **CORE-13/17/20** (CLI, Service Providers, Dev Toolchain) — developer-experience layer; valuable
   early but not blocking.
8. **Hub tier**, in the order already given in `docs/hub-taxonomy/hub-dependency-graph.md` (that
   document's *internal* Hub-to-Hub and Hub-to-Core sequencing was checked in this review and is
   internally consistent — the only defect found in the Hub tier was the downstream Bridge citation
   in Finding 3, now fixed).
9. **BRIDGE-01**, before any External Spoke.
10. **Internal Spokes 01–15**, then **16–25** as Phase 3b (§4).
11. **External Spokes**.

---

## 6. Deploy tier — expanded scope (resolves Finding 9)

`DEPLOY-01` is renamed **`DEPLOY-00: Documentation Site`** and kept as-is (it correctly, if modestly,
describes hosting the Markdown docs on Render's free tier — nothing wrong with that blueprint on its
own terms, it was simply mislabeled as covering more than it does).

New blueprints added to close the actual gap:

- **`DEPLOY-01: Core & Hub Service Deployment`** — containerized deployment of the Core/Hub tier
  (PHP-FPM + Nginx, one image per Hub service, health checks wired to `HUB-15`).
- **`DEPLOY-02: Datastore Provisioning`** — MySQL/Postgres + Redis + queue broker provisioning,
  connection-secret management, backup/restore runbook.
- **`DEPLOY-03: Bridge & External Spoke Deployment`** — the public-facing tier, CDN/edge caching
  integration with `HUB-02`, and the network-isolation rules the Bridge's "Zero-Exposure Test"
  (see `BRIDGE-01.md`) needs to actually be enforceable in a real environment (network policy, not
  just a namespace-import scan).
- **`DEPLOY-04: Multi-Environment & Promotion Pipeline`** — how a change moves dev → staging →
  production across ~50+ independently deployable repos, tying into `orchestrator/` (CORE-01) for
  version-bump and release-gate automation.

Full specs for `DEPLOY-01`–`DEPLOY-04` are out of scope for this delivery's exemplar set but are
sequenced in the roadmap; `02_EXEMPLARS/` in this package includes a fleshed-out `DEPLOY-01` as a
demonstration of the expected fidelity.

---

## 7. Immediate cleanup actions

1. Delete `docs/architecture/origin/Mobile_Optimized/SOVEREIGN_STACK_MASTER.md` (Finding 5) — it is
   byte-identical to `Sovereign_Stack_Blueprint/SOVEREIGN_STACK_MASTER.md` and adds nothing.
2. Fix the literal `$(date)` placeholder in `PhasedBlueprints/ANALYSIS_REPORT.md`, or mark the whole
   file archived per §1 (recommended, since it belongs to Vision A anyway).
3. Reconcile the Nexus and AdminPanel completion-status contradictions (Finding 6) — pick one status
   per component and delete the other claim; both live in files being archived per §1, so this can be
   done as part of the archival pass rather than a separate fix.
4. Correct the arithmetic in `PhasedBlueprints/README.md` (Finding 7) — again, archived under §1, but
   the corrected category table should be preserved in the archive banner for historical accuracy.
5. Replace the single-sentence `disapproved/` rejection note (Finding 12) with a per-file rationale —
   at minimum, a one-line diff summary ("varies from template by including implementation code; kept
   for reference, not because the code was wrong") for each of the 72 files, so the corpus can actually
   serve as the "learning asset" it's claimed to be.
6. Merge `docs/evaluation/SOLUTIONS_TO_WEAKNESSES.md` line-by-line into the specific blueprint files it
   discusses (Finding 11), then delete or archive the standalone solutions doc — a proposed fix that
   isn't merged into the artifact it fixes has no effect.

---

## 8. Governance rules for this blueprint set going forward

These close the *mechanism* that produced Findings 1–13, not just the current instances of them:

1. **One numbering authority.** This file (§2, §4) is the only place a Core/Hub/Spoke ID's identity is
   defined. Any blueprint that changes a component's ID or scope must update this table in the same
   commit. A blueprint and this index disagreeing is treated as a broken build.
2. **No performance target without a method.** Every "CI Verification Criteria" section must name: the
   hardware/runtime baseline, the benchmark tool, and the load model — or state "target is provisional,
   unverified" explicitly. Bare millisecond claims (Finding 10) are no longer permitted as-is.
3. **Evaluation docs are dated snapshots, not living scores.** Any `docs/evaluation/*` file must carry
   a "valid as of commit `<sha>`" header. A quality score with no commit anchor cannot be trusted to
   describe current content (this is exactly how Finding 2 happened).
4. **Rejections need a real reason.** A `disapproved/` entry must state, specifically, what about it
   was rejected — "deviates from template" is not sufficient on its own if the alternative was also
   compared on technical merit (Finding 4).
5. **Solutions must land in the artifact, not beside it.** A weakness write-up is not resolved until
   the referenced blueprint file is edited. `docs/evaluation/SOLUTIONS_TO_WEAKNESSES.md`-style
   documents should be issue trackers, not permanent parallel content.
6. **Duplicate directories require a diff check before merge.** Nothing named `*_Optimized`,
   `*_v2`, or similar should be committed without CI verifying it is not byte-identical to its source
   (Finding 5).

---

## 9. What's in this delivery

```
DGLab-Blueprints-v2/
├── 00_CRITIQUE.md              # This review's findings (read first)
├── 01_MASTER_INDEX.md          # This file — governance + corrected maps + §10 ID policy
└── 02_EXEMPLARS/
    ├── CORE-01.md               # Polyrepo Orchestrator (matches orchestrator/ code)
    ├── CORE-02.md               # DI Container (the actual blocking gap; full contracts)
    ├── HUB-01.md .. HUB-30.md   # Full Hub tier — complete
    ├── BRIDGE-01.md             # The Vanguard
    ├── ISPOKE-01.md .. ISPOKE-15.md  # Full documented Internal Spoke tier — complete
    ├── ESPOKE-01.md .. ESPOKE-15.md  # Full External Spoke tier — complete
    └── DEPLOY-01.md             # Core & Hub Service Deployment (closes Finding 9)
```

66 files total. This closes out every blueprint that had real content to correct in the original repo
— the only things left are genuinely new work: 10 placeholder-only Internal Spokes (`ISPOKE-16`–`25`,
Finding 13) and `DEPLOY-02`–`04` (Finding 9's remaining scope).

The External Spoke pass added three more patterns (F, G, H) to the five found in Internal Spokes,
bringing Pattern A (`CORE-09` mislabeled as crypto) to **14 files** — closer to a systemic default
assumption than an error. The most structurally significant addition was **Finding 15**: the Internal
Spoke tier itself drifted identity (`ISPOKE-05`/`13`/`14`) the same way the Core tier did, and three
separate External Spoke files each guessed a different wrong mapping — between `ESPOKE-09`/`10` and
`ESPOKE-14`, every incorrect pairing of `{05,13,14}` × `{Insight,Ledger,Nexus}` appeared somewhere
except the right one. `ESPOKE-11` also surfaced an honest gap rather than a mislabel: its internal
ticketing counterpart doesn't map cleanly to any of the 15 documented or 10 placeholder Internal
Spokes — flagged as unspecified (like `HUB-31`) rather than forced onto a number that happened to be
free.

Next: `ISPOKE-16`–`25` and `DEPLOY-02`–`04`.

## 10. Stack-wide entity ID policy

Added after a self-review of this delivery's own exemplars found the same class of drift the critique
documents elsewhere: `HUB-06.md`'s audit-record schema originally typed `user_id`/`tenant_id` as `int`
and its own `id` as `uuid`, while `HUB-01.md` and `HUB-21.md` typed `tenant_id` as a 26-character ULID.
Three different identifier schemes for what should be one concept, inside a delivery whose entire
purpose is fixing exactly this. Both have been corrected; this section is now the single place the
policy is stated so it doesn't drift again:

**Every entity primary/foreign-key identifier in the Sovereign Stack — tenant, user, audit record,
tenant-config override row, or otherwise — is a ULID** (26-character, Crockford Base32,
lexicographically sortable), not a database auto-increment integer and not a UUID. ULID is chosen over
UUID specifically because its sortability keeps index locality reasonable for high-write tables like
`HUB-06`'s audit log. The one documented exception: surrogate/internal-only primary keys with no
external reference or cross-service meaning (e.g., `HUB-01.md`'s `hub_config_overrides.id`, which is
never referenced outside that single table) may remain `BIGINT AUTO_INCREMENT` — the distinction is
whether an ID is ever passed between services or exposed to a client (ULID) versus purely local to one
table's own row identity (integer is fine).

Any blueprint introducing a new entity type must state which of the two categories its primary key
falls into, rather than picking a type ad hoc. This is a direct application of Governance Rule 1 (one
numbering/identity authority) extended to data types, not just component IDs.
