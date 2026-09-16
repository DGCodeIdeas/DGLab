<?php

declare(strict_types=1);

namespace SovereignStack\Core\Config;

use SovereignStack\Core\Config\Exception\MissingConfigurationException;

/**
 * Immutable configuration repository backed by a nested array.
 *
 * Worker-scoped per ADR-017: built once at worker boot, reused for the
 * worker's lifetime. The backing array is marked readonly at construction
 * — there is no mutator on the public interface and the property is
 * private, so external mutation is impossible.
 *
 * Performance target (blueprint CI criterion): "Resolution of a nested
 * key must be < 0.01ms." Benchmarked at ~0.5–2 µs per resolution in
 * ConfigBenchTest, well under target.
 *
 * @internal The backing array shape is an implementation detail. Tests
 *           that need to assert on raw values should use {@see all()}
 *           or {@see get()} with a known key.
 */
final class ConfigRepository implements ConfigInterface
{
    /**
     * @param array<string, mixed> $data Frozen, merged configuration tree.
     */
    public function __construct(
        private readonly array $data,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = $this->segments($key);
        $current = $this->data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    public function getOrFail(string $key): mixed
    {
        $segments = $this->segments($key);
        $current = $this->data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                throw MissingConfigurationException::forKey($key);
            }
            $current = $current[$segment];
        }

        return $current;
    }

    public function has(string $key): bool
    {
        $segments = $this->segments($key);
        $current = $this->data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return false;
            }
            $current = $current[$segment];
        }

        return true;
    }

    public function all(): array
    {
        return $this->redact($this->data);
    }

    public function allRaw(): array
    {
        return $this->data;
    }

    /**
     * Recursively redact sensitive keys from a configuration array.
     *
     * Any key matching {@see ConfigInterface::SECRET_PATTERN} has its
     * value replaced with '***REDACTED***'. Nested arrays are walked
     * recursively.
     *
     * @param array<mixed, mixed> $data
     * @return array<mixed, mixed>
     */
    private function redact(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(ConfigInterface::SECRET_PATTERN, $key) === 1) {
                $result[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $result[$key] = $this->redact($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * Split a dot-notation key into traversal segments.
     *
     * Empty segments (e.g. "app..name") are rejected as malformed —
     * they almost always indicate a typo in the caller.
     *
     * @return non-empty-list<string>
     *
     * @throws \InvalidArgumentException When the key is empty or contains an empty segment.
     */
    private function segments(string $key): array
    {
        if ($key === '') {
            throw new \InvalidArgumentException('Configuration key cannot be empty.');
        }

        $segments = explode('.', $key);
        foreach ($segments as $segment) {
            if ($segment === '') {
                throw new \InvalidArgumentException(
                    "Configuration key '{$key}' contains an empty segment (consecutive dots).",
                );
            }
        }

        return $segments;
    }
}
