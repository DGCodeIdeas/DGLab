# PHASE HUB-15: Health Check & Service Discovery

## Tier
Hub (Shared Services)

## Resolves
Reconciles this blueprint's `HealthRegistryInterface`/`CheckInterface` with `02_EXEMPLARS/DEPLOY-01.md`'s
`HealthCheckInterface` (`liveness()`/`readiness()`), which was specified independently in this
delivery's Deploy-tier work. Both are legitimate and complementary, but nothing previously stated how
they relate — that omission is fixed below. Also adds stated benchmark methodology (Finding 10).

## Component Name
Sovereign Pulse (Health)

## Description
Monitoring and service-discovery registry: a centralized dashboard/API verifying the health of every
Hub service and Spoke application — database connectivity, disk space, external API availability,
memory usage.

## Build Status
🔴 **Blocked** on `CORE-10` (Config), `CORE-14` (Filesystem), `HUB-02` (Cache) — none implemented.
This is the component `HUB-08`'s circuit breakers, `BRIDGE-01`'s service discovery, and `DEPLOY-01`'s
routing-pool eviction all depend on — high-priority within the Hub tier.

## Dependency Status
- **Upward:** `CORE-10`, `CORE-14`, `HUB-02`. *(Matches taxonomy.)*
- **Downward:** `HUB-16` (Weaver — release gating), `HUB-08` (circuit-breaker/service registry),
  `BRIDGE-01` (endpoint discovery), `DEPLOY-01` (routing-pool eviction).

## Two-Layer Health Model (reconciles DEPLOY-01)
- **Per-instance layer (`DEPLOY-01`'s `HealthCheckInterface`):** every deployed service process
  implements `liveness()`/`readiness()` directly, at a standard `/healthz/*` path. This is what a load
  balancer or orchestrator polls per-instance, per the deployment topology in `DEPLOY-01.md`.
- **Registry/aggregation layer (this blueprint's `HealthRegistryInterface`):** `HUB-15` polls the
  per-instance `readiness()` endpoints across every registered service instance, aggregates them into
  the stack-wide dashboard, and is the thing `HUB-08`'s circuit breakers and `BRIDGE-01`'s service
  discovery actually query — they don't hit individual instances directly.

Concretely: a service's `readiness(): array{ready: bool, checks: array<string,bool>}` implementation
(from `DEPLOY-01`) is typically *built* using this blueprint's `CheckInterface` primitives (e.g., a
`DatabaseCheck` instance) — `CheckInterface` is the reusable diagnostic building block; `readiness()`
is the per-service aggregate that composes several `CheckInterface` results together.

## Architectural Design
- **HealthManager** — orchestrates checks across the stack.
- **CheckInterface** — contract for individual diagnostics (`DatabaseCheck`, `RedisCheck`, …).
- **ServiceRegistry** — directory of active Hub/Spoke endpoints and current status.
- **PulseEndpoint** — the aggregate `/health` route returning stack-wide JSON status.

```php
class DatabaseCheck implements CheckInterface
{
    public function check(): HealthResult
    {
        try {
            DB::connection()->getPdo();
            return HealthResult::ok('Connected');
        } catch (\Exception $e) {
            return HealthResult::fail('Disconnected: ' . $e->getMessage());
        }
    }
}
```

```php
namespace SovereignStack\Hub\Contracts;

interface HealthRegistryInterface
{
    public function register(string $name, CheckInterface $check): void;
    public function status(): array;
    public function heartbeat(string $service, string $status): void;
}
```

## Integration Strategy
- **Upward:** built on `CORE-13` and `CORE-18`.
- **Downward:** every Spoke reports health via a scheduled `HUB-10` job that polls its own
  `DEPLOY-01`-contract `readiness()` and forwards the result via `heartbeat()`.
- **Monitoring:** standardized health endpoint for external tools (e.g., Render metrics).

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Fail-fast on critical failure | Integration test: force a `DatabaseCheck` to fail; assert the aggregate `/health` endpoint returns `503`, not `200` with a buried failure flag. |
| Check overhead | State environment before citing "< 500ms / 5% CPU" — measure the actual check-suite execution time on a reference runner (Finding 10). |
| Staleness detection | Integration test: register a service, then let its heartbeat interval elapse past the staleness threshold without a new heartbeat; assert `status()` marks it "Stale," and assert `HUB-08`'s circuit breaker (per `HUB-08.md`) treats "Stale" the same as "Down" for routing purposes — this closes the gap where a hung-but-still-responding-to-TCP service could otherwise stay in the routing pool indefinitely. |

## CI Verification Criteria
- Fail-fast 503 test, blocking.
- Staleness-triggers-eviction test, blocking — verifies the actual link to `HUB-08`/`DEPLOY-01`, not
  just that `HUB-15` internally flags staleness.
- Check overhead measured and reported with environment stated.

## SemVer Impact
**Minor.** Essential for production observability and reliability.
