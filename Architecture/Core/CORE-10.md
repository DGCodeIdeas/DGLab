# CORE-10: Configuration & Environment Loader

## Tier
Core (Foundational Infrastructure)

## Resolves
- **Finding 2** (evaluation-layer mapping) — The approved blueprint's evaluation-layer name ("Configuration") coincidentally matches the canonical Core-tier identity (`01_MASTER_INDEX.md` §2: CORE-10 = Configuration & Environment Loader). This blueprint locks in the canonical mapping so the stale evaluation layer need not be consulted again.
- **Finding 4** (thin blueprints) — The approved `CORE-10.md` is 1,219 bytes, prose-only, with no interfaces, no compilable code, no diagrams, and a bare "< 0.01ms" target. Replaced with real PHP 8.3 interfaces, a complete compilable `ConfigLoader`, Mermaid sequence + state diagrams, a methodology-grounded benchmark table, and explicit security invariants.
- **Finding 10** (bare performance targets) — The "< 0.01ms" assertion is replaced with a benchmark table whose every row names the harness (PHPUnit `--group performance`), baseline (GitHub Actions `ubuntu-latest`, PHP 8.3, opcache, no Xdebug), and load model (1 KB / 10 KB / 100 KB JSON files, 10 000 iterations). All absolute numbers marked "provisional, unverified" until first CI measurement.

