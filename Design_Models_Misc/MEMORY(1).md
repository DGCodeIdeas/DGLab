# MEMORY.md — DGLab / Sovereign Stack Canonical Context

**Purpose:** Paste this into any AI agent session (local or cloud) before starting work. This is the
minimum viable context every agent needs to produce compatible output.

**Project:** DGLab (Sovereign Stack) — custom PHP MVC framework, multi-tenant, event-driven.
**Repo:** https://github.com/DGCodeIdeas/DGLab
**Methodology:** AGRD v3.4 Spiral Deepening
**Last Updated:** 2026-08-10

---

## 1. Team

- **1 Tech Lead** (solo, AI-augmented) — you are talking to them. All engineering decisions gate here.
- **1 Marketer** — owns copy via HUB-13 translation keys, ESPOKE-05 content.
- **1 Media** — owns HUB-26 theme tokens, visual design system, demo assets.

**No 3-engineer assumption.** All engineering is sequential. No parallel bets.

---

## 2. Architecture Model

**The Wheel:** 6 concentric rings. A Pulse (work unit) flows inward from Outer Rim toward Core,
how deep depends on the request, then flows back out.

```
Outer Rim      ← Entity touches here (public traffic)
Thin Spokes    ← External Spokes (ESPOKE-01..18) — public-facing, untrusted
Inner Rim      ← BRIDGE-01 checkpoint — gates External↔Internal crossing
Thick Spokes   ← Internal Spokes (ISPOKE-01..27) — staff-facing, broader access
Hub            ← 30 services (HUB-01..30) — stateful layer, mandatory for Core crossings
Core           ← 20 components (CORE-01..20) — framework kernel
```

**Only 2 checkpoints:** Outer Rim (HUB-08 Gateway) and Inner Rim (BRIDGE-01). Everything else is
free lateral flow within its layer.

**Pulse 6-tuple:** `(pulse_id, entry_spoke, exit_spoke, depth, lane, pulse_class)`
- `lane` = tenant ID (HUB-21)
- `pulse_class` = live | dormant | purge | ignition

**Depth scale:** 1=stub, 2=happy path, 3=error paths, 4=observability, 5=production hardening,
6=at-scale verified.

---

## 3. Technology Stack

| Layer | Choice | Canonical Source |
|-------|--------|------------------|
| Language | PHP 8.3+ | CORE-01 (Loom) |
| Framework | Custom (Sovereign Stack) | All CORE blueprints |
| Database | MySQL 8.0+ (InnoDB) | ADR-013 |
| Cache | Redis 7 | HUB-02 |
| Queue | RabbitMQ / AWS SQS | HUB-10 |
| Events | Internal event dispatcher | CORE-03, HUB-09 |
| PK Format | ULID — CHAR(26) CHARACTER SET ascii | Master Index §10 |
| Tenant Isolation | DBAL-enforced tenant_id context | HUB-21 + CORE-19 |
| JSON | MySQL JSON (NOT JSONB) | ADR-013 |
| Auth | JWT via HUB-04 + HUB-05 | HUB-04, HUB-05 |
| Frontend | Vanilla JS + HUB-26 theme tokens | HUB-26 |

**PostgreSQL is disabled-by-default.** Do not generate PostgreSQL-specific code (JSONB, RLS, GIN).

---

## 4. Blueprint Inventory

| Tier | Count | Status |
|------|-------|--------|
| CORE | 20 | CORE-01, CORE-03 implemented; CORE-02 is stub (universal blocker) |
| HUB | 30 | All documented, none implemented |
| ISPOKE | 27 | 01..15 documented in repo; 16..25 promoted; 26..27 hospitality vertical |
| ESPOKE | 18 | 01..15 documented in repo; 16..18 hospitality vertical |
| BRIDGE | 1 | Documented, not implemented |
| DEPLOY | 4 | 01..04 documented |
| **Total** | **101** (96 canonical + 5 hospitality) | **0 placeholders** |

**HUB-31** (Real-Time Analytics) is **Proposed only** (ADR-011). Do not implement dependencies on it.

---

## 5. Methodology: AGRD v3.4 Spiral Deepening

**Current phase:** Pre-Cooldown 0. Nothing implemented yet under v3.4.

**Sequence:**
1. Cooldown 0 (2 weeks) — freeze UI wireframe, theme tokens, string key taxonomy
2. Milestone 0 (target ≤8 weeks) — 8 blueprints, end-to-end walking skeleton
3. Measure W (actual weeks Milestone 0 took)
4. Spiral Deepening laps — widen + deepen per lap

