# PROMPTS.md — AI Agent Orchestration for DGLab / Sovereign Stack

**Project:** DGLab (Sovereign Stack)  
**Team:** 1 Tech Lead (solo, AI-augmented), 1 Marketer, 1 Media  
**Methodology:** AGRD v3.4 Spiral Deepening  
**Canonical Repo:** `https://github.com/DGCodeIdeas/DGLab`  
**Last Updated:** 2026-08-10  
**Status:** Canonical — ratified alongside AGRD v3.4

---

## 1. Purpose

This document governs how every AI agent — any tool, any vendor, any environment — interacts with the DGLab codebase and architecture. Its goal is **consistency**: every agent must produce output that is compatible with every other agent's output, the canonical blueprints, and the live repo state.

**The single rule:** An agent is a tool, not an architect. It implements, critiques, or verifies against canonical documents. It does not invent architecture, rename components, or decide Open Decisions.

---

## 2. Agent Capability Taxonomy (Vendor-Agnostic)

Do not use brand names (Cline, Copilot, Jules, Claude, Kimi, Roo, Kilo, Zoo, Cursor, Windsurf, etc.). Use **capability roles** instead. Any tool that matches the capability profile slots into that role, regardless of vendor.

| Capability Role | Environment | What It Does | What It Needs | What It Must NOT Do |
|-----------------|-------------|--------------|---------------|---------------------|
| **Local Implementer** | Local IDE (VSCode, Cursor, etc.) | Writes code, tests, refactors against frozen blueprints | Live codebase access; canonical blueprint files | Architecture decisions; renaming; resolving ODs |
| **Local Completer** | Local IDE (inline, autocomplete) | Fills boilerplate, repetitive structure, docstrings | Open file context; known patterns from repo | Anything requiring architectural context beyond the open file |
| **Cloud Reviewer** | Cloud-integrated (PR hooks, CI) | Pre-merge verification; cross-reference checking | PR diff; canonical docs; lint output | Write new blueprints; decide ADRs |
| **Cloud Architect** | Cloud chat (web, API) | Architecture critique, gap analysis, complex synthesis | Full canonical docs; long context window | Day-to-day implementation (too slow, too expensive) |
| **Cloud Analyst** | Cloud (repo-connected or large-context) | Repo state checks; cross-cutting docs; threat modeling | Repo access or large doc uploads | Write production code (environment mismatch) |
| **Cloud Peer** | Cloud (any) | Alternative perspective; edge-case hunting; gap review | Draft documents; prior critique history | Primary authorship (context limits; no live code) |
| **Content Producer** | Any (marketing/media tools) | Copy, assets, translation keys, wireframes | Frozen contracts from Cooldown 0 | Deviate from frozen contracts; hardcode strings |

**Critical distinction:** Local roles have the live codebase. Cloud roles have broader context but stale file state. **Never ask a Cloud role to verify something it cannot see.**

**If your tool doesn't fit a role cleanly:** Pick the closest role and add its specific constraints in the session prompt. Do not invent a new role without updating this document.

---

## 3. Canonical Source Hierarchy

When any agent is uncertain, it resolves ambiguity by checking sources in this order. **Lower numbers override higher numbers.**

```
1. Architecture/INDEX.md              — Single numbering authority, component IDs, dependency graph
2. Architecture/OPEN-DECISIONS.md     — Open Decisions (ODs) — these are OPEN, not resolved
3. Architecture/ADRs/ADR-XXX.md       — Accepted Architecture Decision Records
4. Architecture/CrossCutting/         — STRUCTURE-01-Wheel.md, SDLC-AGRD-v3.4.md, PROMPTS.md, etc.
5. Architecture/{Core,Hub,Spoke}/     — Individual blueprint .md files
6. Verification/lint/run.php          — Automated cross-reference and structural checks
7. packages/*/                        — Live implementation code
8. docs/                              — Non-canonical documentation (may be stale)
9. Analysis_Critiques_Rewrites/       — Working archive (may contain deprecated versions)
```

**Rule:** If an agent finds a contradiction between #1 and anything below #1, it reports the contradiction and defaults to #1. It does not "resolve" the contradiction by picking the source it prefers.

---

## 4. Prompt Templates by Task

### 4.1 Blueprint Implementation (Local Implementer)

**Context:** Implementing a blueprint that is already documented and interface-frozen.