## Component Name
Configuration & Environment Loader — `SovereignStack\Core\Config` (PSR-4 mapped to `packages/core/config/src/` per the package's `composer.json`).

## Description
CORE-10 is the single entry point for all runtime configuration. It reads `.env` files, reads structured JSON config, merges them with explicit precedence rules, validates against a schema, and exposes the frozen result through an immutable `ConfigRepository`. Every Core and Hub service that needs a parameter — DSN, log level, cache TTL, encryption key id, feature flag, tenant override, deployment-mode hint — resolves it through `ConfigInterface`. Components that bypass `ConfigInterface` and read `$_ENV` directly are non-conformant and fail CI.

The component is built in-house rather than wrapping `vlucas/phpdotenv`. Per ADR-002 ("Build-or-Buy for Core Infrastructure"), Core-tier components whose surface area fits a single blueprint are built natively to avoid an unauditable transitive dependency tree. `phpdotenv` pulls in `graham-campbell/result-type`, `phpoption/phpoption`, `symfony/polyfill-ctype`, `symfony/polyfill-mbstring`; CORE-10 pulls in nothing. The accepted trade-off is a smaller feature surface: no variable interpolation across files, no multi-file loader (both out of scope; either would require an ADR).

What CORE-10 is **not**: not a feature-flag service (HUB-01 layers tenant-aware flags on top of the frozen repository); not a secrets manager (HUB-20 serves rotating secrets at runtime); not a remote-config source (no Consul/etcd/AppConfig; runtime reconfiguration is out of scope). The component is small and synchronous: load, merge, validate, freeze. All mutation happens before the Kernel exits its boot phase; all reads after boot are O(1) array lookups.

## Build Status
📝 Not started. 🔴 Blocked on CORE-02 (DI Container) for the singleton binding of `ConfigInterface` → `ConfigRepository`. Implementation can be written and unit-tested in isolation against the interfaces declared here, but production wiring requires the container's `singleton()` method (see CORE-02 reference implementation). Once CORE-02 lands (Build Sequence Step 1), CORE-10 is parallelizable with CORE-08 and CORE-09 per Build Sequence Step 2.

## Dependency Status
- **Upward:** CORE-02 (DI Container — provides `singleton()` binding). The JSON loader uses only `ext-json` (bundled with PHP 8.3); the `.env` parser is pure PHP.
- **Downward:** CORE-08 (reads `error.report_path`, `error.display_details`), CORE-09 (reads `log.level`, `log.channel`, `log.handlers`), CORE-18 (reads `app.env`, `app.debug`, `app.timezone`), CORE-19 (reads `database.default_dsn`), HUB-01 (consumes the frozen repository as its base layer before applying tenant overrides), HUB-15 (reads `health.check_interval`), and every Hub service that takes a `#[ConfigKey]`-annotated parameter. Effectively every stateful service depends on CORE-10.
- **Runtime:** PHP 8.3, `ext-json`. No composer runtime dependencies. CI dev-dependencies: `phpunit/phpunit ^10.5`, `phpstan/phpstan ^1.10`, `friendsofphp/php-cs-fixer ^3.48`.

## Architectural Design

### Class Map

| Class | Kind | Responsibility |
|---|---|---|
| `ConfigLoader` | `final class` | Reads `.env` and JSON, merges with precedence rules, detects environment. Stateless; one instance per Kernel boot. |
| `ConfigRepository` | `final class` | Implements `ConfigInterface`. Holds the frozen merged config array; `get()` is O(1) array lookup. Immutable after construction. |
| `ConfigInterface` | `interface` | Contract every consumer depends on: `get()`, `has()`, `all()`, `getEnvironment()`. |
| `Environment` | `enum` | Three cases: `Dev`, `Staging`, `Production`. Default `Production` (fail-safe). |
| `ConfigValidator` | `final class` | Validates the merged config against an array spec. Throws `ConfigNotFoundException` for missing required keys, `ConfigValidationException` for type mismatches. |
| `ConfigNotFoundException` | `final class extends \RuntimeException` | Thrown when a required key is absent and no default supplied. |
| `ConfigValidationException` | `final class extends \RuntimeException` | Thrown by `ConfigValidator` on type mismatch. |
| `ConfigKey` | `#[\Attribute]` | Marks a constructor parameter/property as config-injected. Read by a CORE-17 compiler pass. |

### Interface Contracts

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Core\Config;

/**
 * Immutable, read-only view of the merged application configuration.
 * After construction the configuration MUST NOT mutate: no setters, no
 * reload(). Runtime reconfiguration is the responsibility of HUB-01 and
 * HUB-20, NOT this interface. All get() lookups are O(1) array access.
 */
interface ConfigInterface
{
    /**
     * Fetch a configuration value by dot-notation key.
     *
     * @param string $key     Dot-notation key, e.g. "database.default_dsn".
     * @param mixed  $default Value to return if the key is absent. When
     *                        $default is provided, the method MUST NOT throw.
     *
     * @return mixed The stored value; type depends on the key.
     *
     * @throws ConfigNotFoundException If $key is absent and $default is
     *                                 not supplied, or $key is empty.
     */
    public function get(string $key, mixed $default = null): mixed;

    /** Whether the key exists in the merged configuration. O(1). */
    public function has(string $key): bool;

    /**
     * Returns the entire merged configuration as a nested array. Defensive
     * copy; values whose keys match /password|secret|key|token/i are
     * replaced with "***REDACTED***" so the output is safe to log.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * The detected runtime environment, derived from APP_ENV at load time.
     * If APP_ENV is absent or unrecognised, returns Environment::Production
     * (fail-safe).
     */
    public function getEnvironment(): Environment;
}
```

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Core\Config;

/**
 * The runtime environment. Detection (in ConfigLoader::detectEnvironment):
 *   - APP_ENV=development, dev, local  → Dev
 *   - APP_ENV=staging, stage           → Staging
 *   - APP_ENV=production, prod         → Production
 *   - APP_ENV absent or unrecognised   → Production  (fail-safe)
 */
enum Environment: string
{
    case Dev = 'development';
    case Staging = 'staging';
    case Production = 'production';

    /** Whether verbose error output is permitted (CORE-08 reads this). */
    public function displayErrors(): bool
    {
        return $this === self::Dev;
    }

    /** Whether opcache should be enabled (DEPLOY-01 entrypoint reads this). */
    public function enableOpcache(): bool
    {
        return $this !== self::Dev;
    }
}
```

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Core\Config;

/**
 * Marks a constructor parameter/property as injected from the configuration
 * repository. Read by a CORE-17 (Service Provider) compiler pass during
 * Kernel boot: the pass reflects each service's constructor, finds
 * parameters bearing this attribute, and resolves their values via
 * {@see ConfigInterface::get($key)} before invoking the constructor.
 *
 * Example:
 *   public function __construct(
 *       #[ConfigKey('cache.ttl')] private int $ttl,
 *   ) {}
 *
 * This attribute is read-only metadata. It does NOT perform injection;
 * the CORE-17 compiler pass is the single reader.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final class ConfigKey
{
    public function __construct(
        public readonly string $key,
        public readonly mixed $default = null,
    ) {
    }
}
```

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Core\Config;

/**
 * Thrown when a required configuration key is absent and no default was
 * supplied to get(), or when the schema validator finds a required key
 * missing. The Kernel treats this as a boot-time fatal.
 */
final class ConfigNotFoundException extends \RuntimeException
{
    /** @param string $key The missing dot-notation key. */
    public static function forKey(string $key): self
    {
        return new self("Configuration key [{$key}] not found and no default supplied.");
    }
}
```

### Reference Implementation
The following class is the complete, copy-pasteable `ConfigLoader`. It compiles against PHP 8.3 with no external dependencies (only `ext-json`). Drop it into `packages/core/config/src/ConfigLoader.php`.

```php
<?php
declare(strict_types=1);

namespace SovereignStack\Core\Config;

/**
 * Reads .env files and JSON config, merges with precedence rules,
 * detects the runtime environment. Stateless: one instance per Kernel
 * boot, discarded after producing a {@see ConfigRepository}.
 *
 * .env parsing (subset of shell .env format):
 *   - Lines starting with # are comments; blank lines skipped.
 *   - Optional "export " prefix stripped.
 *   - Keys MUST match /^[A-Z_][A-Z0-9_]*$/.
 *   - Values: unquoted, single-quoted (literal), or double-quoted
 *     (escapes: \n, \t, \r, \\, \", \$).
 *   - Inline comments after unquoted values stripped.
 *   - Backslash at end of line continues to the next line.
 *   - Parsed values written to both $_ENV and $_SERVER. getenv() NOT
 *     called — $_ENV is the thread-safe source (threat model TB-1).
 *
 * JSON loading: top-level MUST be an object; JSON_THROW_ON_ERROR.
 *
 * Merge precedence: non-secrets → JSON wins (committed baseline);
 * secrets (matching /password|secret|key|token/i) → env wins
 * (operator-supplied overrides; CI lint flags secrets in JSON).
 */
final class ConfigLoader
{
    private const SECRET_PATTERN = '/password|secret|key|token/i';
    private const ENV_KEY_PATTERN = '/^[A-Z_][A-Z0-9_]*$/';

    /**
     * Parse a .env file; return key/value pairs and populate $_ENV/$_SERVER.
     *
     * @throws \RuntimeException If the file does not exist or is unreadable.
     * @return array<string, string>
     */
    public function loadEnv(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException("Unable to read .env file at [{$path}].");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException("Failed to read .env file at [{$path}].");
        }

        $config = [];
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        $lineCount = count($lines);

        for ($i = 0; $i < $lineCount; $i++) {
            $line = ltrim($lines[$i]);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }

            $key = substr($line, 0, $eqPos);
            $value = substr($line, $eqPos + 1);

            if (!preg_match(self::ENV_KEY_PATTERN, $key)) {
                throw new \RuntimeException(
                    "Invalid .env key [{$key}] on line " . ($i + 1) . " of [{$path}]; "
                    . 'keys must match /^[A-Z_][A-Z0-9_]*$/.'
                );
            }

            $parsed = $this->parseValue(ltrim($value), $lines, $i, $path);

            $config[$key] = $parsed;
            $_ENV[$key] = $parsed;
            $_SERVER[$key] = $parsed;
        }

        return $config;
    }

    /**
     * Parse a single .env value, handling quotes, escapes, and line
     * continuation. Mutates $lineIndex to advance past continuation lines.
     *
     * @param list<string> $lines
     */
    private function parseValue(string $value, array $lines, int &$lineIndex, string $path): string
    {
        if ($value === '') {
            return '';
        }

        $first = $value[0];

        if ($first === '"') {
            return $this->parseDoubleQuoted($value, $lines, $lineIndex, $path);
        }

        if ($first === "'") {
            return $this->parseSingleQuoted($value, $lines, $lineIndex, $path);
        }

        // Unquoted: strip inline comments, handle backslash continuation.
        $hashPos = strpos($value, ' #');
        if ($hashPos !== false) {
            $value = substr($value, 0, $hashPos);
        }
        $value = rtrim($value);

        while (str_ends_with($value, '\\')) {
            $value = substr($value, 0, -1);
            $lineIndex++;
            if (!isset($lines[$lineIndex])) {
                break;
            }
            $value .= ltrim($lines[$lineIndex]);
        }

        return $value;
    }

    private function parseDoubleQuoted(string $value, array $lines, int &$lineIndex, string $path): string
    {
        $buffer = substr($value, 1);
        $result = '';

        while (true) {
            $len = strlen($buffer);
            for ($j = 0; $j < $len; $j++) {
                $char = $buffer[$j];

                if ($char === '\\' && isset($buffer[$j + 1])) {
                    $next = $buffer[$j + 1];
                    $result .= match ($next) {
                        'n'  => "\n",
                        't'  => "\t",
                        'r'  => "\r",
                        '\\' => '\\',
                        '"'  => '"',
                        '$'  => '$',
                        default => '\\' . $next,
                    };
                    $j++;
                    continue;
                }

                if ($char === '"') {
                    return $result;
                }

                $result .= $char;
            }

            $result .= "\n";
            $lineIndex++;
            if (!isset($lines[$lineIndex])) {
                throw new \RuntimeException(
                    "Unterminated double-quoted value in [{$path}] near line {$lineIndex}."
                );
            }
            $buffer = $lines[$lineIndex];
        }
    }

    private function parseSingleQuoted(string $value, array $lines, int &$lineIndex, string $path): string
    {
        $buffer = substr($value, 1);
        $result = '';

        while (true) {
            $closePos = strpos($buffer, "'");
            if ($closePos === false) {
                $result .= $buffer . "\n";
                $lineIndex++;
                if (!isset($lines[$lineIndex])) {
                    throw new \RuntimeException(
                        "Unterminated single-quoted value in [{$path}] near line {$lineIndex}."
                    );
                }
                $buffer = $lines[$lineIndex];
                continue;
            }

            $result .= substr($buffer, 0, $closePos);
            return $result;
        }
    }

    /**
     * Read and decode a JSON config file.
     *
     * @throws \RuntimeException If unreadable, malformed, or not a top-level object.
     * @return array<string, mixed>
     */
    public function loadJson(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException("Unable to read JSON config at [{$path}].");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException("Failed to read JSON config at [{$path}].");
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException(
                "Invalid JSON in config file [{$path}]: " . $e->getMessage(),
                0,
                $e
            );
        }

        if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new \RuntimeException(
                "Top-level JSON structure in [{$path}] must be an object, not an array."
            );
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Merge env and JSON with precedence rules. Nested arrays merged
     * recursively. Secrets: env wins. Non-secrets: JSON wins.
     *
     * @param array<string, mixed> $env  Flat string map from .env.
     * @param array<string, mixed> $json Decoded JSON object (nested array).
     * @return array<string, mixed>
     */
    public function merge(array $env, array $json): array
    {
        $envTree = $this->nestEnvKeys($env);
        return $this->mergeRecursive($envTree, $json);
    }

    /**
     * Fold flat KEY=VALUE env array into nested tree: APP_FOO=bar →
     * ['app' => ['foo' => 'bar']]. Conflicts overwrite (last wins).
     *
     * @param array<string, string> $env
     * @return array<string, mixed>
     */
    private function nestEnvKeys(array $env): array
    {
        $tree = [];
        foreach ($env as $key => $value) {
            $parts = array_map('strtolower', explode('_', $key));
            $ref = &$tree;
            $partCount = count($parts);
            for ($i = 0; $i < $partCount - 1; $i++) {
                if (!isset($ref[$parts[$i]]) || !is_array($ref[$parts[$i]])) {
                    $ref[$parts[$i]] = [];
                }
                $ref = &$ref[$parts[$i]];
            }
            $ref[$parts[$partCount - 1]] = $value;
            unset($ref);
        }
        return $tree;
    }

    /**
     * Recursive merge. For each leaf: secrets → env wins; non-secrets →
     * JSON wins.
     *
     * @return array<string, mixed>
     */
    private function mergeRecursive(array $envTree, array $json): array
    {
        $out = $envTree;

        foreach ($json as $key => $value) {
            if (is_array($value) && isset($out[$key]) && is_array($out[$key])) {
                $out[$key] = $this->mergeRecursive($out[$key], $value);
                continue;
            }

            if (preg_match(self::SECRET_PATTERN, (string) $key)) {
                if (array_key_exists($key, $envTree)) {
                    continue; // Keep env value already in $out.
                }
            }

            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * Detect runtime environment from merged config. Reads app.env (set
     * from APP_ENV). Unrecognised or absent → Environment::Production
     * (fail-safe).
     */
    public function detectEnvironment(array $config): Environment
    {
        $raw = $config['app']['env'] ?? null;

        if (!is_string($raw)) {
            return Environment::Production;
        }

        $lower = strtolower(trim($raw));

        return match (true) {
            in_array($lower, ['dev', 'development', 'local'], true) => Environment::Dev,
            in_array($lower, ['staging', 'stage'], true)            => Environment::Staging,
            in_array($lower, ['prod', 'production'], true)          => Environment::Production,
            default                                                 => Environment::Production,
        };
    }

    /**
     * Redact secret values from a config array (replace with "***REDACTED***").
     * Used by ConfigRepository::all() to produce log-safe snapshots.
     *
     * @return array<string, mixed>
     */
    public function redact(array $config): array
    {
        $out = [];
        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $out[$key] = $this->redact($value);
                continue;
            }
            if (preg_match(self::SECRET_PATTERN, (string) $key)) {
                $out[$key] = '***REDACTED***';
                continue;
            }
            $out[$key] = $value;
        }
        return $out;
    }
}
```

### SQL DDL
Not applicable. CORE-10 is stateless: configuration is loaded from files into memory, validated, frozen in a `ConfigRepository`, and never persisted. The repository is constructed fresh on every Kernel boot. Persistent configuration storage (tenant overrides, feature-flag state) is the responsibility of HUB-01 and CORE-19.

### Sequence Diagram

```mermaid
sequenceDiagram
    participant K as CORE-18 Kernel
    participant CL as ConfigLoader
    participant V as ConfigValidator
    participant CR as ConfigRepository
    participant C as CORE-02 Container

    K->>CL: loadEnv("/app/.env")
    CL-->>K: env: array<string,string>  (also populates $_ENV, $_SERVER)
    K->>CL: loadJson("/app/config/config.json")
    CL-->>K: json: array<string,mixed>
    K->>CL: merge(env, json)
    Note over CL: Secrets (key matching /password|secret|key|token/i):<br/>env wins. Non-secrets: json wins.
    CL-->>K: merged: array<string,mixed>
    K->>CL: detectEnvironment(merged)
    CL-->>K: Environment::Production (default if APP_ENV absent)
    K->>V: validate(merged, schema)
    V->>V: Check required keys present
    V->>V: Check value types match schema
    alt Schema violation
        V-->>K: throw ConfigNotFoundException | ConfigValidationException
        K-->>K: Kernel boot fails; CORE-08 renders 503
    else Schema satisfied
        V-->>K: ok
        K->>CR: new ConfigRepository(merged, environment)
        CR->>CR: Freeze internal state (no further mutation)
        K->>C: singleton(ConfigInterface::class, $cr)
        C->>C: Cache $cr; subsequent get(ConfigInterface) returns same instance
        K-->>K: Boot continues with bound ConfigInterface
    end
