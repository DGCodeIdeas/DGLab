# PHASE ISPOKE-02: Internal Developer Portal and Documentation Hub

## Tier
Internal Spoke (Staff-only Application)

## Resolves
This file's own cross-references were checked against `01_MASTER_INDEX.md` §3 and found clean — no
correction needed here. Adds stated benchmark methodology (Finding 10).

## Component Name
Sovereign Forge Portal

## Description
A dedicated portal for internal developers/engineers: hosts documentation (including these
blueprints), API specifications, code standards, and onboarding guides; interactive tools for testing
Hub API versioning and exploring the GraphQL schema.

## Build Status
🔴 **Blocked** on `HUB-11` (Storage), `HUB-14` (Search), `HUB-24` (GraphQL), `HUB-26` (UI), `HUB-28`
(API Versioning) — none implemented.

## Dependency Status
- **Direct Hub:** `HUB-11`, `HUB-14`, `HUB-24`, `HUB-26`, `HUB-28`, `HUB-15`, `HUB-16`. *(All verified
  against `01_MASTER_INDEX.md` §2/§4 — correct.)*
- **Transitive Core:** `CORE-14`, `CORE-13`, `CORE-20`, `CORE-03`.

## Architectural Design
- **DocEngine** — parses Markdown blueprints/specs into a searchable web interface.
- **ApiExplorer** — interactive UI for exploring the Hub's REST and GraphQL APIs.
- **StandardsGuide** — living document pulling PSR-12 and internal coding standards from the repo.
- **GraphiQL_PHP** — pure-PHP GraphQL explorer using `HUB-24`.

## Integration Strategy
- **Bootstrapping:** reads architectural documents via `HUB-11`.
- **UI Rendering:** `HUB-26` documentation layouts.
- **Orchestration:** reports doc-build health via `HUB-16`/`HUB-15`.
- **Search:** full-text search via `HUB-14`.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Markdown integrity | Automated link-check across every rendered blueprint page in CI; assert zero broken internal links. |
| Search latency | State environment before citing "< 100ms" — measure against a realistic fixture set (81+ real blueprint files, not a handful) once `HUB-14` exists (Finding 10). |
| API sync | Integration test: register a schema change in `HUB-24`, assert the GraphiQL explorer's introspection output reflects it without a manual rebuild step. |

## CI Verification Criteria
- Broken-link scan across the full blueprint corpus, blocking.
- API-sync test (above), blocking.
- Search latency measured against the realistic fixture set, reported with environment stated.

## SemVer Impact
**Minor.** Enhances developer productivity and architectural governance.
