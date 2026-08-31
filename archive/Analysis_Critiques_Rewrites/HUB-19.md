# PHASE HUB-19: Centralised Validation & Sanitisation Library

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10) and a concrete, testable XSS-blocking fixture set
instead of an unspecified "standard payloads" claim.

## Component Name
Sovereign Guard (Validation)

## Description
Centralized validation/sanitization: consistent data-integrity rules across Hub and Spoke services,
complex rule-sets, recursive validation, automatic HTML sanitization against XSS.

## Build Status
🔴 **Blocked** on `HUB-13` (I18n — for translated error messages), `CORE-02` (DI Container),
`CORE-10` (Config) — none implemented.

## Dependency Status
- **Direct Hub:** `HUB-13`. *(Matches taxonomy.)*
- **Transitive Core:** `CORE-02`, `CORE-10`.
- **Downward:** `HUB-17` (webhook payload validation), every Spoke form.

## Architectural Design
- **ValidationEngine** — evaluates rules against data.
- **RuleRegistry** — reusable rules (`Email`, `MinLength`, `Unique`, …).
- **SanitizationEngine** — input filters/transforms (`StripTags`, `CastToInteger`, …).
- **ValidatorFactory** — creates validators with injected dependencies (e.g., DB for `unique` checks).

```php
$rules = [
    'email' => 'required|email|unique:users,email',
    'bio' => 'string|max:500|sanitize_html',
];
```

```php
namespace SovereignStack\Hub\Contracts;

interface GuardInterface
{
    public function validate(array $data, array $rules): array;
    public function sanitize(mixed $data, string|array $filters): mixed;
}
```

## Integration Strategy
- **Upward:** uses `HUB-13` for translated error messages.
- **Downward:** injected into Spoke controllers and the `HUB-08` Gateway for request-body validation.
- **Contract:** throws `ValidationException` with a structured error map.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| XSS blocking | Test against the OWASP XSS filter evasion cheat-sheet payload set (a stated, versioned fixture list — not "standard payloads," which is undefined) checked into the test suite; assert every payload is neutralized. |
| DBAL integration for `unique` | Integration test against a real (fixture) `CORE-19` connection; assert the rule correctly rejects a duplicate and accepts a unique value, including a case-sensitivity edge case if the underlying column collation is case-insensitive. |
| Validation throughput | State environment before citing "< 1ms for 50 fields" — measure once `CORE-02`/`CORE-10` exist (Finding 10). |

## CI Verification Criteria
- OWASP fixture-list XSS test, blocking, with the fixture list versioned and checked in (so "passes
  XSS tests" is reproducible, not dependent on memory of what payloads were tried).
- `unique` rule integration test against a real DB connection, blocking.
- Throughput measured and reported with environment stated.

## SemVer Impact
**Minor.** Standardizes data integrity across the stack.