```

### State Diagram

```mermaid
stateDiagram-v2
    [*] --> Unloaded
    Unloaded --> LoadingEnv: loadEnv(.env)
    LoadingEnv --> LoadingJson: env parsed; loadJson(config.json)
    LoadingJson --> Merging: json decoded; merge(env, json)
    Merging --> Detecting: merged array built
    Detecting --> Validating: Environment resolved
    Validating --> Frozen: schema OK; ConfigRepository constructed
    Validating --> BootFailed: schema violation
    BootFailed --> [*]: Kernel aborts; CORE-08 handles
    Frozen --> Frozen: get() / has() / all() / getEnvironment() (no mutation)
    Frozen --> [*]: Kernel terminate

    note right of Frozen
        Immutable after construction.
        No setters, no reload().
        Runtime reconfiguration is
        HUB-01 (Config & Flags).
    end note
```

## Integration Strategy

**Upward:** CORE-02 (DI Container). The Kernel constructs `ConfigLoader` directly via `new` (the loader has no dependencies and is not container-managed). After `ConfigRepository` is built, the Kernel binds it into the container as a singleton keyed by `ConfigInterface::class`. Every consumer resolves `ConfigInterface` from the container; the same instance is returned for the entire request lifetime.

**Downward (consumers of CORE-10):**
- CORE-08 reads `error.report_path`, `error.display_details` (gated through `Environment::displayErrors()`).
- CORE-09 reads `log.level`, `log.channel`, `log.handlers`.
- CORE-18 reads `app.env`, `app.debug`, `app.timezone`; uses `Environment` to gate debug behaviour.
- CORE-19 reads `database.default_dsn`, `database.connections.<name>.{dsn,username,password,pool_size}`.
- HUB-01 consumes the entire frozen repository as its base layer, then applies tenant overrides on top.
- HUB-15 reads `health.check_interval`, `health.timeout`.
- Every Hub service that takes a `#[ConfigKey]`-annotated parameter.

