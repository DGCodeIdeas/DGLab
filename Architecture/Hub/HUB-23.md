# PHASE HUB-23: Data Export & Reporting Service

## Tier
Hub (Shared Services)

## Resolves
The original blueprint's `ReportScheduler` design note said it "hooks into HUB-25 (to be defined)" —
`HUB-25` is defined (`HUB-25.md`, Sovereign Chronos, the Background Scheduler). That forward reference
was simply never updated once `HUB-25` was actually written; this is the same class of stale-reference
bug as `00_CRITIQUE.md` Finding 3, found independently while rewriting this tier. Fixed below, and
`HUB-25` is now added to this blueprint's formal dependency list, where it had been omitted.

## Component Name
Sovereign Reporter

## Description
Generates large-scale data exports (CSV, Excel, PDF) and scheduled reports: extracts from `CORE-19`,
generates in the background via `HUB-10`, delivers via `HUB-12`/`HUB-11`.

## Build Status
🔴 **Blocked** on `HUB-11` (Storage), `HUB-10` (Queue), `HUB-12` (Notify) — none implemented.

## Dependency Status — corrected
- **Direct Hub:** `HUB-11`, `HUB-10`, `HUB-12`, and **`HUB-25`** (Scheduler — added; was referenced in
  prose as "to be defined" but omitted from the formal list even after `HUB-25` was written).
- **Transitive Core:** `CORE-19`, `CORE-14`.

## Architectural Design
- **ExportCoordinator** — orchestrates the export lifecycle.
- **DataStreamer** — iterates large datasets from the DBAL via PHP generators (flat memory profile).
- **FormatWriter** — CSV / Excel (OpenXML) writing logic.
- **ReportScheduler** — recurring reports, now concretely wired to `HUB-25`'s `SchedulerInterface`:
  `$schedule->job(new GenerateReportJob($reportId))->weekly()`, not a bespoke scheduling mechanism.

```php
namespace SovereignStack\Hub\Contracts;

interface ReporterInterface
{
    public function queueExport(string $query, string $format, array $options = []): string;
    public function getExportStatus(string $exportId): array;
}
```

## Integration Strategy
- **Upward:** built on `HUB-10`.
- **Downward:** Spoke applications provide "Export Blueprints" (SQL queries + headers).
- **Contract:** notifies via `HUB-12` once the file is ready in `HUB-11`; recurring reports are
  registered as `HUB-25` scheduled jobs, not a separate cron mechanism.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Memory-bounded large exports | Integration test streaming a real 100,000-row fixture table to CSV; assert peak memory via `memory_get_peak_usage()` stays under the stated bound — measured, not assumed from "uses generators." |
| CSV format integrity | Round-trip test: generate a CSV containing values with embedded commas, quotes, and newlines; re-parse it with a standard CSV parser and assert exact field recovery. |
| Expiration | Integration test: create an export file, fast-forward the fixture clock past 24 hours, assert `HUB-11` no longer serves it (verifies an actual TTL/cleanup mechanism exists, not just a documented intention). |

## CI Verification Criteria
- Memory-bound test with measured peak, blocking.
- CSV round-trip integrity test with adversarial field content, blocking.
- Expiration test against a real (or fixture-clock) TTL mechanism, blocking.
- `ReportScheduler` registration verified against `HUB-25`'s actual `SchedulerInterface`, not a
  bespoke scheduling shim.

## SemVer Impact
**Minor.** Adds reporting and data mobility features.
