# PHASE ISPOKE-03: System Health and Observability Dashboard

## Tier
Internal Spoke (Staff-only Application)

## Resolves
Cross-references checked against `01_MASTER_INDEX.md` §3 — clean, no correction needed. Adds stated
benchmark methodology (Finding 10).

## Component Name
Sovereign Pulse Dashboard

## Description
Real-time observability/health monitoring dashboard aggregating `HUB-15` (Pulse), `HUB-06` (Audit),
and `CORE-08` (Error Handler) into a unified view of stack performance and stability.

## Build Status
🔴 **Blocked** on `HUB-15`, `HUB-06`, `HUB-02`, `HUB-09` — none implemented.

## Dependency Status
- **Direct Hub:** `HUB-15`, `HUB-06`, `HUB-02` (real-time metrics), `HUB-09` (Event Bus, live alerts),
  `HUB-26`, `HUB-16`. *(Verified — correct, including `CORE-09` correctly identified in the original
  as "Logger," matching the real PSR-3 Logging Service.)*
- **Transitive Core:** `CORE-08`, `CORE-09`, `CORE-19`, `CORE-10`.

## Architectural Design
- **PulseWall** — grid of health tiles per Hub/Spoke service.
- **ErrorStream** — real-time feed of exceptions/fatal errors.
- **MetricCharts** — memory/response-time visualization via `HUB-26`.
- **IncidentManager** — tracks/documents system-wide incidents.

## Integration Strategy
- **Bootstrapping:** subscribes to `HUB-09` for real-time health alerts.
- **UI Rendering:** `HUB-26` dashboard/data-viz components.
- **Data Source:** `HUB-15` registry and `CORE-09` log storage.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Real-time propagation | Integration test: flip a fixture service's `HUB-15` status; measure and report actual wall-clock time to dashboard update, on a stated environment — don't restate "within 2 seconds" unmeasured (Finding 10). |
| Alert accuracy | Integration test: log a `CORE-08` critical error, assert a corresponding visual alert renders — checked via DOM/state assertion, not just "an event fired." |
| Aggregation performance | Benchmark aggregating a realistic 24h/10-service fixture dataset; report actual time, state environment. |

## CI Verification Criteria
- Alert-accuracy test (above), blocking.
- Real-time propagation and aggregation performance measured and reported with environment stated.

## SemVer Impact
**Minor.** Essential for production operations and SRE.
