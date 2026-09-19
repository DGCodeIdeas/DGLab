# PHASE HUB-13: I18n & L10n Service

## Tier
Hub

## Component Name
Sovereign Translator

## Description
A comprehensive internationalization (i18n) and localization (l10n) service. It provides translation management, number/date formatting, and pluralization support. It centralizes language files at the Hub level while allowing Spokes to define their own local overrides.

## Context7 Research
- **Depends on**: `CORE-10: Config`, `HUB-02: Cache`.
- **Standards**: BCP 47 (Locale tags), CLDR (Common Locale Data Repository) for formatting patterns.
- **Reference**: Evaluates `symfony/translation` but recommends a lightweight sovereign implementation focused on fast array-based lookups and PHP 8.3 features.

## Architectural Design
- **Translator**: The main service for retrieving translated strings (`trans()`).
- **Loader**: Loads translation files (PHP arrays or JSON) from multiple directories (Hub + Spoke).
- **Formatter**: Handles dynamic replacement of placeholders and locale-aware number/date formatting.
- **Pluralizer**: Implements rule-based pluralization for different languages (e.g., Arabic has 6 plural forms).

### Translation File Example
```php
// resources/lang/en/messages.php
return [
    'welcome' => 'Welcome, :name!',
    'items' => '{0} No items|{1} One item|[2,*] :count items',
];
```

## Interface Contracts

### TranslatorInterface
```php
namespace Sovereign\Hub\Contracts;

interface TranslatorInterface
{
    /**
     * Translate the given message.
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string;

    /**
     * Get the current application locale.
     */
    public function getLocale(): string;

    /**
     * Set the current application locale.
     */
    public function setLocale(string $locale): void;
}
```

## Integration Strategy
- **Upward**: Integrated into the `CORE-18` Kernel to detect locale from request headers or session (`HUB-04`).
- **Downward**: Spoke applications use the `trans()` helper or `@lang` SuperPHP directive.
- **Persistence**: Locales are stored in the user's session or a cookie.

## CI Verification Criteria
- **Fallback Logic**: If a key is missing in `fr-CA`, it must fall back to `fr`, then to the default locale (e.g., `en`).
- **Performance**: Retrieving a simple string from a hot cache must take < 0.01ms.
- **UTF-8 Integrity**: Must verify correct rendering of complex scripts (Arabic, CJK, Emojis).

## SemVer Impact
**Minor**. Enables global availability of the stack.
