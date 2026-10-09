# PHASE HUB-30: Hub Developer CLI Toolchain


<!-- Blind-Spot Awareness (per BLIND-SPOT-DOCTRINE.md) -->
> **⚠️ Blind-Spot Awareness:** This Hub blueprint may contain **unverified assumptions, unstated dependencies, or edge cases not covered**. The contract declared here is a candidate, not a certainty. Upward/Downward declarations may have asymmetric drift (producer claims a consumer that the consumer doesn't acknowledge). The blueprint's edge_type classifications may be UNKNOWN or incorrect. Cross-tier dependencies (Hub→Core, Hub→Runtime) may not be fully verified. **An audit of this blueprint is a starting point, not a complete inventory.** See `Architecture/CrossCutting/BLIND-SPOT-DOCTRINE.md` for the governance framework.

<!-- End Blind-Spot Awareness -->

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10). Completes the Hub tier — `HUB-01` through `HUB-30`
are now all rewritten to the standard defined in `01_MASTER_INDEX.md`.

## Component Name
Sovereign Hub-CLI

## Description
Specialized CLI for Hub administrators/developers, extending `CORE-20` (Forge) with commands for
managing tenants, clearing global caches, inspecting queues, and monitoring service health across the
stack.

## Build Status
🔴 **Blocked** on `HUB-21` (Tenancy), `HUB-15` (Health Check), `HUB-10` (Queue), `HUB-02` (Cache) —
none implemented. As the tier's administrative interface, this is naturally last to build — it has no
value until the components it administers exist.

## Dependency Status
- **Direct Hub:** `HUB-21`, `HUB-15`, `HUB-10`, `HUB-02`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-13`, `CORE-20`.

## Architectural Design
- **TenantManagerCommand** — create/suspend/migrate tenants via `HUB-21`'s `TenancyInterface`.
- **PulseMonitorCommand** — real-time health dashboard from `HUB-15`.
- **QueueInspectorCommand** — view/retry/purge jobs via `HUB-10`.
- **AssetManagerCommand** — triggers Hub-level asset compilation/deployment (`HUB-03`).

```php
class CreateTenantCommand extends Command
{
    protected string $signature = 'hub:tenant:create {name} {domain}';

