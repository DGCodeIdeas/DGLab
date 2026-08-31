# PHASE HUB-15: Health Check & Service Discovery

## Tier
Hub

## Component Name
Sovereign Pulse (Health)

## Description
A monitoring and service discovery registry. It provides a centralized dashboard and API for verifying the health of all Hub services and Spoke applications. It checks database connectivity, disk space, external API availability, and memory usage.

## Context7 Research
- **Depends on**: `CORE-10: Config`, `CORE-14: Filesystem`, `HUB-02: Cache`.
- **Standards**: Health Check Response Format (draft-in-ietf-appsawg-service-health-check).
- **Patterns**: Registry, Pulse/Heartbeat.

## Architectural Design
- **HealthManager**: Orchestrates health checks across the stack.
- **CheckInterface**: Contract for individual diagnostic tasks (e.g., `DatabaseCheck`, `RedisCheck`).
- **ServiceRegistry**: A directory of active Hub/Spoke endpoints and their current status.
- **PulseEndpoint**: A secure `/health` route that returns the system status in JSON.

### Health Check Example
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

## Interface Contracts

### HealthRegistryInterface
```php
namespace Sovereign\Hub\Contracts;

interface HealthRegistryInterface
{
    /**
     * Register a new health check.
     */
    public function register(string $name, CheckInterface $check): void;

    /**
     * Run all registered checks and return the status.
     */
    public function status(): array;

    /**
     * Report the status of a specific service.
     */
    public function heartbeat(string $service, string $status): void;
}
```

## Integration Strategy
- **Upward**: Built on the `CORE-13` CLI and `CORE-18` Kernel.
- **Downward**: Every Spoke application reports its health to the Hub registry via a scheduled job (`HUB-10`).
- **Monitoring**: Integrates with external tools (e.g., Render metrics) by providing a standardized health endpoint.

## CI Verification Criteria
- **Failing Fast**: A critical failure (e.g., DB down) must return a `503 Service Unavailable` status on the health endpoint.
- **Overhead**: A health check execution must not consume more than 5% of CPU or take longer than 500ms.
- **Reporting Accuracy**: The registry must correctly identify "Stale" services if a heartbeat hasn't been received for 5 minutes.

## SemVer Impact
**Minor**. Essential for production observability and reliability.
