# ADR-Gated Radial Delivery (AGRD) v2.0 — Solo Tech Lead Edition

> **Status:** Draft v2.0 · **Supersedes:** AGRD v1.0 (3-person assumption) + Kimi Radial Incremental + Z.ai ADR-Gated Continuous Architecture
> **Related:** `Architecture/INDEX.md` §5, `Architecture/OPEN-DECISIONS.md`, `Architecture/CrossCutting/STRUCTURE-01-Wheel.md`
> **Decision required:** ADR-014 to ratify before Milestone 0 begins
> **Team reality:** 1 tech lead (engineering + architecture decisions) + 1 marketer (copy, content, go-to-market) + 1 media (visual design, demo assets)

---

## 1. The hard truth that invalidates v1.0

Both v1.0 documents assumed **3 engineers**. The actual team is **1 tech lead** + 2 non-engineering contributors (marketer, media). This is not a scheduling tweak — it invalidates the load-bearing assumption under both prior models.

| v1.0 Assumption | Reality | Impact |
|-----------------|---------|--------|
| 3 engineers, tangential ownership | 1 tech lead | All parallel engineering bets are **void**, not risky |
| 6-week bets sized for 3-person throughput | Solo + AI agents | Duration unknown — must be **calibrated**, not projected |
| Peer review on every PR | No peer | **Lint + ADR + automated gates** become the only review |
| Bus factor mitigated by rotation | No rotation possible | **Walking skeleton** approach mandatory — integration surprises must surface early |
| Cooldown is "nice to have" | Cooldown is **essential** | Only person who can catch drift is the same person writing code |

**The good news:** marketer and media are not idle. They form a **second, real, non-blocking track** that runs continuously from week 1, never gated by engineering.

---

## 2. Team topology: One lane, two tracks

```
Engineering Track (Tech Lead + AI Agents)
  └── Sequential ring bets, Milestone 0 first, calibrate after

Content Track (Marketer + Media)
  └── Parallel, continuous, never blocked on engineering
```

### Engineering Track

**Owner:** Tech lead (you). **Multipliers:** AI agents (Cline, Jules, etc.) for implementation throughput. **Bottleneck:** Your judgment and attention on every ADR, every PR, every Ring Lock.

AI agents multiply **implementation** speed but do not substitute for **review and judgment**. The critical path is still your brain — every merge decision, every architecture call, every kill-or-continue assessment at week 3 of a bet.

### Content Track

| Role | Deliverables | DGLab Integration | Starts |
|------|--------------|-------------------|--------|
| **Marketer** | All user-facing copy, translation keys, go-to-market content, SEO strategy | `HUB-13` (I18n) — every string is a translation key from day one; `ESPOKE-05` (marketing pages); `ESPOKE-17` (AI Concierge tone) | Week 1 |
| **Media** | Visual design system, theme tokens, demo/video assets, brand guidelines | `HUB-26` (ThemeInterface tokens + tone contract); `ESPOKE-01` (public site visuals); demo content for Dev Hub | Week 1 |

**Rule:** Content track never gates engineering, and engineering never gates content. The marketer can write copy for `ISPOKE-01` (Admin Panel) before a single line of its code exists, because copy lives in `HUB-13` translation files. The media designer can define `HUB-26` tokens before any Spoke implements them.

---

## 3. The delivery model: Walking Skeleton + Calibrated Bets

### 3.1 Milestone 0: The Walking Skeleton (Weeks 1–8)