The `#[ConfigKey]` attribute is consumed by a CORE-17 (Service Provider) compiler pass that runs during Kernel boot. The pass reflects each service's constructor parameters, finds those bearing the attribute, and resolves their values from `ConfigInterface::get($key)` before invoking the constructor. This is the only sanctioned path for constructor-time config injection; direct `$_ENV` reads in service constructors are non-conformant and fail CI.

```php
// Example: a Hub service consuming CORE-10 via attribute injection.
namespace SovereignStack\Hub\Cache;

use SovereignStack\Core\Config\ConfigKey;

final class CacheService
{
    public function __construct(
        #[ConfigKey('cache.driver')] private string $driver,
        #[ConfigKey('cache.ttl', 3600)] private int $ttl,
    ) {
    }
}
```

## Benchmark & Verification Methodology

Baseline for all rows: GitHub Actions `ubuntu-latest`, PHP 8.3.0, opcache enabled, no Xdebug. Harness: PHPUnit `--group performance` with `microtime(true)` wall-clock. Iterations: 10 000 (1 000-iteration warm-up) unless noted. All absolute targets marked **provisional, unverified** until first CI run records them; subsequent runs assert ≥80% of recorded baseline.

| Target | Load model | Assert |
|---|---|---|
| `loadEnv()` parse throughput | 1 KB `.env`, 40 keys (mix unquoted/single/double/multiline) | provisional, unverified — first CI run records baseline |
| `loadJson()` parse throughput | 3 files: 1 KB (30 keys, 2 levels), 10 KB (300 keys, 3 levels), 100 KB (3 000 keys, 4 levels) | provisional, unverified — linear scaling expected (O(n) in file size) |
| `merge()` cost | 40 env keys (nested 2 levels) × 300 JSON keys (nested 3 levels); 5 secrets overlap | provisional, unverified |
| `ConfigRepository::get()` hot path | 1 000-key repository; `get('app.debug')` in tight loop; 100 000 iterations | O(1) — within 2× of bare `$array[$key]` lookup on same baseline |
| `ConfigRepository::all()` redaction | 1 000-key repository, 50 secrets; 1 000 iterations | provisional, unverified |

