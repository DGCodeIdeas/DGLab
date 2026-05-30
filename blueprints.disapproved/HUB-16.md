# HUB-16.md

## Phase ID

`HUB-16`

## Tier

`Hub`

## Component Name and Description

**Configuration Management Service** – Centralizes application configuration loading, validation, and hot‑reloading. Supports hierarchical sources (environment variables, .env files, remote config server) and provides a PSR‑11 `ConfigInterface` for type‑safe access.

## Context7 Research

- **PHP Best Practices**: Validate config schema on startup, use immutable value objects, avoid global state.
- **PSR‑11**: Service container provides `ConfigInterface`.
- **Design Patterns**: Builder for constructing config objects, Strategy for source adapters, Singleton for global access.
- **Performance**: Config lookup < 0.2 ms; hot‑reload latency < 100 ms.

## Architectural Design

```php
<?php
declare(strict_types=1);

namespace App\Config;

use Psr\Container\ContainerInterface; // PSR‑11

interface ConfigInterface
{
    public function get(string $key, $default = null);
    public function has(string $key): bool;
    public function reload(): void;
}

final class Config implements ConfigInterface
{
    private array $values = [];
    private array $sources = [];

    public function __construct(array $sources)
    {
        $this->sources = $sources;
        $this->load();
    }

    private function load(): void
    {
        foreach ($this->sources as $source) {
            $this->values = array_merge($this->values, $source->load());
        }
    }

    public function get(string $key, $default = null)
    {
        return $this->values[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function reload(): void
    {
        $this->values = [];
        $this->load();
    }
}

interface ConfigSourceInterface
{
    public function load(): array;
}

final class EnvConfigSource implements ConfigSourceInterface
{
    public function load(): array
    {
        return $_ENV;
    }
}

final class DotenvConfigSource implements ConfigSourceInterface
{
    private string $path;
    public function __construct(string $path) { $this->path = $path; }
    public function load(): array
    {
        if (!file_exists($this->path)) {
            return [];
        }
        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $data = [];
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            [$k, $v] = explode('=', $line, 2) + [null, null];
            $data[$k] = $v;
        }
        return $data;
    }
}
```

**Mermaid Component Diagram**

```mermaid
componentDiagram
    component Config {
        +get(string, mixed): mixed
        +has(string): bool
        +reload(): void
    }
    component ConfigSource <<interface>>
    component EnvConfigSource <<interface>>
    component DotenvConfigSource <<interface>>
    Config --> ConfigSource
    ConfigSource --> EnvConfigSource
    ConfigSource --> DotenvConfigSource
```

## Integration Strategy

Registered as a singleton in the Core DI container (`CORE-02`). All services type‑hint `ConfigInterface` to retrieve configuration values. Hot‑reload can be triggered via a `/config/reload` endpoint in the Core tier.

## CI Verification Criteria

- Unit test coverage ≥ 94% for source loading and precedence rules.
- Integration test verifies that changes to `.env` are reflected after `reload()`.
- Latency for `get()` ≤ 0.2 ms.
- Security: secret values are masked in logs; validated against a JSON schema on startup.

## SemVer Impact

**Minor** – Adds a new configuration API affecting all components that consume config values.
