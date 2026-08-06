# PHASE ISPOKE-21: Sovereign Scan (Vulnerability)

## Tier
Internal Spoke (Staff-only — VPN/bastion)

## Component Name
Sovereign Scan — `SovereignStack\Internal\Scan`. Automated vulnerability scanning for internal services
and dependencies: CVE-database integration, severity scoring, remediation workflow, patch tracking.

> **Naming note.** The placeholder source named this "Sovereign Sentinel (Vulnerability)"; renamed to
> **Sovereign Scan** to avoid colliding with `HUB-27` (Sovereign Sentinel — Headers). Display word
> "Sentinel" is reserved for the Hub tier where it already exists.

## Description
ISPOKE-21 scans deployed services and their dependency manifests (composer.lock, container images) for
known CVEs, scores severity, and drives a remediation workflow (ticket → patch → re-scan). It consumes
SBOM/dependency data and correlates against an internal CVE mirror; it does not perform penetration
testing (that is ISPOKE-15 SOC's red-team tooling).

## Build Status
✅ **Documented — ready for implementation.**

## Dependency Status
- **Upward:** HUB-04 (Sovereign Identity — scanner authn), HUB-19 (Sovereign Guard — input validation on
  scan targets), HUB-06 (Sovereign Auditor — findings recorded), HUB-15 (Sovereign Pulse — target health),
  ISPOKE-15 (Sovereign SOC — hands off critical findings), CORE-19 (Database — findings store), HUB-21
  (Sovereign Nexus — tenancy scoping).
- **Downward:** ISPOKE-01 (UI shell), ISPOKE-25 (Sovereign Responder — consumes critical findings).

## Architectural Design

| Class | Kind | Responsibility |
|---|---|---|
| `ScanTarget` | `final readonly class` | `tenant_id`, `kind` (`composer`\|`image`\|`endpoint`), `ref`. |
| `ScannerInterface` | interface | `scan(ScanTarget $t): ScanReport`, `remediate(string $findingId, string $patchRef): void`. |
| `CveCorrelator` | class | Joins dependency graph to the CVE mirror; emits `Finding` list. |
| `SeverityScorer` | class | CVSS-based scoring → `critical`\|`high`\|`medium`\|`low`. |

```php
<?php
declare(strict_types=1);
namespace SovereignStack\Internal\Scan;

interface ScannerInterface
{
    public function scan(ScanTarget $target): ScanReport;
    public function remediate(string $findingId, string $patchRef): void;
}
```

## Data Model (MySQL 16)

```sql
CREATE TABLE scan_findings (
    id           ULID PRIMARY KEY DEFAULT ulid_generate(),
    tenant_id    ULID NOT NULL REFERENCES tenants(id),
    target_ref   text NOT NULL,
    cve          text NOT NULL,
    severity     text NOT NULL CHECK (severity IN ('critical','high','medium','low')),
    status       text NOT NULL DEFAULT 'open' CHECK (status IN ('open','patched','accepted','wont_fix')),
    created_at   timestamptz NOT NULL DEFAULT now()
);
```

## Integration Strategy
**Upward:** resolves HUB-04/HUB-19/HUB-06/HUB-15/ISPOKE-15/CORE-19/HUB-21 through the container
(CORE-02). **Downward:** UI in ISPOKE-01; critical findings handed to ISPOKE-25.

## Security Properties
1. Scan targets are validated (HUB-19) — no arbitrary command or URL injection via `ref`.
2. Findings are immutable audit rows (HUB-06); "accepted"/"wont_fix" requires a recorded justification.
3. Critical findings escalate to ISPOKE-15/ISPOKE-25 through the event bus (HUB-09).
4. Scans are tenancy-scoped (HUB-21); one tenant cannot enumerate another's dependencies.

## CI Verification Criteria
- Unit: `SeverityScorer` maps a CVSS 9.8 finding to `critical`; `CveCorrelator` returns exactly the
  expected `Finding` set for a seeded dependency graph.
- Integration (MySQL 16): `scan()` writes `scan_findings`; `remediate()` flips status to `patched`.
- Static: phpstan `level: max` clean; ≥95% branch coverage on `CveCorrelator`.