**Iron rule (per Governance Rule 2):** No bare millisecond targets. Every row names harness, baseline, and load model. The first CI run's recorded numbers replace the "provisional, unverified" markers in this table; subsequent runs assert ≥80% of the recorded baseline.

## CI Verification Criteria
- **Branch coverage:** 100% on `loadEnv()`, `loadJson()`, `merge()` and on the private helpers `parseValue()` / `parseDoubleQuoted()` / `parseSingleQuoted()` / `nestEnvKeys()` / `mergeRecursive()` / `detectEnvironment()`. 95% on `ConfigRepository`. 100% on `Environment` enum.
- **Static analysis:** `phpstan.neon` level 8, zero baseline-ignored errors; includes `phpstan/strict-rules` and `treatPhpDocTypesAsCertain: true`.
- **`.env` parsing tests (data-provider):** unquoted; single-quoted (literal); double-quoted with `\n`, `\t`, `\r`, `\\`, `\"`, `\$` escapes; inline comment after unquoted value; full-line comment; blank line; `export ` prefix; backslash line continuation across 2 and 3 lines; multiline double-quoted value (2 and 3 lines); multiline single-quoted; unterminated double-quote (asserts `\RuntimeException`); unterminated single-quote (asserts `\RuntimeException`); invalid key (lowercase, leading digit); key with no `=` (ignored silently).
- **JSON loading tests:** valid object; valid object with nested arrays; malformed JSON (asserts `\RuntimeException` wrapping `\JsonException`); top-level array (asserts `\RuntimeException`); empty file; unreadable file.
- **Merge precedence tests:** non-secret in both → JSON wins; secret in both → env wins; secret only in JSON → JSON used (and CI lint flags it, see below); secret only in env → env used; nested merge 3 levels deep with disjoint subtrees; nested merge with overlapping subtrees → recursive merge.
- **Schema validation tests (`ConfigValidator`):** required key present → ok; required key absent → `ConfigNotFoundException`; type mismatch (int expected, string given) → `ConfigValidationException`; enum constraint (expected one of `["dev","staging","production"]`, got `"qa"`) → `ConfigValidationException`; optional key absent → ok.
- **Environment detection tests:** `APP_ENV=production|prod` → `Production`; `APP_ENV=development|dev|local` → `Dev`; `APP_ENV=staging|stage` → `Staging`; `APP_ENV=qa` (unrecognised) → `Production` (fail-safe); `APP_ENV` absent → `Production`; `APP_ENV=` (empty) → `Production`.
- **Immutability test:** reflection-based test asserts no `set()` / `reload()` / `mutate()` method on `ConfigRepository`; `get()` called 1 000 times returns the same value each time.
- **Redaction test:** `all()` on a repository with `database.password`, `api.key`, `auth.token`, `cache.ttl` returns `['***REDACTED***', '***REDACTED***', '***REDACTED***', 3600]`.
- **Direct-`$_ENV`-read prohibition (CI lint):** custom PHPStan rule fails the build if any class in `packages/core/**/src/` other than `ConfigLoader` references `$_ENV` or `$_SERVER` directly.
- **Secret-in-JSON detection (CI lint):** pre-commit hook scans every `config/*.json` for keys matching `/password|secret|key|token/i` whose value is not `"***REDACTED***"` or `null`. Any match fails the build.

