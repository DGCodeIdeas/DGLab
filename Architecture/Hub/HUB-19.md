# PHASE HUB-19: Centralised Validation & Sanitisation Library

## Tier
Hub (Shared Services)

## Component Name
Sovereign Guard (Validation)

## Description
A centralized validation and sanitization engine that provides consistent data integrity rules across all Hub and Spoke services. It supports complex rule-sets, recursive validation, and automatic HTML sanitization to prevent XSS.

## Sequencing Rationale
Foundational for all data-entry points. Used by `HUB-17` (Webhooks) and all Spoke forms.

## Context7 Research
- **Direct Hub Dependencies**: `HUB-13: I18n` (for error messages).
- **Transitive Core Dependencies**: `CORE-02: DI Container`, `CORE-10: Config`.
- **Patterns**: Validator, Filter, Sanitizer.
- **Compliance**: OWASP XSS prevention guidelines.

## Architectural Design
- **ValidationEngine**: The core runner that evaluates rules against data.
- **RuleRegistry**: A collection of reusable validation rules (e.g., `Email`, `MinLength`, `Unique`).
- **SanitizationEngine**: Filters and transforms input (e.g., `StripTags`, `CastToInteger`).
- **ValidatorFactory**: Creates validator instances with injected dependencies (like DB for unique checks).

### Validation Rule Example
```php
$rules = [
    'email' => 'required|email|unique:users,email',
    'bio' => 'string|max:500|sanitize_html',
];
```

## Interface Contracts

### GuardInterface
```php
namespace Sovereign\Hub\Contracts;

interface GuardInterface
{
    /**
     * Validate data against a set of rules.
     */
    public function validate(array $data, array $rules): array;

    /**
     * Sanitize a single value or array.
     */
    public function sanitize(mixed $data, string|array $filters): mixed;
}
```

## Integration Strategy
- **Upward**: Uses `HUB-13` for translated error messages.
- **Downward**: Injected into Spoke controllers and `HUB-08` Gateway to validate incoming request bodies.
- **Contract**: Throws a `ValidationException` containing a structured map of error messages.

## CI Verification Criteria
- **Security**: Must successfully block standard XSS payloads in the sanitization phase.
- **DB Integration**: `unique` rule must correctly query the `CORE-19` DBAL.
- **Performance**: Validating an array of 50 fields must take < 1ms.

## SemVer Impact
**Minor**. Standardizes data integrity across the stack.