```
You are implementing [BLUEPRINT-ID] ([Component Name]) for the DGLab Sovereign Stack.

CANONICAL SPEC:
- Blueprint: Architecture/[Tier]/[BLUEPRINT-ID].md
- Structure doc: Architecture/CrossCutting/STRUCTURE-01-Wheel.md
- SDLC: Architecture/CrossCutting/SDLC-AGRD-v3.4.md

CONSTRAINTS:
- Do NOT rename any component, interface, or method.
- Do NOT change the dependency list in the blueprint's "Dependency Status" section.
- Do NOT resolve any Open Decision (OD-XX) — these are tracked in Architecture/OPEN-DECISIONS.md.
- Do NOT assume PostgreSQL — ADR-013 makes MySQL 8 (InnoDB) the primary datastore.
- ULID (CHAR(26)) is the canonical PK format for anything cross-service.
- The Wheel model: this component lives at [RING] depth [DEPTH]. It may only call inward (Core-ward) or laterally (same-ring). No outward calls except via events (HUB-09).

OUTPUT:
- PHP code matching the blueprint's "Architectural Design" section.
- PHPUnit tests matching the "CI Verification Criteria" section.
- If the blueprint's spec is ambiguous, STOP and quote the ambiguous sentence. Do not guess.
```

### 4.2 Code Review / Pre-Merge Verification (Cloud Reviewer)

**Context:** A PR is ready for review. The agent checks it against canonical docs.

```
Review this PR against the DGLab canonical architecture.

CHECKLIST (fail = block merge):
[ ] Every modified file's blueprint ID is valid per Architecture/INDEX.md
[ ] No component references a non-existent blueprint ID (check against INDEX.md §2)
[ ] No mislabel patterns from INDEX.md §3 (e.g., CORE-09 called "crypto", HUB-28 called "Metrics")
[ ] If a frozen interface (§2.1 SDLC-AGRD-v3.4) is touched, an ADR is cited in the PR description
[ ] MySQL 8 DDL is used (ADR-013); no JSONB, no PostgreSQL-specific features
[ ] ULID used for cross-service PKs; integer only for purely-local surrogate keys
[ ] No new dependencies on HUB-31 (still Proposed, ADR-011)
[ ] run.php passes (cross-reference validity, naming drift, structural completeness)

OUTPUT:
- Pass / Fail for each check.
- If Fail: specific file, line, and canonical source that was violated.
- If ambiguous (blueprint unclear): flag as "needs human review," do not assume.
```

### 4.3 Architecture Critique / Gap Analysis (Cloud Architect)

**Context:** Pressure-testing a structure doc or blueprint before Ring Lock.

```
You are reviewing [DOCUMENT] for the DGLab Sovereign Stack before its Ring Lock.

METHOD:
- Treat the document as a falsifiable model, not a description.
- For every claim, ask: "What would prove this wrong?" and "Does the repo contain evidence?"
- Check against canonical sources per §3 hierarchy.
- Look for internal contradictions (same number, different meaning in different sections).
- Look for unstated assumptions (team size, timeline, dependency availability).

FORBIDDEN:
- Do not invent new components or rename existing ones.
- Do not resolve Open Decisions — flag them if they block the document's claims.
- Do not assume 3 engineers unless the document explicitly states it.
- Do not assume PostgreSQL (ADR-013 = MySQL 8).

OUTPUT FORMAT:
1. What the document gets right (cite specific sections).
2. Real gaps (spec-level: internal contradictions, unstated assumptions, unit mismatches).
3. Edge-case risks (file as ODs, don't block).
4. Smaller observations (not gaps, not blocking).
5. Verdict: "Push," "Hold for fixes," or "File as OD and push."
```

### 4.4 Repo State Check (Cloud Analyst)

**Context:** Checking what's changed in the repo since last sync.

```
Check the DGLab repo (https://github.com/DGCodeIdeas/DGLab) for new commits since [LAST_COMMIT_SHA].

REPORT:
- New commits: SHA, message, author, date.
- Files changed per commit (top-level directories only).
- Any changes to: Architecture/INDEX.md, Architecture/OPEN-DECISIONS.md, Architecture/ADRs/, Verification/lint/run.php.
- Any new blueprints or deprecated ones.
- Any naming collisions or mislabels introduced.

DO NOT:
- Do not summarize code changes you cannot see (diffs may be too large).
- Do not assume a commit is "just cleanup" — check if it touches canonical docs.
- Do not report on non-canonical directories (docs/, Analysis_Critiques_Rewrites/) unless they affect canonical state.
```

### 4.5 Content Track Task (Content Producer)

**Context:** Marketer or media needs to produce assets against frozen contracts.

```
You are producing [CONTENT_TYPE] for the DGLab Sovereign Stack content track.

FROZEN CONTRACTS (from Cooldown 0, do not deviate):
- UI wireframe: [REFERENCE_TO_ESPOKE-05_WIREFRAME]
- Theme token contract: Architecture/Hub/HUB-26.md §ThemeInterface
- String key taxonomy: Architecture/Hub/HUB-13.md §StringKeyTaxonomy

CONSTRAINTS:
- All copy resolves through HUB-13 translation keys — no hardcoded strings.
- Visual assets match HUB-26 token slots — no custom dimensions or colors.
- Tone per theme: Internal Spoke = staff, technical, terse; External Spoke = public, warmer, plainer.
- Content Ring Lock occurs every cooldown — assets must pass review against frozen contracts.

OUTPUT:
- [CONTENT] formatted per the frozen contract.
- List of any contract ambiguities found (do not guess — flag for tech lead).
```

