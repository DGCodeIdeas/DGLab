<?php

declare(strict_types=1);

namespace SovereignStack\Hub\Config;

/**
 * Stable-hash helper for percentage rollouts.
 *
 * Determinism contract: for any string $key, compute($key) returns the same
 * integer in [0, 100) across processes, requests, and restarts on the same PHP
 * build. This is what makes percentage rollouts "sticky" per user — the UI does
 * not flicker between requests.
 *
 * Hash choice: xxh3 if ext-hash exposes it (PHP 8.3+ typically does); falls
 * back to crc32b. Both are pure functions, no I/O, no allocation. xxh3 is
 * preferred for distribution quality at scale; crc32b is the universally-
 * available floor.
 *
 * @package SovereignStack\Hub\Config
 */
final class RolloutBucket
{
    /**
     * Compute a stable bucket in [0, 100) from the supplied key.
     *
     * @param string $key Any string (typically $flag . ':' . $userId).
     * @return int Integer in [0, 100). Suitable for direct comparison
     *             against a rollout percentage: `compute($key) < $pct`.
     */
    public static function compute(string $key): int
    {
        $hash = function_exists('hash')
            && in_array('xxh3', hash_algos(), true)
            ? hash('xxh3', $key, binary: false)
            : hash('crc32b', $key, binary: false);

        // Take the last 8 hex chars (32 bits) and modulo 100.
        return (int) (hexdec(substr($hash, -8)) % 100);
    }
}
