<?php

declare(strict_types=1);

namespace SovereignStack\Core\Crypto;

use SensitiveParameterValue;

/**
 * In-process multi-key registry for AES-256-GCM encryption keys.
 *
 * Per doctrine §4.4.1: raw key material is held inside
 * SensitiveParameterValue (PHP 8.4+) to prevent accidental
 * disclosure via var_dump, stack traces, or error logs.
 *
 * The registry never persists keys — it is an in-process cache
 * populated at boot from CORE-10 Config or HUB-20 Vault.
 *
 * @package SovereignStack\Core\Crypto
 */
final class KeyRegistry implements KeyRegistryInterface
{
    private const KEY_LENGTH = 32;

    /** @var array<string, SensitiveParameterValue> */
    private array $keys = [];

    /** @var array<string, bool> */
    private array $active = [];

    private ?string $activeKid = null;

    public function getActiveKey(): string
    {
        if ($this->activeKid === null) {
            throw CryptoException::unknownKid('(none active)');
        }
        return $this->activeKid;
    }

    public function getKey(string $kid): string
    {
        if (!isset($this->keys[$kid])) {
            throw CryptoException::unknownKid($kid);
        }
        if (!$this->active[$kid] && $this->activeKid !== $kid) {
            // Key exists but is RETAINED — still retrievable for decrypt.
        }
        return $this->keys[$kid]->value;
    }

    public function addKey(string $kid, string $key): void
    {
        if (isset($this->keys[$kid])) {
            throw new CryptoException('DUPLICATE_KID', "Key id '{$kid}' already exists");
        }
        if (\strlen($key) !== self::KEY_LENGTH) {
            throw CryptoException::invalidKeyLength($kid, \strlen($key));
        }
        $this->keys[$kid] = new SensitiveParameterValue($key);
        $this->active[$kid] = false;
        if ($this->activeKid === null) {
            $this->setActiveKey($kid);
        }
    }

    public function deactivateKey(string $kid): void
    {
        if (!isset($this->keys[$kid])) {
            throw CryptoException::unknownKid($kid);
        }
        $this->active[$kid] = false;
        if ($this->activeKid === $kid) {
            $this->activeKid = null;
        }
    }

    public function setActiveKey(string $kid): void
    {
        if (!isset($this->keys[$kid])) {
            throw CryptoException::unknownKid($kid);
        }
        if ($this->activeKid !== null && $this->activeKid !== $kid) {
            $this->active[$this->activeKid] = false;
        }
        $this->active[$kid] = true;
        $this->activeKid = $kid;
    }

    public function __toString(): string
    {
        return \sprintf('KeyRegistry(%d keys, active=%s)', \count($this->keys), $this->activeKid ?? '(none)');
    }
}
