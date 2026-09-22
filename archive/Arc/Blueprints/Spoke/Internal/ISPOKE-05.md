# PHASE ISPOKE-05: Internal Reporting and Analytics Dashboard

## Tier
Internal Spoke (Staff-only Application)

## Resolves
Corrects two of this delivery's cataloged mislabel patterns (`01_MASTER_INDEX.md` §3): the original
cited `HUB-28: Distributed Ledger & Analytics Engine` (Pattern B — no such Hub blueprint exists; real
`HUB-28` is API Versioning) and `CORE-09: Cryptography & Hashing` (Pattern A — real `CORE-09` is PSR-3
Logging; crypto is `CORE-16`, which this Spoke has no actual need of and the reference is dropped
rather than redirected).

## Component Name
Sovereign Insight

## Description
Centralized reporting engine and visualization dashboard: business intelligence, system performance
metrics, and operational reports for data-driven decisions.

## Build Status
🔴 **Blocked** on `HUB-31` (pending — see below), `HUB-24`, `HUB-26`, `HUB-08` — none implemented, and
`HUB-31` isn't even specified yet.

## Dependency Status — corrected
- **Direct Hub:** ~~`HUB-28: Distributed Ledger & Analytics Engine`~~ → **`HUB-31` (pending — Real-Time
  Analytics & Metrics Ledger, registered in `01_MASTER_INDEX.md` §4; not yet specified)**, `HUB-24`,
  `HUB-26`, `HUB-08`, `HUB-15`, `HUB-16`, `HUB-02`.
- **Transitive Core:** `CORE-19`, `CORE-18`, `CORE-11`, `CORE-12`, `CORE-06`, `CORE-02`. ~~`CORE-09:
  Cryptography & Hashing`~~ — removed; this Spoke performs no cryptographic operations of its own, the
  reference was simply wrong, not a mis-pointed real need.

**This blueprint cannot be considered build-ready until `HUB-31` is specified** — its core feature set
(`QueryBuilder` against real-time analytics, `WidgetEngine`'s live KPIs) has no backing service to
build against. Treat everything below as a design sketch pending that follow-up work.

## Architectural Design
- **QueryBuilder** — constructs analytics queries against `HUB-31` (pending).
- **WidgetEngine** — visualization components (Charts, Tables, KPIs) via `HUB-26`.
- **ReportScheduler** — periodic report generation/distribution via `HUB-10` (Queue — corrected from
  the original's unlabeled "HUB-10" reference in the report-scheduler diagram, which happened to be
  right by coincidence rather than by the stated dependency list, which omitted it).
- **ExportService** — high-volume exports via `CORE-14`.

```php
namespace SovereignStack\Internal\Insight\Contracts;

interface AnalyticsQueryInterface
{
    public function setTimeRange(\DateTimeInterface $start, \DateTimeInterface $end): self;
    public function groupBy(string $dimension): self;
    public function execute(): array;
}
```

## Integration Strategy
- **Bootstrapping:** via `CORE-18`; discovers analytics endpoints via `HUB-15`.
- **Data Access:** through `HUB-08` (Gateway) or direct `HUB-31` calls once it exists.
- **Lifecycle:** `HUB-16` hooks pause high-load reporting during maintenance.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Query performance | Cannot be meaningfully specified until `HUB-31` exists — state this explicitly rather than restating "< 200ms on 1M rows" against a backend that doesn't exist (Finding 10 applies doubly here). |
| UI namespace compliance | Static scan asserting 100% of chart components originate from `HUB-26` — this criterion doesn't depend on `HUB-31` and can be verified today once `HUB-26` exists. |
| Cross-tenant isolation | Integration test (once `HUB-31` exists): a tenant-scoped report must never contain another tenant's data — same severity class as `HUB-21`'s and `BRIDGE-01`'s isolation tests. |

## CI Verification Criteria
- UI namespace compliance test, blocking, buildable independent of `HUB-31`.
- Cross-tenant isolation test, blocking once `HUB-31` exists — do not ship this Spoke without it.
- Query performance: no target stated until `HUB-31` is specified and a real benchmark can be run.

## SemVer Impact
**Minor**, pending `HUB-31`'s existence — this blueprint's SemVer is meaningless until its core
dependency is real.
