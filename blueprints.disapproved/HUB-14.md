# HUB-14.md

## Phase ID

`HUB-14`

## Tier

`Hub`

## Component Name and Description

**Internationalization (i18n) Service** – Provides locale‑aware translation, number/date formatting, and message catalog management. Implements PSR‑7 request locale extraction and PSR‑11 registration of `TranslatorInterface`.

## Context7 Research

- **PHP Best Practices**: Use immutable message objects, lazy loading of catalogs, fallback locales.
- **PSR‑7**: Locale derived from `Accept-Language` header or request attribute.
- **PSR‑11**: Service container resolves `TranslatorInterface`.
- **Design Patterns**: Strategy for storage back‑ends (PHP array, JSON, DB), Decorator for pluralization, Singleton for catalog cache.
- **Performance**: Translation lookup < 0.5 ms per string.

## Architectural Design

```php
<?php
declare(strict_types=1);

namespace App\Service\I18n;

use Psr\Container\ContainerInterface; // PSR‑11
use Psr\Http\Message\ServerRequestInterface; // PSR‑7

interface TranslatorInterface
{
    public function translate(string $key, array $params = [], ?string $locale = null): string;
    public function setLocale(string $locale): void;
    public function getLocale(): string;
}

final class ArrayTranslator implements TranslatorInterface
{
    private array $catalogs = [];
    private string $locale = 'en';
    public function __construct(array $catalogs) { $this->catalogs = $catalogs; }
    public function setLocale(string $locale): void { $this->locale = $locale; }
    public function getLocale(): string { return $this->locale; }
    public function translate(string $key, array $params = [], ?string $locale = null): string
    {
        $loc = $locale ?? $this->locale;
        $message = $this->catalogs[$loc][$key] ?? $key;
        return strtr($message, $params);
    }
}

final class LocaleMiddleware
{
    private TranslatorInterface $translator;
    public function __construct(TranslatorInterface $translator) { $this->translator = $translator; }
    public function __invoke(ServerRequestInterface $request, callable $next)
    {
        $locale = $request->getHeaderLine('Accept-Language') ?: 'en';
        $this->translator->setLocale($locale);
        return $next($request);
    }
}
```

**Mermaid Component Diagram**

```mermaid
componentDiagram
    component ArrayTranslator {
        +translate(string, array, ?string): string
        +setLocale(string): void
    }
    component LocaleMiddleware {
        +__invoke(ServerRequestInterface, callable): ResponseInterface
    }
    ArrayTranslator --> TranslatorInterface
    LocaleMiddleware --> TranslatorInterface
```

## Integration Strategy

Registered in the Core DI container (`CORE-02`). Middleware is added early in the API gateway stack (`HUB-12`) to set the locale for the request lifecycle. Controllers retrieve the translator via `$container->get(TranslatorInterface::class)`.

## CI Verification Criteria

- Unit test coverage ≥ 93% for translation lookup and fallback.
- Integration test verifies that `Accept-Language` header changes the locale used by a controller.
- Latency overhead ≤ 0.5 ms per request.
- Reliability: missing keys fall back to the key identifier without error.

## SemVer Impact

**Minor** – Introduces i18n APIs and middleware, affecting response rendering.
