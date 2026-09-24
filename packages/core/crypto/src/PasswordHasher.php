<?php

declare(strict_types=1);

namespace SovereignStack\Core\Crypto;

/**
 * Argon2id password hashing wrapper per ADR-008.
 *
 * Per doctrine §4.4.3: Argon2id parameters are floored at
 * memory_cost=32768 (32 MiB), time_cost=2, threads=1.
 * Anything weaker is rejected with CryptoException(WEAK_HASH_PARAMETERS).
 *
 * Default parameters: memory_cost=65536 (64 MiB), time_cost=4, threads=2
 * per ADR-008 (RFC 9106 second-tier).
 *
 * @package SovereignStack\Core\Crypto
 */
final class PasswordHasher
{
    public const DEFAULT_MEMORY_COST = 65536;
    public const DEFAULT_TIME_COST = 4;
    public const DEFAULT_THREADS = 2;

    // Doctrine §4.4.3 floor — anything below this is rejected
    public const FLOOR_MEMORY_COST = 32768;
    public const FLOOR_TIME_COST = 2;
    public const FLOOR_THREADS = 1;

    /** @var array{memory_cost: int, time_cost: int, threads: int} */
    private array $options;

    /**
     * @param array{memory_cost?: int, time_cost?: int, threads?: int} $options
     */
    public function __construct(array $options = [])
    {
        $this->options = [
            'memory_cost' => $options['memory_cost'] ?? self::DEFAULT_MEMORY_COST,
            'time_cost' => $options['time_cost'] ?? self::DEFAULT_TIME_COST,
            'threads' => $options['threads'] ?? self::DEFAULT_THREADS,
        ];

        // Enforce floor per doctrine §4.4.3
        if ($this->options['memory_cost'] < self::FLOOR_MEMORY_COST
            || $this->options['time_cost'] < self::FLOOR_TIME_COST
            || $this->options['threads'] < self::FLOOR_THREADS
        ) {
            throw CryptoException::weakHashParameters(
                $this->options['memory_cost'],
                $this->options['time_cost'],
                $this->options['threads']
            );
        }
    }

    public function hash(string $plaintext): string
    {
        $hash = \password_hash($plaintext, \PASSWORD_ARGON2ID, $this->options);
        if ($hash === false) {
            throw new CryptoException('HASH_FAILED', 'password_hash returned false — Argon2id not available');
        }
        return $hash;
    }

    public function verify(string $plaintext, string $hash): bool
    {
        return \password_verify($plaintext, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return \password_needs_rehash($hash, \PASSWORD_ARGON2ID, $this->options);
    }

    /**
     * @return array{memory_cost: int, time_cost: int, threads: int}
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