Instead of "finish Core, then finish Hub, then finish Spokes" (you don't see a working system until week 40), Milestone 0 is a **minimal end-to-end round trip**.

**Scope:**
- `CORE-02` (DI Container) — just enough to boot and resolve dependencies
- `CORE-18` (Kernel) — minimal boot sequence
- `HUB-01` (Config) — feature flags, tenant config
- `HUB-02` (Cache) — basic get/set
- `HUB-04` (Identity) — stub auth (hardcoded token for dev)
- `HUB-21` (Tenancy) — single-tenant mode (you)
- `BRIDGE-01` (Vanguard) — minimal routing, no Zero-Exposure yet
- `ISPOKE-01` (Admin Panel) — one page: "Hello, [name]"
- `ESPOKE-01` (Public CMS) — one page: "Welcome"

**Exit gate:** A Pulse can enter at the Outer Rim, pass through every ring, touch Core, and return. No business logic. No security. Just proof the Wheel spins.

**Why 8 weeks (not 6):** Solo + AI agents is faster than 1 person alone, but slower than 3 people. 8 weeks is a guess — the real number is whatever it actually takes. The point is to **measure**, not to hit a deadline.

### 3.2 Calibration: Size Bets 1+ from Real Velocity

After Milestone 0, you have a real number: "I completed X blueprints in Y weeks with Z agent hours." Size all subsequent bets from that ratio.

**Do not use the v1.0 week estimates.** They were fiction based on a 3-person assumption. Your real velocity is the only number that matters.

### 3.3 Ring Bets (post-Milestone 0)

| Bet | Ring | Scope | Sizing Method |
|-----|------|-------|---------------|
| 1 | **Core** | `CORE-02` production-grade, `CORE-04`–`06`, `CORE-08`–`10`, `CORE-16`, `CORE-18`, `CORE-19` | 1.5× Milestone 0 Core time |
| 2 | **Hub Critical** | `HUB-01`, `HUB-02`, `HUB-04`, `HUB-05`, `HUB-08`, `HUB-20`, `HUB-21` | 1.5× Milestone 0 Hub time |
| 3 | **Hub Full** | Remaining `HUB-03`, `HUB-06`, `HUB-07`, `HUB-09`–`19`, `HUB-22`–`30` | Calibrated |
| 4 | **Thick Spokes (a)** | `ISPOKE-01`–`15` | Calibrated |
| 5 | **Thick Spokes (b)** | `ISPOKE-16`–`25` | Calibrated |
| 6 | **Inner Rim** | `BRIDGE-01` hardening — Zero-Exposure, penetration policy | Calibrated |
| 7 | **Thin Spokes + Outer Rim** | `ESPOKE-01`–`15` + `DEPLOY-01`–`04` | Calibrated |
| 8 | **Hardening** | Security audit, performance baseline, chaos engineering | Calibrated |

**No parallel bets.** Sequential only. One ring at a time.

### 3.4 Cooldowns: Non-negotiable

Between every bet: **2-week cooldown** (not 1 — solo means no one else catches drift).

Cooldown work:
- Update `INDEX.md` §2 if IDs shifted
- Reconcile `INCONSISTENCIES.md`
- Write missing `STRUCTURE-XX` sections
- Refactor inside the locked ring (interface is frozen, so refactoring is safe)
- Archive obsolete docs
- **Content track sync:** Review marketer copy, media design tokens for the next ring
- **Rest:** You are the only engineering brain. Burnout kills the project.

---

## 4. Security: Moved Left, But Realistically

v1.0's "security moves left" is correct in principle, but solo execution changes the timeline:

| Phase | Security Deliverable | Realistic Solo Timeline |
|-------|---------------------|------------------------|
| Milestone 0 | None. Skeleton is dev-only, no public surface. | Weeks 1–8 |
| Bet 1 (Core) | Authz facet stub — scope slots in every Core crossing | Weeks 9–18 |
| Bet 2 (Hub Critical) | JWT validation, tenant lane assignment active | Weeks 21–32 |
| Bet 3 (Hub Full) | Audit facet active — every Hub crossing logged | Weeks 35–46 |
| Bet 4–5 (Thick Spokes) | ISPOKE audit enforced | Weeks 49–70 |
| Bet 6 (Inner Rim) | Zero-Exposure enforcement | Weeks 73–78 |

**Key change:** Zero-Exposure is not tested until Bet 6, but the **scaffolding** for it (scope slots, audit trails) is built incrementally from Bet 1. You don't bolt security on at week 73 — you build the hooks early, then flip the enforcement switch when the time comes.

---

## 5. OD Resolution: Embedded in Bets (File2's Table, Corrected)

| OD | Topic | Target Bet | Resolution Path |
|----|-------|------------|-----------------|
| OD-01 | HUB-31 real-time analytics | Bet 3 (Hub Full) or deferred | Accept ADR-011 or move to Future Scale |
| OD-02 | Post-quantum JWT signing | Bet 4 (Thick Spokes a) | Ship ES256 (ADR-003) as interim |
| OD-03 | Soft component-name collisions | Bet 3 (Hub Full) | Resolve naming conflicts in `INDEX.md` |
| OD-04 | Exemplar count / label confusion | Bet 5 (Thick Spokes b) | Clean up `ISPOKE-01`/`ESPOKE-01` references |
| OD-05 | ISPOKE folder name vs `INDEX.md` name | Bet 4 (Thick Spokes a) | Align folder structure with canonical IDs |
| OD-06 | ADR-010 opcache scope vs `CORE-02` | Bet 1 (Core) | Define opcache baseline for DI container |

**Source:** File2's OD table (matches live `OPEN-DECISIONS.md`). File1's OD table is discarded — it contained internal contradictions (OD-06 described two different topics in different sections).

---

## 6. PR Gates: Lint Is Your Only Reviewer

With no peer engineer, the automated gates become **blocking**, not advisory.

### Automated gates (CI — required, not optional)

| Gate | Tool | Failure Mode |
|------|------|-------------|
| Architecture lint | `run.php --ring=<current>` | Non-zero exit = PR blocked |
| Reference existence | `run.php` | Wrong ID reference = PR blocked |
| Tier direction | `run.php` | Violates ADR-004 = PR blocked |
| Unit tests | PHPUnit | < 80% coverage = PR blocked |
| Pulse contract tests | Custom | 6-tuple drift = PR blocked |
| Performance benchmark | `phpunit --group performance` | Misses target = PR blocked (Bet 7+) |

### Human gates (self-enforced)

| Gate | Method | Failure Mode |
|------|--------|-------------|
| ADR reference | PR template requires `ADR:` field | No ADR = no merge |
| 24-hour rule | Sleep on every non-trivial PR | Merge in haste = regret at leisure |
| Weekly ADR review | 30 min, every Monday | ODs don't rot |
| Kill trigger check | Week 3 of every bet | Assess descope/kill |

**The 24-hour rule:** With no peer reviewer, your worst enemy is your own fatigue. Every non-trivial PR waits 24 hours before merge. This is non-negotiable.

---

## 7. Rituals: Lightweight, But Real

| Ritual | Frequency | Duration | Purpose |
|--------|-----------|----------|---------|
| **Weekly ADR Review** | Monday | 30 min | Triage OD queue, assign targets |
| **Bi-weekly Content Sync** | Wednesday | 45 min | Review marketer copy, media tokens for upcoming ring |
| **Bet Architecture Review** | Week 3 of every bet | 1 hour | Kill or descope if diverging |
| **Ring Lock Ceremony** | End of every bet | 2 hours | Demo, lint, freeze, plan next |
| **Quarterly Reconciliation** | Every 3 months | 2 days | Canonical tree diff, archive, plan |

**Total ceremony:** ~2.5 hours/week during bets. Sustainable for one person.

---

## 8. Risk Management: Kill Triggers + Rollback

### Kill triggers (Bet Architecture Review, Week 3)

| Trigger | Action |
|---------|--------|
| CORE-02 still incomplete at Week 5 of Bet 1 | **Kill Bet 1.** Descope to "CORE-02 only." Everything else moves to Bet 2. |
| HUB-04 cannot resolve OD-02 (post-quantum JWT) | **Kill crypto facet.** Ship ES256 interim. Move post-quantum to Future Scale. |
| Performance target missed by > 50% | **Kill the feature causing the miss.** Ship the rest. |
| You are burned out (subjective, but real) | **Kill the bet.** Take a 2-week cooldown. The project dies if you do. |

### Rollback strategy

Every Ring Lock produces a **rollback artifact**:

| Artifact | Contents | Stored |
|----------|----------|--------|
| Ring snapshot | Git tag + container digest + DB migration version | GitHub Releases |
| Pulse state | Serialized dormant Pulses, queue depth | `CORE-19` snapshot table |
| Config state | `HUB-01` flags, `HUB-21` tenant config | Sealed secret backup |

**Rule:** If a bet fails Ring Lock, roll back to the previous ring's snapshot. 48 hours to fix or kill. No "fix forward" during Ring Lock.

---

## 9. Content Track: Detailed Deliverables

### Marketer

| Week | Deliverable | DGLab Destination |
|------|-------------|-------------------|
| 1–2 | Brand voice guide (Internal vs. External tone) | `HUB-26` tone contract doc |
| 3–4 | Admin panel copy (all strings as translation keys) | `HUB-13` English locale file |
| 5–6 | Public site copy (SEO-optimized) | `ESPOKE-01` content + `HUB-13` |
| 7–8 | AI Concierge FAQ knowledge base (50 questions) | `HUB-14` index + `ESPOKE-17` training data |
| Ongoing | Translation keys for every new Spoke | `HUB-13` locale files |

### Media

| Week | Deliverable | DGLab Destination |
|------|-------------|-------------------|
| 1–2 | Design system tokens (colors, typography, spacing) | `HUB-26` ThemeInterface |
| 3–4 | Admin theme (dark mode, component library) | `HUB-26` Admin theme tokens |
| 5–6 | Public theme (light mode, marketing visuals) | `HUB-26` Public theme tokens |
| 7–8 | Demo video script + assets for Dev Hub | `ESPOKE-04` (Dev Hub) |
| Ongoing | Visual assets for every new Spoke | Spoke-specific asset directories |

---

## 10. Migration Path: Next 30 Days

| Day | Action | Deliverable |
|-----|--------|-------------|
| 1 | Write ADR-014: "Adopt AGRD v2.0 (Solo Edition)" | `Architecture/ADRs/ADR-014-agrd-v2-solo.md` |
| 2–3 | Assign OD-01–06 owners (you, for all) | Updated `OPEN-DECISIONS.md` |
| 4–7 | Add `--ring` flag to `run.php`, wire as required check | PR to `Verification/lint/run.php` |
| 8–10 | Set up PR template: `ADR:` field mandatory | `.github/pull_request_template.md` |
| 11–14 | Content track kickoff: marketer writes voice guide, media writes token spec | `HUB-26` tone contract, token draft |
| 15 | **Milestone 0 starts.** You + agents build the walking skeleton. | — |
| 21 | Milestone 0 check-in: assess velocity, adjust timeline | Calibrated week estimate |
| 36–50 | Milestone 0 completes. Ring Lock Ceremony. | Walking skeleton demo |
| 51–56 | Cooldown. Plan Bet 1. Content track delivers first locale files. | — |
| 57 | **Bet 1 starts.** Core ring. | — |

---

## 11. What v2.0 Keeps vs. Discards

| From v1.0 | Status | Reason |
|-----------|--------|--------|
| Ring bets as delivery unit | ✅ Kept | Correct in principle, just re-sized |
| ADR-gated PR flow | ✅ Kept | More critical solo — lint is your only reviewer |
| `run.php` as notary | ✅ Kept | Now blocking, not advisory |
| Soft → Hard Freeze | ✅ Kept | Correct for greenfield |
| Kill triggers | ✅ Kept | More critical solo — no one else catches drift |
| Cooldowns | ✅ Kept | Extended to 2 weeks (burnout risk is higher) |
| 3-person team topology | ❌ Discarded | Reality: 1 tech lead |
| Tangential ownership | ❌ Discarded | No team to rotate |
| Parallel bets (6/7/8) | ❌ Discarded | Impossible with 1 person |
| 6-week bet duration | ❌ Discarded | Calibrate from Milestone 0 |
| Bus factor mitigation (primary + reviewer) | ❌ Discarded | No reviewer; 24-hour rule replaces |

---

## 12. The One Anti-Pattern to Watch For

> **Don't let AI agents become the architect.**

AI agents (Cline, Jules, etc.) are brilliant at implementation and terrible at architecture. They will happily:
- Implement a feature that violates the Wheel model because you didn't specify the ring constraint
- Generate code that bypasses the Hub because it's "faster"
- Produce a "working" solution that breaks the lint rules you spent weeks formalizing

**Your job is to be the architect. The agent's job is to be the implementer.** Every agent-generated PR must pass the same gates as yours. The ADR Review is where decisions are made — by you. The linter enforces them. The agent executes them.

---

## 13. Open Questions (for v2.1)

1. **AI agent scope:** Should agents be allowed to write ADR drafts, or only implementation PRs? *(Recommended: Implementation only. You write ADRs.)*
2. **Hospitality vertical timing:** Does the partner's 5-workflow integration (ISPOKE-26/27, ESPOKE-16/17/18) start after Bet 3 (Hub Full) or after Milestone 0? *(Recommended: After Bet 3 — needs HUB-21, HUB-22, HUB-02.)*
3. **Marketer/media access to repo:** Should they commit directly to `HUB-13` locale files and `HUB-26` token files, or PR through you? *(Recommended: Direct commit to content-only files, PR for anything touching code.)*
4. **Milestone 0 scope creep:** Is 8 blueprints too many for a skeleton? Should it be 5? *(Recommended: Start with 8, descope if Week 5 assessment says kill.)*
