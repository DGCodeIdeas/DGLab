# Arc — Canonical DGLab Architecture

This folder is the active source of truth for the DGLab polyrepo architecture.

## What belongs here
- `Arc/Blueprints/`: active canonical blueprints for Core, Hub, Bridge, Spoke, and Deploy.
- `Arc/Governance/`: the authoritative master index, authoring guide, glossary, threat model, observability rules, migration plan, and ADRs.

## What does not belong here
- Legacy Vision A documents from `docs/architecture/origin/`.
- The `docs/blueprints/disapproved/` folder.
- Any stale evaluation snapshot unless it is explicitly dated and archived in `Arc/Governance/`.

## Authority
The canonical architecture is declared by `Arc/Governance/01_MASTER_INDEX.md`. If any other document in the repo disagrees with the ID mapping, tier count, or cross-reference rules in that file, `Arc/Governance/01_MASTER_INDEX.md` is authoritative.

## Purpose
`Arc/` exists to give the DGLab team a single, unambiguous point of truth for architectural blueprints and implementation specifications.
