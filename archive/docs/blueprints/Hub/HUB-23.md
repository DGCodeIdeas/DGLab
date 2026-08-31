# PHASE HUB-23: Data Export & Reporting Service

## Tier
Hub (Shared Services)

## Component Name
Sovereign Reporter

## Description
A service for generating large-scale data exports (CSV, Excel, PDF) and scheduled reports. It coordinates the extraction of data from `CORE-19`, handles background generation via `HUB-10`, and manages delivery via `HUB-12` or `HUB-11`.

## Sequencing Rationale
Depends on `HUB-11` (Storage) for output files and `HUB-10` (Queue) for long-running tasks.

## Context7 Research
- **Direct Hub Dependencies**: `HUB-11: File Storage`, `HUB-10: Queue & Job Dispatcher`, `HUB-12: Notification Service`.
- **Transitive Core Dependencies**: `CORE-19: DBAL`, `CORE-14: Filesystem`.
- **Efficiency**: Uses PHP Generators to stream data from DB to file, maintaining a flat memory profile.

## Architectural Design
- **ExportCoordinator**: Orchestrates the export lifecycle.
- **DataStreamer**: Efficiently iterates over large datasets from the DBAL.
- **FormatWriter**: Encapsulates logic for writing CSV or Excel (OpenXML) formats.
- **ReportScheduler**: Hooks into `HUB-25` (to be defined) for recurring reports.

## Interface Contracts

### ReporterInterface
```php
namespace Sovereign\Hub\Contracts;

interface ReporterInterface
{
    /**
     * Queue an asynchronous export.
     */
    public function queueExport(string $query, string $format, array $options = []): string;

    /**
     * Get the status or download URL of a queued export.
     */
    public function getExportStatus(string $exportId): array;
}
```

## Integration Strategy
- **Upward**: Built on the `HUB-10` Queue system.
- **Downward**: Spoke applications provide "Export Blueprints" (SQL queries + headers) to the Reporter.
- **Contract**: Notifies the user via `HUB-12` once the file is ready in `HUB-11`.

## CI Verification Criteria
- **Memory Consistency**: Exporting 100,000 rows to CSV must not exceed 32MB of RAM.
- **Format Integrity**: Generated CSVs must be valid (correct quoting/escaping of special characters).
- **Expiration**: Export files in `HUB-11` must be automatically deleted after 24 hours.

## SemVer Impact
**Minor**. Adds reporting and data mobility features.
