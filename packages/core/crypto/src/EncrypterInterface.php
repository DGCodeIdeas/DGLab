<?php

declare(strict_types=1);

namespace SovereignStack\Core\Crypto;

/**
 * Main encryption service contract.
 *
 * The encrypter produces a self-describing, versioned "envelope" —
 * a base64-encoded JSON object containing the key id (kid), the
 * 12-byte initialization vector, the ciphertext, and the 16-byte
 * AEAD authentication tag. The envelope is the only payload format
 * the encrypter accepts on input or returns on output; raw
 * ciphertext is never accepted.
 *
 * Frozen per SDLC-AGRD §2.1 on first implementation.
 */
interface EncrypterInterface
{
    public function encrypt(string $plaintext, ?string $kid = null): string;
    public function decrypt(string $payload): string;
    public function rotateKey(string $newKid): void;
}
