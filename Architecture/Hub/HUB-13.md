# PHASE HUB-13: I18n & L10n Service

## Tier
Hub (Shared Services)

## Resolves
Adds stated benchmark methodology (Finding 10).

## Component Name
Sovereign Translator

## Description
Internationalization and localization service: translation management, number/date formatting,
pluralization. Centralizes language files at the Hub level while allowing Spoke-level overrides.

## Build Status
🔴 **Blocked** on `CORE-10` (Config) and `HUB-02` (Cache) — neither implemented.

## Dependency Status
- **Upward:** `CORE-10`, `HUB-02`. *(Matches taxonomy.)*
- **Downward:** `HUB-19` (Validation — translated error messages), `HUB-26` (UI Library).

## Architectural Design
- **Translator** — main `trans()` retrieval service.
- **Loader** — loads translation files (PHP arrays/JSON) from Hub + Spoke directories.
- **Formatter** — placeholder replacement, locale-aware number/date formatting.
- **Pluralizer** — rule-based pluralization (e.g., Arabic's 6 plural forms).

```php
// resources/lang/en/messages.php
return [
    'welcome' => 'Welcome, :name!',
    'items' => '{0} No items|{1} One item|[2,*] :count items',
];
```

```php
namespace SovereignStack\Hub\Contracts;

interface TranslatorInterface
{
    public function get(string $key, array $replace = [], ?string $locale = null): string;
    public function getLocale(): string;
    public function setLocale(string $locale): void;
}
```

## Integration Strategy
- **Upward:** integrated into `CORE-18` Kernel to detect locale from request headers or `HUB-04`
  session.
- **Downward:** Spoke applications use `trans()` or `@lang`.
- **Persistence:** locale stored in session or cookie.

## Benchmark & Verification Methodology
| Target | Method |
|---|---|
| Fallback chain correctness | Unit test: request a key present only in `fr` while locale is `fr-CA`; assert fallback to `fr`, then to the default locale if still missing, in that documented order — not just "eventually finds something." |
| Hot-cache retrieval latency | State environment before citing "< 0.01ms" — measure once `HUB-02` exists; currently a target, not a result (Finding 10). |
| UTF-8 / complex-script integrity | Test fixture including Arabic (RTL, pluralization edge case), CJK, and emoji strings round-tripped through `Formatter`; assert byte-for-byte fidelity, not just "renders without erroring." |

## CI Verification Criteria
- Fallback-chain test with the exact documented order, blocking.
- UTF-8/complex-script fixture test, blocking.
- Retrieval latency measured and reported with environment stated.

## SemVer Impact
**Minor.** Enables global availability of the stack.
