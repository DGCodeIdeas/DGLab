# DGLab Old-to-New Architecture Migration Map
## Deliverable D — Proposed `Architecture/component-inventory.yaml` Schema

**Status: SPECIFICATION ONLY — the file is NOT created or committed.** This deliverable documents the proposed schema, representative entries, and validation rules.

---

## D-1. Design goals

1. Single machine-readable source of truth for component **identity, tier, canonical path, namespace, status, and declared dependencies** — exactly the facts INDEX.md §0 claims to own, made executable.
2. Generated artifacts (tier indexes, DAG summaries, build orders) derive FROM this file, never the reverse.
3. Does **not** supersede: FROZEN-CONTRACTS.md (contract registry), ADRs (decisions), blueprints (responsibility detail), or the Verified DAG (implementation-reality edges). The inventory records *references* to them.

## D-2. Proposed schema (YAML)

```yaml
# Architecture/component-inventory.yaml
# Authority: component identity, tier, canonical path, namespace, status,
#            declared component dependencies, test ownership, entrypoints.
# NOT authority for: contract freezing (FROZEN-CONTRACTS.md), decisions (ADRs),
#            implementation reality (VERIFIED-DAG.md), responsibilities detail (blueprints).
schema_version: 1

components:
  - component_id: CORE-02            # stable, unique; matches blueprint numbering
    name: DI Container
    tier: core                        # core|hub|bridge|spoke-internal|spoke-external|product|cli|tools|platform
    path: Core/Container              # canonical path AFTER migration; pre-migration: packages/core/container
    namespace: DGLab\Core\Container   # canonical PSR-4 prefix (null for non-PHP)
    status: implemented               # blueprint-only|implemented|partial|stub|archived|deprecated
    responsibilities:
      - PSR-11 dependency injection with reflection autowiring
      - Fiber-scoped pulse() instance cache (Shape C)
    dependencies:                     # DECLARED architectural deps (reviewed decisions, not auto-imported)
      - component_id: CORE-10
        kind: runtime
        evidence: "ViewFactory::fromConfig imports ConfigInterface"   # only for verified edges
    dependents: [CORE-18]             # generated/read-only view; refreshed by tooling
    test_path: Core/Container/tests   # REQUIRED for implemented/partial/stub
    entrypoints: []                   # CLIs, HTTP front controllers
    configuration_paths: []           # config files consumed
    resource_paths: []                # templates/assets owned
    contract_refs:                    # frozen-contract registry cross-refs
      - FROZEN-CONTRACTS.md#CORE-02.ContainerInterface
    migration_action: move+rename     # move+rename|split|merge|retain|remove|defer|none
    migration_from: packages/core/container   # populated until migration completes, then dropped
    evidence:                         # every claim traceable
      - path: packages/core/container/src/Container.php
        symbol: SovereignStack\Core\Container\Container
    owner: core                       # optional ownership/team tag
```

## D-3. Field rules

| Field | Rule |
|---|---|
| `component_id` | `^(CORE|HUB|ISPOKE|ESPOKE|BRIDGE|DEPLOY|RUNTIME|PRODUCT|CLI|TOOL)-\d{2}$` or `PRODUCT-LMS`-style slugs for products. Unique; never reused after removal (removed IDs move to a `retired_ids` section with removal ADR ref). |
| `path` | Unique across components; must exist on disk for status ≠ blueprint-only; must NOT exist for status: blueprint-only. |
| `namespace` | Unique among PHP components; PSR-4-valid; exactly one root-autoload prefix owner per namespace (**forbids the current dual-root `Core\Http` state by construction**). |
| `tier` | enum above. Products may not be depended on by any other tier. Spokes may not depend on Products. External spokes additionally may not be depended on by internal spokes (except via Bridge). |
| `status` | enum above; `blueprint-only` requires a blueprint file ref in evidence. |
| `dependencies[].component_id` | must reference an existing component (no dangling); no cycles (validated by topological sort); direction rules per tier. |
| `test_path` | REQUIRED when status ∈ {implemented, partial, stub}; must exist; must be covered by a CI runner id (new field `ci_runner`). |
| `entrypoints` | every declared entrypoint path must exist and be executable. |
| `migration_action` | present only while migration is in flight (dropped at completion). |
| `evidence` | ≥1 entry for every component with status ≠ blueprint-only. |

