# PHASE HUB-29: Hub-level Testing Harness

## Tier
Hub (Shared Services)

## Component Name
Sovereign Hub Spec (Testing)

## Description
A specialized testing harness that extends `CORE-20` to provide integration and E2E testing tools for Hub services. It includes mock drivers for all Hub components (Auth, Storage, Queue) to allow Spokes to test in isolation.

## Sequencing Rationale
Provides the testing infrastructure for all Hub phases and prepares for Spoke validation.

## Context7 Research
- **Direct Hub Dependencies**: `HUB-15: Health Check`, `HUB-16: Orchestration Hooks`.
- **Transitive Core Dependencies**: `CORE-20: Developer CLI Toolchain`, `CORE-08: Error Handler`.
- **Patterns**: Mock Object, Test Double, Contract Testing.

## Architectural Design
- **ServiceMocker**: Swaps real Hub services for fast, in-memory mocks during tests.
- **AuthSimulator**: Provides helpers for acting as specific Users or Tenants without hitting the real `HUB-04` database.
- **ContractValidator**: Ensures that Hub service changes do not break the defined Interface Contracts.
- **DuskBridge**: (Optional) Pure PHP browser automation integration for E2E testing Spoke UIs.

## Interface Contracts

### HubTestHarnessInterface
```php
namespace Sovereign\Hub\Contracts;

interface HubTestHarnessInterface
{
    /**
     * Mock a Hub service for the duration of the test.
     */
    public function mockService(string $service, object $mock): void;

    /**
     * Set the current authenticated context for the test.
     */
    public function actingAs(User $user, array $scopes = []): self;
}
```

## Integration Strategy
- **Upward**: Complements the `CORE-20` Forge toolchain.
- **Downward**: Used by every Spoke application to write reliable integration tests against the Hub.
- **Contract**: Mocks must implement the same Interfaces as the real services.

## CI Verification Criteria
- **Isolation**: Tests using the Hub Spec must not require a running database or Redis unless explicitly requested.
- **Speed**: Running 100 basic integration tests must take < 5 seconds.
- **Reliability**: Mocks must behave identically to real services in terms of Interface compliance.

## SemVer Impact
**Minor**. Crucial for the stability and maintainability of the entire stack.
