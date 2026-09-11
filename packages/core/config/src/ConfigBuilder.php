<?php

declare(strict_types=1);

namespace SovereignStack\Core\Config;

use SovereignStack\Core\Config\Exception\InvalidConfigFileException;

/**
 * Default {@see ConfigBuilderInterface} implementation.
 *
 * Merges PHP config files, $_ENV (string values only), and inline overrides
 * into an immutable {@see ConfigRepository}.
 *
 * Recursive merge semantics: nested arrays are merged key-by-key rather
 * than replaced wholesale. This lets a `local.php` override override a
 * single nested key without redefining the whole tree.
 *
 * After {@see build()} is called, the builder freezes — further mutations
 * throw {@see \LogicException}. This enforces the ADR-017 worker-scope
 * discipline: configuration is built once at worker boot and never mutated.
 */
final class ConfigBuilder implements ConfigBuilderInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @var array<int, array{0: string, 1: mixed}> */
    private array $overrides = [];

    private bool $frozen = false;

    public function loadFile(string $path): static
    {
        $this->assertNotFrozen();

        if (!is_file($path) || !is_readable($path)) {
            throw InvalidConfigFileException::missing($path);
        }

        $loaded = require $path;

        if (!is_array($loaded)) {
            throw InvalidConfigFileException::notArray($path, get_debug_type($loaded));
        }

        /** @var array<string, mixed> $loaded */
        $this->data = $this->mergeRecursive($this->data, $loaded);

        return $this;
    }

    public function withOverride(string $key, mixed $value): static
    {
        $this->assertNotFrozen();
        $this->assertValidKey($key);
        $this->overrides[] = [$key, $value];
        return $this;
    }

    public function build(): ConfigInterface
    {
        $this->assertNotFrozen();
        $this->frozen = true;

        // 1. Start from file-merged data.
        $merged = $this->data;

        // 2. Merge $_ENV (string values only, dot-converted: APP_URL -> app.url).
        foreach ($_ENV as $envKey => $envValue) {
            // PHPStan stubs $_ENV as array<string, string> in some contexts
            // and array<string, mixed> in others. The is_string() check guards
            // against runtime non-string values (rare but legal via direct
            // $_ENV assignment) without affecting the happy path.
            if (!is_string($envValue) || $envValue === '') {
                continue;
            }
            $configKey = strtolower(str_replace('_', '.', $envKey));
            $this->setNested($merged, $configKey, $envValue);
        }

        // 3. Apply explicit overrides last (highest precedence).
        foreach ($this->overrides as [$key, $value]) {
            $this->setNested($merged, $key, $value);
        }

        return new ConfigRepository($merged);
    }

    private function assertNotFrozen(): void
    {
        if ($this->frozen) {
            throw new \LogicException(
                'ConfigBuilder is frozen after build(). Create a new builder to produce another repository.',
            );
        }
    }

    /**
     * Validate a dot-notation key immediately, before storing the override.
     *
     * Empty keys and keys with consecutive dots are rejected as malformed —
     * catching them at withOverride() time gives the caller a useful stack
     * trace pointing at the bad input, rather than deferring the error to
     * build() where the originating call site is lost.
     */
    private function assertValidKey(string $key): void
    {
        if ($key === '') {
            throw new \InvalidArgumentException('Configuration key cannot be empty.');
        }
        if (str_contains($key, '..')) {
            throw new \InvalidArgumentException(
                "Configuration key '{$key}' contains an empty segment (consecutive dots).",
            );
        }
    }

    /**
     * Recursively merge two config arrays. $right wins on key collision;
     * when both sides are arrays, they merge recursively.
     *
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     *
     * @return array<string, mixed>
     */
    private function mergeRecursive(array $left, array $right): array
    {
        foreach ($right as $key => $value) {
            // $key is typed string per the @param annotation; PHPStan trusts
            // this. Runtime non-string keys (from a malformed config file)
            // would surface as a TypeError when used as an array key.
            if (
                array_key_exists($key, $left)
                && is_array($left[$key])
                && is_array($value)
            ) {
                /** @var array<string, mixed> $leftNested */
                $leftNested = $left[$key];
                /** @var array<string, mixed> $rightNested */
                $rightNested = $value;
                $left[$key] = $this->mergeRecursive($leftNested, $rightNested);
            } else {
                $left[$key] = $value;
            }
        }
        return $left;
    }

    /**
     * Set a value at a dot-notation path, creating intermediate arrays as needed.
     *
     * @param array<string, mixed> $array Reference — mutated in place.
     * @param string $key Dot-separated path.
     * @param mixed $value Value to set.
     */
    private function setNested(array &$array, string $key, mixed $value): void
    {
        if ($key === '') {
            throw new \InvalidArgumentException('Configuration key cannot be empty.');
        }

        $segments = explode('.', $key);
        $current = &$array;

        foreach ($segments as $i => $segment) {
            if ($segment === '') {
                throw new \InvalidArgumentException(
                    "Configuration key '{$key}' contains an empty segment (consecutive dots).",
                );
            }

            if ($i === count($segments) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }
}