## D-4. Representative entries (verified evidence, abbreviated)

```yaml
schema_version: 1
components:
  - component_id: CORE-04
    name: HTTP Message
    tier: core
    path: Core/Http
    namespace: DGLab\Core\Http
    status: implemented
    test_path: Core/Http/tests
    ci_runner: packages-ci[core-http]     # matrix entry re-pointed at Stage 9
    contract_refs: [FROZEN-CONTRACTS.md#CORE-04.MessageFactoryInterface]
    migration_action: split                # partner: CORE-05 leaves the shared namespace
    migration_from: packages/core/http-message
    evidence: [{path: packages/core/http-message/src/Request.php}]

  - component_id: CORE-05
    name: Middleware
    tier: core
    path: Core/Middleware
    namespace: DGLab\Core\Middleware       # OD-01: split from SovereignStack\Core\Http
    status: implemented
    test_path: Core/Middleware/tests
    migration_action: split
    migration_from: packages/core/middleware
    evidence: [{path: packages/core/middleware/src/MiddlewarePipeline.php}]

  - component_id: CORE-07
    name: NeoSPX Lexer
    tier: core
    path: Core/NeoSpx
    namespace: DGLab\Core\NeoSpx
    status: partial                        # lexer only; parser/compiler/runtime absent
    test_path: Core/NeoSpx/tests
    notes: "not in root composer.json today; add at migration"
    migration_action: move+rename
    migration_from: packages/core/neospx
    evidence: [{path: packages/core/neospx/src/NeoSPXLexer.php}]

  - component_id: HUB-04
    name: Hub Identity
    tier: hub
    path: Hub/Identity
    namespace: DGLab\Hub\Identity
    status: implemented
    dependencies:
      - {component_id: CORE-16, kind: runtime}
      - {component_id: CORE-19, kind: runtime}
      - {component_id: CORE-18, kind: runtime}
    test_path: Hub/Identity/tests
    migration_action: move+rename
    migration_from: packages/hub/identity
    evidence: [{path: packages/hub/identity/src/Jwt/JwtSigner.php}]

  - component_id: PRODUCT-LMS
    name: LMS Product
    tier: product
    path: Products/LMS
    namespace: DGLab\Products\LMS
    status: implemented                     # walking skeleton, pre-domain
    dependencies:
      - {component_id: BRIDGE-01, kind: runtime}
      - {component_id: CORE-18, kind: runtime}
      # ... 11 core imports verified in ApplicationFactory
    test_path: [Products/LMS/tests, Testsuite/Integration]
    entrypoints: [Products/LMS/public/index.php]
    resource_paths: [Products/LMS/resources/views]
    migration_action: split                 # from app/ + public/
    migration_from: [app/, public/]
    evidence: [{path: app/ApplicationFactory.php}, {path: public/index.php}]

  - component_id: PRODUCT-CODEX
    name: Codex Product
    tier: product
    path: Products/Codex
    namespace: DGLab\Products\Codex
    status: blueprint-only                   # NO application code today; reserved per agreed target structure (§3)
    dependencies:
      - {component_id: ISPOKE-09, kind: runtime, evidence: "declared intent — Codex Product will compose the Codex spoke; not yet wired (no source)"}
    test_path: null                          # none — no code today
    entrypoints: []                          # reserved for Products/Codex/public/index.php when implemented
    migration_action: defer                  # no source to migrate; directory is reserved as the future boundary
    migration_from: null
    evidence:
      - path: README.md                      # brief §3 target structure lists Products/LMS|Codex/
        symbol: "target directory listing"
    notes: "owner directive: do NOT invent Codex application code to fill this directory; spoke Spokes/Internal/Codex (ISPOKE-09) is the reusable capability, NOT a Product"

  - component_id: TOOL-BOUNDARY-LINT
    name: Architecture Boundary Lint
    tier: tools
    path: Tools/lint/architecture-boundary-lint.py
    namespace: null
    status: implemented
    entrypoints: [Tools/lint/architecture-boundary-lint.py]
    migration_action: move+rename
    migration_from: scripts/architecture-boundary-lint.py
    evidence: [{path: .github/workflows/architecture-boundary-lint.yml, symbol: "step: python3 scripts/..."}]
```