### 4.6 Debugging / Incident Response (Local Implementer)

**Context:** Something is broken in the local dev environment.

```
Debug this issue in the DGLab local environment.

CONTEXT:
- OS: [Xubuntu / macOS / Windows]
- Anvil status: [running / stopped / not installed]
- Docker status: [running / stopped]
- Last working commit: [SHA]

DIAGNOSIS PROTOCOL:
1. Check if the issue is in canonical code or local config.
2. If local config: fix it, do not change canonical code.
3. If canonical code: check if the bug contradicts the blueprint. If yes, fix code. If the blueprint is wrong, STOP — flag as "blueprint bug" for ADR review.
4. If the issue is DNS/networking (Anvil-related): follow the Xubuntu DNS fix protocol (see Anvil install.sh known issue).

DO NOT:
- Do not modify /etc/resolv.conf directly on Xubuntu — use NetworkManager or systemd-resolved.
- Do not disable systemd-resolved stub listener unless you are also configuring NetworkManager's dnsmasq plugin.
- Do not commit local workarounds as canonical fixes.
```

---

## 5. Anti-Drift Protocols

### 5.1 The "Where Did You Get That?" Rule

Every agent output that references a component, a decision, or a constraint must be traceable to a canonical source. If asked, the agent must answer: "I got that from [FILE], section [X]." If it cannot cite a canonical source, the reference is hallucinated and must be discarded.

**Prompt appendix (append to every long-running session):**
```
REMINDER: If you reference any component ID (CORE-XX, HUB-XX, ISPOKE-XX, ESPOKE-XX, BRIDGE-01, DEPLOY-XX), 
an ADR number, an Open Decision, or a structural rule, you MUST cite the canonical file and section. 
If you cannot cite it, say "I don't have a canonical source for this" rather than guessing.
```

### 5.2 The "Frozen Interface" Guard

Per AGRD v3.4 §2.1, once a blueprint's interface is frozen (at admission, even depth 1), changing it requires an ADR. Agents must enforce this:

```
Before modifying any interface (class signature, method name, DTO shape, dependency list), check:
1. Is this blueprint's interface already frozen? (Has it been admitted to the matrix?)
2. If yes: is there an ADR cited in the PR/commit message authorizing this change?
3. If no ADR: REJECT the change. Suggest filing an ADR instead.
```

### 5.3 The "No Silent PostgreSQL" Rule

ADR-013 pivoted to MySQL 8. Agents frequently hallucinate PostgreSQL features (JSONB, RLS, GIN indexes). The guard:

```
When generating DDL, schema, or persistence code:
- Primary engine: MySQL 8.0+ (InnoDB)
- JSON type: MySQL JSON (not JSONB)
- Tenant isolation: DBAL-enforced tenant_id context (not engine RLS)
- PK format: CHAR(26) CHARACTER SET ascii (ULID)
- If the blueprint explicitly mentions a disabled PostgreSQL driver, that's documentation — do not generate PostgreSQL code.
```

### 5.4 The "HUB-31 Is Proposed" Rule

`HUB-31` (Real-Time Analytics) is in ADR-011, status **Proposed**, not Accepted. Six blueprints cite it as `pending`. Agents must not:
- Implement HUB-31 as if it exists
- Add new dependencies on HUB-31
- Treat HUB-31 as a resolved component in timelines

If an agent encounters a reference to HUB-31, it must flag: "HUB-31 is Proposed (ADR-011), not Accepted. This dependency is pending an architecture-lead decision."

---

## 6. Handoff Protocol

When passing context from one agent to another (e.g., Cloud Architect drafts an ADR, Local Implementer implements it), use this format:

```markdown
## AGENT HANDOFF

**From:** [Capability role + session date]
**To:** [Capability role]
**Task:** [What the receiving agent should do]
**Canonical Basis:** [File(s) and section(s) this handoff is based on]
**Assumptions:** [Anything the sender assumed that the receiver must verify]
**Open Decisions:** [Any ODs that affect this task — do not resolve them]
**Deliverables:** [Expected output format]
**Verification:** [How the receiver confirms correctness]

---

[Paste the relevant content here — keep it under 4K tokens if possible]
```

**Why this matters:** Cloud roles lose context between sessions. Local roles have the codebase but not the architectural reasoning. A handoff without canonical citations is a recipe for drift.

---