**Key rules:**
- Interface freeze happens at admission (depth 1), not at production depth. Changing a frozen
  interface requires an ADR.
- Bets are sequential, one ring at a time.
- Cooldowns are 2 weeks (solo operation).
- Kill trigger: 1.5× ceiling on bet duration. Milestone 0 kill: >8 weeks = stop and reassess.
- No timeline is trustworthy until Milestone 0 produces W.

---

## 6. Open Decisions (ODs)

These are OPEN. Do not resolve them. Do not assume answers.

| OD | Topic | Current Timing |
|----|-------|----------------|
| OD-01 | HUB-31 acceptance (Real-Time Analytics) | Proposed, no deadline |
| OD-02 | Post-quantum / algorithm-agility JWT signing | Resolve by end of Cooldown 1 |
| OD-03 | Soft component-name collisions | Track, resolve when encountered |
| OD-04 | Exemplar count (ISPOKE-01 / ESPOKE-01 labels) | Track, resolve when encountered |
| OD-05 | ISPOKE folder name vs INDEX.md name | Track, resolve when encountered |
| OD-06 | ADR-010 opcache baseline (blocked on CORE-02) | Resolve during Milestone 0 |

---

## 7. Known Issues & Hazards

| Issue | Impact | Mitigation |
|-------|--------|------------|
| **Anvil DNS bug** | install.sh breaks Xubuntu internet by replacing /etc/resolv.conf | Use NetworkManager dnsmasq plugin, not system dnsmasq on port 53 |
| **CORE-02 stub** | Only .gitkeep in packages/core/container/ — blocks everything | Milestone 0 priority #1 |
| **Sovereign naming collisions** | HUB-09 was "Sovereign Pulse" → renamed "Sovereign Signal" | Check INDEX.md before using "Sovereign [X]" |
| **CORE-09 mislabel** | Frequently called "crypto" — but CORE-16 is Crypto | CORE-09 = Encryption Engine; CORE-16 = Hashing/Signing |
| **HUB-28 confusion** | Was "Metrics" but HUB-22 is Billing, HUB-15 is Health | HUB-28 = Metrics Dashboard; verify context |
| **PostgreSQL ghost** | Agents hallucinate JSONB, RLS, GIN | ADR-013 guard — reject non-MySQL DDL |
| **3-engineer assumption** | Predecessor docs assumed parallel bets | Solo sequential — no exceptions |

---

## 8. Canonical Source Hierarchy (Quick Reference)

When uncertain, check in this order:

1. `Architecture/INDEX.md` — numbering authority, component IDs
2. `Architecture/OPEN-DECISIONS.md` — ODs (these are OPEN)
3. `Architecture/ADRs/ADR-XXX.md` — accepted decisions
4. `Architecture/CrossCutting/` — structure docs, SDLC, prompts
5. `Architecture/{Core,Hub,Spoke}/` — individual blueprints
6. `Verification/lint/run.php` — automated checks
7. `packages/*/` — live code

**Lower number wins.** If you find a contradiction, report it and default to #1.

---

## 9. Agent Interaction Rules

- **You are a tool, not an architect.** Implement, critique, or verify against canonical docs. Do not
  invent components, rename things, or resolve ODs.
- **Cite canonical sources.** Every architectural claim must cite file + section. If you cannot cite
  it, say "I don't have a canonical source for this."
- **Frozen interfaces require ADRs.** Per AGRD v3.4 §2.1, changing a frozen interface is ADR-gated.
- **No HUB-31 dependencies.** It is Proposed (ADR-011), not Accepted.
- **No PostgreSQL code.** MySQL 8 (InnoDB) is primary. JSONB = wrong.
- **ULID for cross-service PKs.** Integer only for purely-local surrogate keys.

---

## 10. Current Immediate Priorities

1. **Push AGRD v3.4** to `Architecture/CrossCutting/SDLC-AGRD.md`
2. **Push PROMPTS.md** to `Architecture/CrossCutting/PROMPTS.md`
3. **Push MEMORY.md** (this file) to `Architecture/CrossCutting/MEMORY.md`
4. **Start Cooldown 0** — freeze UI wireframe (ESPOKE-05), theme tokens (HUB-26), string keys (HUB-13)
5. **Begin Milestone 0** — implement CORE-02 (DI Container) as first priority

---

*This document is governed by the canonical source hierarchy (§8). If it contradicts*
*Architecture/INDEX.md or an accepted ADR, INDEX.md/ADR wins.*