## Security Properties
1. **Secrets never logged at boot.** `ConfigLoader::redact()` replaces any value whose key matches `/password|secret|key|token/i` with `"***REDACTED***"` before the merged array is exposed via `ConfigRepository::all()`. CORE-18 (Kernel) and CORE-09 (Logging) MUST call `all()`, not iterate the internal array, when emitting diagnostic snapshots.
2. **`.env` is never committed.** `.gitignore` at the repo root contains `.env` and `.env.*` (but not `.env.example`, which IS committed as the operator-facing template). Pre-commit hook asserts `git diff --cached --name-only` does not include any file matching `.env*` other than `.env.example`.
3. **Config is immutable after Kernel boot.** `ConfigRepository` exposes no setters; the internal array is `private readonly array $config` (PHP 8.3). Reflection-based mutation is technically possible but forbidden by convention — a CI test asserts no service in `packages/**/src/` calls `ReflectionProperty::setValue` on any `ConfigRepository` instance.
4. **Environment defaults to Production.** `detectEnvironment()` returns `Environment::Production` for any unrecognised, absent, or empty `APP_ENV` value. A misconfigured deployment MUST NOT accidentally enable `Environment::Dev` (which would enable verbose error output, in-memory caches, and unthrottled log levels).
5. **Secrets in env win over secrets in JSON.** Merge rule for keys matching the secret pattern: env value wins. This protects against the scenario where a developer accidentally commits a real secret to `config/production.json` — the operator-supplied `.env` value (never committed) overrides it, and the CI lint rule flags the JSON file for sanitisation.
6. **No `getenv()` calls.** `ConfigLoader` reads from `$_ENV` (and writes to both `$_ENV` and `$_SERVER` for downstream PSR-7/Symfony compatibility). `getenv()` is not thread-safe in PHP-FPM worker pools (per threat model TB-1) and is forbidden — a PHPStan rule fails the build on any `getenv(` call in `packages/**/src/`.