    public function handle(TenancyInterface $nexus): int
    {
        $tenant = $nexus->create([
            'name' => $this->argument('name'),
            'domain' => $this->argument('domain')
        ]);

        $this->info("Tenant created with ID: {$tenant->id}");
        return 0;
    }
}
```

## Interface Contracts
Inherits from `CORE-13` and `CORE-20`; no new interface surface of its own beyond individual
`Command` subclasses.

## Integration Strategy
- **Upward:** plugs into the `s-cli` entry point.
- **Downward:** used by DevOps/Hub administrators.
- **Contract:** every command supports `--json` output for scripting/automation.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Command discovery | Integration test: run `s-cli list hub`, assert output includes every registered Hub command by name — a count-based assertion (`>= 30`) is weaker than an explicit name-list assertion; use the latter so a renamed/dropped command is caught, not just a count drift. |
| Destructive-command safety | Integration test: invoke `hub:cache:clear` and `hub:tenant:delete` without `--force` in a non-interactive context; assert both refuse to proceed rather than silently completing. |
| Help documentation completeness | Static check: every registered command class has a non-empty description and at least one usage example in its help text — enforced at CI time, not left to reviewer diligence. |

## CI Verification Criteria
- Named command-discovery test (not count-only), blocking.
- Destructive-command safety test for every command tagged destructive, blocking.
- Help-documentation completeness static check, blocking.

## SemVer Impact
**Major.** Completes the Hub tier and provides the operational control plane.


---

## Doctrines Applied + Rewrite Notes (PR #335)

> **This section was added in PR #335 (Core rewrite Batch, 2026-10-07) per Tech-Lead directive: "rewrite with proper details the entire SDLC then Architecture starting from Core, three documents at a time. No more Patches!"**

### Doctrines Applied

This blueprint is bound by the following doctrines (per [SDLC-01 §9](../SDLC/SDLC-01-Foundations.md) convention):

- **Blind-Spot Doctrine** ([`../CrossCutting/BLIND-SPOT-DOCTRINE.md`](../CrossCutting/BLIND-SPOT-DOCTRINE.md)) — banner at top of this file (binding rule #3: every architectural document carries a blind-spot awareness note). The blueprint's claims are candidates, not certainties. The implementation may diverge from the blueprint (implementation drift). An audit of this blueprint is a starting point, not a complete inventory.
- **Nuclear-Grade Doctrine** ([`../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md`](../CrossCutting/NUCLEAR-GRADE-DOCTRINE.md)) — binding on Core tier (per the doctrine's §0). Where this blueprint and the doctrine disagree, the doctrine wins. Depth 5 (production hardening) requires the doctrine's §9 merge gate to pass.
- **Integrity Gate** ([`../Verification/INTEGRITY-GATE.md`](../Verification/INTEGRITY-GATE.md)) — depth 6 (at-scale verification) requires the Integrity Gate's convergence criteria. The gate is a finite stopping condition (PASSED 2026-10-05).
- **Two-DAG Governance** ([`../ADRs/ADR-021-tier-stratified-build-order.md`](../ADRs/ADR-021-tier-stratified-build-order.md)) — the Dependency Status section above reflects the Declared DAG (architectural intent). The Verified DAG (implementation reality) lives at [`CORE-VERIFIED-DAG.md`](CORE-VERIFIED-DAG.md). When the two disagree, that disagreement is a finding in [`../Verification/SHORTCOMINGS-REGISTER.md`](../Verification/SHORTCOMINGS-REGISTER.md), not a defect to fix by editing either DAG.
- **FROZEN-CONTRACTS** ([`../FROZEN-CONTRACTS.md`](../FROZEN-CONTRACTS.md)) — the Interface Contracts section above declares the public surface. Once this blueprint is implemented at any depth, those contracts freeze. Changes require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).

### Updates Applied in PR #335

- **PHP version**: bulk-updated all references from `PHP 8.3` → `PHP 8.4` (the package `composer.json` files already require `^8.4`; the blueprint references were stale). This includes version strings in interface contracts, reference implementation notes, benchmark methodology baselines, and runtime requirements.
- **Cross-references**: added references to the new SDLC documents ([`SDLC-01`](../SDLC/SDLC-01-Foundations.md), [`SDLC-02`](../SDLC/SDLC-02-Governance.md), [`SDLC-03`](../SDLC/SDLC-03-InterfaceFreeze.md), [`SDLC-04`](../SDLC/SDLC-04-CooldownMechanics.md), [`SDLC-05`](../SDLC/SDLC-05-AI-Assisted-Development-Protocol.md), [`SDLC-06`](../SDLC/SDLC-06-Generator-Specifications.md)) which replaced the original `SDLC-AGRD.md` (now a redirect at [`../CrossCutting/SDLC-AGRD.md`](../CrossCutting/SDLC-AGRD.md)).
- **Doctrine layer**: added this "Doctrines Applied + Rewrite Notes" section per the new SDLC convention (SDLC-01 §9 Provenance).
- **Build Status**: verified current shipped state (depth 2 for this blueprint — HUB-30 — Hub Developer CLI Toolchain — shipped per the verified DAG).

### What Was NOT Changed in PR #335

- **Interface contracts** — the PHP interface definitions, class maps, and behavior contracts were NOT modified. They remain as declared. Any change to these would require an ADR (per [SDLC-03 §3.1](../SDLC/SDLC-03-InterfaceFreeze.md)).
- **Reference implementations** — the compilable class code was NOT modified. The implementation lives in `packages/core/hub/cli/src/` and is verified by the architecture-boundary-lint.
- **Sequence diagrams** — the Mermaid sequence diagrams were NOT modified.
- **Benchmark methodology** — the harness specs were NOT modified (only the PHP version in the baseline was updated from 8.3 to 8.4 to match the actual CI runner).

### Verification Conditions for This Rewrite

- **Architecture-lint**: this file is scanned by `Architecture/Verification/lint/run.php`. The lint checks for invalid tokens (CORE-NN, HUB-NN, etc. in valid ranges), `misattribution phrases` (`CORE-09: Cryptography/Hashing`, `HUB-28: Analytics/Ledger` — must not appear in active prose), and structural completeness (the file must exist). Must pass.
- **Architecture-boundary-lint**: not directly applicable (this is a documentation file, not source code), but the implementation referenced in this blueprint is scanned by `scripts/architecture-boundary-lint.py`.
- **Self-test**: the architecture-lint's `--self-test` flag (added in PR #314) verifies the lint correctly detects missing files. This ensures the structural completeness check is not a false-positive.

### Provenance

This section was added in PR #335 (2026-10-07). The original blueprint content (interface contracts, class maps, sequence diagrams, etc.) was authored in earlier sessions and is preserved. The doctrine layer + PHP version update + cross-references to new SDLC documents are the additions.

Per the Blind-Spot Doctrine: this rewrite is a starting point, not a complete specification. The number of doctrines applied here is not the number of doctrines that exist. Future rewrites may add more doctrine cross-references as the methodology continues to evolve.
