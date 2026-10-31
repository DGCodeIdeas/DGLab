<?php

declare(strict_types=1);

namespace SovereignStack\Core\Crypto;

/**
 * HKDF-SHA256 key derivation wrapper (RFC 5869).
 *
 * Used by HUB-20 Vault for envelope-encryption key derivation
 * (master KEK -> per-secret DEK) and by HUB-04 for JWT
 * signing-key derivation from the master APP_KEY.
 *
 * NOT a password-hashing primitive — HKDF has no salt-and-iteration
 * loop and is trivially brute-forceable for low-entropy inputs.
 *
 * @package SovereignStack\Core\Crypto
 */
final class Hasher
{
    /**
     * Derive a subkey from a master key via HKDF-SHA256.
     *
     * @param string $masterKey  The input key material (IKM).
     * @param string $info       Context/application-specific info string.
     * @param int    $length     Output length in bytes (default 32 = 256 bits).
     * @return string            Derived key material of $length bytes.
     */
    public function deriveKey(string $masterKey, string $info, int $length = 32): string
    {
        // RFC 5869 bounds: 1 .. hashlen*255 = 8160 bytes for SHA-256.
        if ($length < 1 || $length > 8160) {
            throw new CryptoException('HKDF_LENGTH', "deriveKey length must be between 1 and 8160 bytes, got {$length}");
        }

        return \hash_hkdf('sha256', $masterKey, $length, $info);
    }
}