## Migration Notes
**Landing the component:**
1. Create `packages/core/config/` with `composer.json` (runtime: `php: ^8.3`, `ext-json: *`; dev: `phpunit/phpunit`, `phpstan/phpstan`, `friendsofphp/php-cs-fixer`), `src/`, `tests/`.
2. Add the package to the root `composer.json` `repositories` (path repository) and `require` (`"sovereignstack/core-config": "@dev"`).
3. `composer update sovereignstack/core-config` to symlink into `vendor/`.
4. Land CORE-02 (DI Container) — required for the singleton binding in step 5.
5. In CORE-18 (Kernel) boot phase, after CORE-02 is constructed, add: `$loader = new ConfigLoader(); $env = $loader->loadEnv(/* path */); $json = $loader->loadJson(/* path */); $merged = $loader->merge($env, $json); $envEnum = $loader->detectEnvironment($merged); (new ConfigValidator())->validate($merged, $schema); $repo = new ConfigRepository($merged, $envEnum); $container->singleton(ConfigInterface::class, $repo);`
6. Roll out consumers one at a time: each Hub service currently reading `$_ENV` directly is migrated to `ConfigInterface::get()` (or `#[ConfigKey]` once CORE-17 lands). Each migration is a separate PR.

**Rollback:**
- `git revert` the merge commit; remove from root `composer.json` `repositories` and `require`; `composer update`.
- Consumers revert to reading `$_ENV` directly — a regression in testability (unit tests can no longer inject a fake `ConfigInterface`; they must mutate `$_ENV` and reset between tests). This regression is the primary cost of rollback.
- No database migration, no schema change, no production deployment to undo — the component is stateless.

**Forward-compatibility note:** a future ADR may swap the in-house loader for `vlucas/phpdotenv` (or `symfony/dotenv`) if the maintenance burden of the custom `.env` parser exceeds the value of zero-dependency auditability. The `ConfigInterface` contract is stable across this swap: only `ConfigLoader` changes; `ConfigRepository`, `Environment`, `ConfigValidator`, and all consumers are unaffected.

## SemVer Impact
**Minor** (initial release: `0.1.0`). The package is new; no existing code is modified. `ConfigInterface` is the public API surface and is stable from `0.1.0` forward — breaking changes (adding a required method, removing `getEnvironment()`, changing `get()`'s signature) require a Major bump. Adding optional methods with default implementations, adding new `Environment` cases, or extending the `#[ConfigKey]` attribute with new constructor parameters (with defaults) are Minor bumps. Bug fixes and performance improvements are Patch bumps.

Minor rather than Major reflects the package's leaf position in the dependency DAG: no consumer exists yet, so the initial release cannot break anything. Once CORE-18 and the Hub tier depend on it, subsequent breaking changes will be Major.
