# ISPOKE-18: Scheduled Task Manager

> **Placeholder blueprint.** This file exists so that the tier inventory in `INDEX.md` §4 (25 Internal
> Spokes) resolves to real files. It is deliberately **below** the `AUTHORING_GUIDE.md` fidelity bar and
> is exempt from the Phase-1 fidelity gate. Re-authoring to full fidelity is Phase-2 work.

## Tier
Internal Spoke

## Resolves
Finding 13 (`Critiques/00_CRITIQUE.md`) — the Internal Spoke tier is under-counted in every score and
timeline; `INDEX.md` §4 claims 25 Internal Spokes while only `ISPOKE-01..15` existed as files.

## Component Name
Sovereign Cron (Scheduling) — `SovereignStack\Internal\Cron`

## Description
A UI and engine for defining, scheduling, monitoring, and managing recurring background tasks. Cron-expression configuration with failure notification and retry policies.

Scope, contracts, and reference implementation are **not yet specified**. Nothing downstream may assume
an interface from this file. Any component that needs ISPOKE-18 today must record the dependency as
`pending` and raise it in `OPEN-DECISIONS.md`.

## Build Status
📝 **Placeholder — not started, not specified.** Blocked behind the whole Internal Spoke tier, which is
itself blocked on `CORE-02` (DI Container, ❌ stub) per `INDEX.md` §2.

## Dependency Status
- **Upward:** HUB-10 (Queue), HUB-01 (Config), HUB-15 (Health), ISPOKE-07 (Notifications)
- **Downward:** none declared.
- **Runtime:** not yet specified.

## Sequencing Rationale
Extends ISPOKE-08 (Workflow Engine) with time-based trigger capabilities not covered in the base workflow definition.

**Estimated documentation window:** Weeks 30–34 (see `Migration/04_MIGRATION_PLAN.md`).

## Architectural Design
Not specified. A Phase-2 re-author must supply the full `AUTHORING_GUIDE.md` section set: Class Map,
Interface Contracts, Reference Implementation, SQL DDL (PostgreSQL 16 per ADR-007, `CHAR(26)` ULID
primary keys per ADR-009), and at least one Mermaid sequence diagram.

## Integration Strategy
Not specified.

## Benchmark & Verification Methodology
Not specified. **Provisional, unverified** — no performance target may be quoted for this component
until a harness, baseline, and load model exist (Governance Rule 2).

## CI Verification Criteria
Not specified. The Phase-1 lint treats placeholder files as exempt; the Phase-2 gate does not.

## Security Properties
Not specified. Inherits the Internal Spoke tier invariants from `CrossCutting/THREAT_MODEL.md`:
staff-only reachability, no public ingress, all access mediated by `HUB-04` (Identity) and `HUB-05`
(Guardian/RBAC), every mutation audited through `HUB-06`.

## Migration Notes
None — nothing depends on this component yet.

## SemVer Impact
**Not applicable** — no released contract.
