<?php

namespace DGLab\Services\Auth;

use RuntimeException;

/**
 * Password Service
 *
 * Handles password hashing and verification using Argon2id.
 */
class PasswordService
{
    private array $config;

    public function __construct()
    {
        $this->config = config('auth.hashing.argon2id', [
            'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
            'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
            'threads' => PASSWORD_ARGON2_DEFAULT_THREADS,
        ]);
    }

    /**
     * Hash a password
     *
     * @param string $password
     * @return string
     */
    public function hash(string $password): string
    {
        $hash = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->config['memory_cost'],
            'time_cost' => $this->config['time_cost'],
            'threads' => $this->config['threads'],
        ]);

        if ($hash === false) {
            throw new RuntimeException("Password hashing failed.");
        }

        return $hash;
    }

    /**
     * Verify a password against a hash
     *
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if a hash needs rehashing based on current config
     *
     * @param string $hash
     * @return bool
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->config['memory_cost'],
            'time_cost' => $this->config['time_cost'],
            'threads' => $this->config['threads'],
        ]);
    }
}
