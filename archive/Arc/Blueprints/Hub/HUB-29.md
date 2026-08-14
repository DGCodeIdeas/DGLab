# PHASE HUB-29: Hub-level Testing Harness

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10) and a concrete mock-fidelity verification mechanism
instead of an asserted-only "mocks behave identically" claim.

## Component Name
Sovereign Hub Spec (Testing)

## Description
Specialized testing harness extending `CORE-20` with integration/E2E tools for Hub services, including
mock drivers for every Hub component so Spokes can test in isolation.

## Build Status
🔴 **Blocked** on `HUB-15` (Health Check) and `HUB-16` (Orchestration Hooks) — neither implemented.
Note: as a testing tool, this can and should be built incrementally alongside each Hub component it
mocks, rather than waiting for the full tier — its `ServiceMocker` for `HUB-02`/`HUB-03` can exist as
soon as those two land, without waiting on `HUB-15`/`HUB-16`.

## Dependency Status
- **Direct Hub:** `HUB-15`, `HUB-16`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-20`, `CORE-08`.

## Architectural Design
- **ServiceMocker** — swaps real Hub services for fast in-memory mocks during tests.
- **AuthSimulator** — act as specific Users/Tenants without hitting real `HUB-04`/`HUB-21`.
- **ContractValidator** — ensures Hub service changes don't break defined Interface Contracts.
- **DuskBridge** — optional pure-PHP browser automation for E2E Spoke UI testing.

```php
namespace SovereignStack\Hub\Contracts;

interface HubTestHarnessInterface
{
    public function mockService(string $service, object $mock): void;
    public function actingAs(User $user, array $scopes = []): self;
}
```

## Mock Fidelity Contract (new)
"Mocks must behave identically to real services in terms of Interface compliance" is now a checked
property, not an assertion: `ContractValidator` runs the **same** test suite used to verify a real
service's interface compliance against its corresponding mock, on every CI run for this package. A
mock that passes a different (looser) test suite than its real counterpart is exactly the failure mode
this contract prevents.

## Integration Strategy
- **Upward:** complements `CORE-20`.
- **Downward:** every Spoke application uses this to write reliable integration tests against the Hub.
- **Contract:** mocks implement the same interfaces as real services.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Isolation | Integration test asserting the standard test suite runs with no database or Redis connection available in the test environment (enforced by literally removing network access to those services in the CI job, not just "not using" them). |
| Speed | State environment before citing "< 5 seconds for 100 tests" — measure on the actual reference CI runner (Finding 10). |
| Mock fidelity | The `ContractValidator` shared-suite mechanism above, run for every mocked service, blocking if any mock's behavior diverges from its real counterpart's test results. |

## CI Verification Criteria
- Network-isolated test-suite run, blocking.
- Mock-fidelity shared-suite check (above), blocking — this is the test that makes "mocks behave
  identically" enforced rather than hoped for.
- Speed measured and reported with environment stated.

## SemVer Impact
**Minor.** Crucial for the stability and maintainability of the entire stack.