## 7. Verification Checklist (Every Agent Output)

Before any agent output is considered complete, it must pass this checklist. The tech lead (you) enforces it. The agent should self-check where possible.

| # | Check | Method |
|---|-------|--------|
| 1 | No invented component IDs | Cross-check against Architecture/INDEX.md §2 |
| 2 | No resolved ODs | Cross-check against Architecture/OPEN-DECISIONS.md — if an OD is listed, it's OPEN |
| 3 | No PostgreSQL-specific code | ADR-013 enforcement — MySQL 8 is primary |
| 4 | No HUB-31 dependencies | ADR-011 enforcement — HUB-31 is Proposed |
| 5 | Frozen interfaces respected | AGRD v3.4 §2.1 — ADR required for changes |
| 6 | ULID for cross-service PKs | Master Index §10 policy |
| 7 | Citable canonical sources | Every architectural claim cites a file + section |
| 8 | run.php would pass | Cross-reference validity, no mislabels, structural completeness |
| 9 | No 3-engineer assumptions | AGRD v3.4 §1 — solo tech lead, sequential bets |
| 10 | Depth scale respected | AGRD v3.4 §4.1 — depth 1–6 defined, no skipping |

---

## 8. Common Failure Modes

| Failure | Symptom | Catch It By |
|---------|---------|-------------|
| **The "Sovereign" Collision** | Multiple components named "Sovereign [X]" — HUB-09 was "Sovereign Pulse" renamed to "Sovereign Signal"; ISPOKE-21 was "Sovereign Sentinel" renamed to "Sovereign Scan" | Check INDEX.md for name uniqueness before accepting any agent-generated name |
| **The CORE-09 Mislabel** | Agent calls CORE-09 "crypto" or "encryption" — it's the Encryption Engine, but CORE-16 is Crypto (Hashing/Signing) | INDEX.md §3 — Pattern A, 14 occurrences across corpus |
| **The PostgreSQL Ghost** | Agent generates JSONB, RLS policies, or PostgreSQL-specific DDL | ADR-013 guard — reject any non-MySQL DDL |
| **The 3-Engineer Timeline** | Agent assumes parallel bets, 6-week fixed durations, or peer review | AGRD v3.4 §1 — solo, sequential, measured not projected |
| **The HUB-31 Dependency** | Agent treats HUB-31 as accepted, implements real-time analytics features | ADR-011 guard — HUB-31 is Proposed, not Accepted |
| **The Blueprint Hallucination** | Agent generates a full blueprint for a component that doesn't exist in INDEX.md | Check INDEX.md §2 — if the ID isn't listed, it doesn't exist |
| **The OD Resolution** | Agent "resolves" an Open Decision by asserting an answer | OPEN-DECISIONS.md — if it's listed, it's open. Only an ADR ratified by the tech lead closes it. |
| **The Anvil DNS Break** | Agent running on Xubuntu modifies /etc/resolv.conf or disables systemd-resolved | Anvil install.sh is known-bad on Xubuntu. Use NetworkManager dnsmasq plugin approach (see §4.6). |

---

## 9. Quick Reference: Component IDs

| Tier | Range | Count | Notes |
|------|-------|-------|-------|
| CORE | 01–20 | 20 | CORE-02 is the universal blocker (stub) |
| HUB | 01–30 | 30 | HUB-31 is Proposed only (ADR-011) |
| ISPOKE | 01–27 | 27 | 26–27 are hospitality vertical (not in main repo yet) |
| ESPOKE | 01–18 | 18 | 16–18 are hospitality vertical |
| BRIDGE | 01 | 1 | Inner Rim checkpoint |
| DEPLOY | 01–04 | 4 | DEPLOY-00 is docs site |

**Total canonical:** 101 blueprints (including hospitality) or 96 (excluding hospitality).

---

## 10. Emergency Escalation

If an agent produces output that:
- Contradicts Architecture/INDEX.md
- Resolves an Open Decision without an ADR
- Introduces a new component ID not in INDEX.md
- Reverts the MySQL 8 pivot back to PostgreSQL

**Stop immediately.** Do not commit. Do not continue the session. Start a new session with this prompt:

```
The previous session produced output that may contradict canonical architecture. 
Before continuing, verify the following against Architecture/INDEX.md and Architecture/OPEN-DECISIONS.md:
[Describe the suspected contradiction].
Do not proceed with implementation until the contradiction is resolved.
```

---

## 11. Changelog

| Version | Date | Changes |
|---------|------|---------|
| v1.0 | 2026-08-10 | Initial — ratified alongside AGRD v3.4. Vendor-agnostic capability roles. |

---

*This document is itself governed by the canonical source hierarchy (§3). If it contradicts Architecture/INDEX.md or an accepted ADR, INDEX.md/ADR wins.*