## D-5. CI validation rules (for a new `inventory-validate` job)

1. **Schema**: YAML parses; all required fields per status; ID/path/namespace uniqueness.
2. **Filesystem consistency**: every non-blueprint component's `path` exists; no component directory on disk under tier roots missing from inventory (two-way scan, excluding `vendor/`, protected dirs, archive).
3. **Composer consistency**: root composer.json PSR-4 prefixes exactly match `(namespace → path/src)` for every PHP component; no prefix maps to two paths; no per-component composer.json exists outside protected dirs (orchestrator exempt, recorded).
4. **Dependency direction**: tier rules (D-2/D-3) enforced over `dependencies`; topological sort succeeds (no cycles).
5. **Test ownership**: every implemented/partial/stub component has an existing `test_path` AND a `ci_runner` that includes it (this catches today's orphaned `app/tests` and neospx/view matrix drift).
6. **Frozen-contract refs**: every `contract_refs` anchor resolves in FROZEN-CONTRACTS.md; every CORE/HUB/BRIDGE/ISPOKE/ESPOKE implemented component has ≥1 contract ref or an explicit `contracts: none` note.
7. **Generated-artifact freshness**: INDEX tier tables, DAG summaries, and build orders regenerated from the inventory must match the committed files (hash comparison), else "stale generated artifact" failure — this directly prevents the current S-059/S-061 drift class.
8. **Retired IDs**: no component references a retired ID.
9. **Protected paths**: `Architecture/`, `orchestrator/`, `anvil/`, `archive/` never appear as a `path` for migration actions other than `retain`.
10. **Grandfathered namespace exceptions**: a new top-level field `grandfathered_exceptions` lists `SovereignStack\` namespaces retained inside protected dirs (e.g., `SovereignStack\Orchestrator\*` in `orchestrator/src/`). Each entry must reference the protected-dir path and the decision ID that justifies retention (e.g., `D-H2` for the orchestrator release-model workstream). The CI rule enforces: NO `SovereignStack\` references exist OUTSIDE the listed exception paths; the migration does NOT introduce new `SovereignStack\` references inside protected dirs (only existing references are grandfathered). This reconciles I-2's zero-`SovereignStack\` rule with I-9's protected-dir physical-retention rule.

## D-6. What stays OUT of the inventory

- Contract freezing status & change history → FROZEN-CONTRACTS.md (+ ADR gates).
- Architectural decisions & rationale → ADRs / OPEN-DECISIONS.md.
- Implementation-reality dependency edges (auto-derived from imports) → VERIFIED-DAG (generated, compared against inventory `dependencies` to surface undeclared edges — the two-DAG model of ADR-021).
- Blueprint responsibilities prose, threat models, SDLC process → respective governance docs.
- Third-party Composer package dependencies → root composer.json (inventory records only inter-component edges).

**Governance note (D-H9):** establishing this inventory does not repair INDEX.md's existing drift (Hub/ESPOKE counts, HUB-31/32 table omissions, ADR-011 status contradiction). The inventory should be **generated initially FROM the verified file evidence** (Deliverable B), which will institutionalize the corrected counts (32 Hub files, 19 ESPOKE files, 104 blueprints) — and INDEX/DAG repair is proposed as a bounded governance task either inside Stage 1 or deferred.

*Deliverable D complete.*
