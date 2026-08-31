# DGLab — Sovereign Stack Architecture

This repository is the home of the **Sovereign Stack**: a polyrepo, tier-isolated PHP architecture for
the DGLab platform (CMS + Studio + admin).

> **This `Architecture/` directory is the single source of truth.**
> Four older trees have been **archived as read-only provenance** and must not be edited or merged from:
> `Arc/`, `Analysis_Critiques_Rewrites/`, `docs/blueprints/`, `docs/architecture/origin/`. Each carries
> an `ARCHIVED.md` banner. See [`INDEX.md` §1](./INDEX.md#1-single-source-of-truth-declaration).

## What lives here

| Folder | Contents |
|---|---|
| [`Core/`](./Core) | 20 Core-tier blueprints (`CORE-01`…`CORE-20`) |
| [`Hub/`](./Hub) | 30 Hub-tier blueprints (`HUB-01`…`HUB-30`) + proposed `HUB-31` (ADR-011) |
| [`Spoke/`](./Spoke) | Internal (`ISPOKE-01`…`25`), External (`ESPOKE-01`…`15`), Bridge (`BRIDGE-01`) |
| [`Deploy/`](./Deploy) | `DEPLOY-00` (docs) … `DEPLOY-04` (promotion) |
| [`ADRs/`](./ADRs) | 10 Accepted decision records + 1 Proposed (`ADR-011`) |
| [`CrossCutting/`](./CrossCutting) | Structure (Wheel, Pulse, Security, Events, Persistence, Boot, Testing, Deployment, Performance), Observability, Glossary, Threat Model |
| [`Critiques/`](./Critiques) | The consolidated critique that drove this consolidation |
| [`Migration/`](./Migration) | `04_MIGRATION_PLAN.md` — the 11-step build sequence |
| [`Verification/`](./Verification) | `INCONSISTENCIES.md` + `lint/run.php` (CI) |
| [`INDEX.md`](./INDEX.md) | **Governance & numbering authority** — read this first |
| [`AUTHORING_GUIDE.md`](./AUTHORING_GUIDE.md) | Blueprint fidelity bar and template |

## Start here

1. **[`INDEX.md`](./INDEX.md)** — the canonical ID→component map, tier DAG, build sequence, and
   governance rules. If anything disagrees with it, `INDEX.md` wins.
2. **[`AUTHORING_GUIDE.md`](./AUTHORING_GUIDE.md)** — before writing or editing any blueprint.
3. **[`Verification/INCONSISTENCIES.md`](./Verification/INCONSISTENCIES.md)** — what was wrong with the
   corpus and how it was reconciled.

## Naming

Every PHP namespace begins with `SovereignStack\`. The bare `Sovereign\` prefix used by predecessor
files is withdrawn. Component names and IDs are authoritative in `INDEX.md` §2.

## Verification

```bash
php Architecture/Verification/lint/run.php
```

The lint re-derives every cross-reference against `INDEX.md` §2 and fails the build on a mismatch. It
runs on every PR via `Verification/lint/architecture-lint.yml`.

## Open decisions

Unresolved forks are tracked in [`OPEN-DECISIONS.md`](./OPEN-DECISIONS.md). They are recorded, not
silently resolved (governance Rule 9).
