<?php

declare(strict_types=1);

namespace SovereignStack\Core\Crypto;

/**
 * Thrown for every cryptographic failure in CORE-16.
 *
 * The error code is a string enum (not an int) so it survives
 * JSON serialisation for HUB-06 audit logging without ambiguity.
 * The exception message is safe to log — it never contains raw
 * key material, plaintext, or ciphertext.
 *
 * Per doctrine §2 taxonomy: class Permanent-Local (caller or
 * system state is wrong, not retryable, not a panic).
 *
 * Frozen per SDLC-AGRD §2.1 on first implementation.
 */
class CryptoException extends \RuntimeException
{
    public const TAG_MISMATCH = 'TAG_MISMATCH';
    public const UNKNOWN_KID = 'UNKNOWN_KID';
    public const DEACTIVATED_KEY = 'DEACTIVATED_KEY';
    public const INVALID_KEY_LENGTH = 'INVALID_KEY_LENGTH';
    public const INVALID_ENVELOPE = 'INVALID_ENVELOPE';
    public const BASE64_DECODE_FAILED = 'BASE64_DECODE_FAILED';
    public const ENCRYPTION_FAILED = 'ENCRYPTION_FAILED';
    public const WEAK_HASH_PARAMETERS = 'WEAK_HASH_PARAMETERS';

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message ?: $errorCode, 0, $previous);
    }

    public static function tagMismatch(string $kid): self
    {
        return new self(self::TAG_MISMATCH, "AEAD tag mismatch decrypting envelope with kid '{$kid}'");
    }

    public static function unknownKid(string $kid): self
    {
        return new self(self::UNKNOWN_KID, "Unknown key id '{$kid}'");
    }

    public static function deactivatedKey(string $kid): self
    {
        return new self(self::DEACTIVATED_KEY, "Key '{$kid}' is deactivated (decrypt-only)");
    }

    public static function invalidKeyLength(string $kid, int $actual): self
    {
        return new self(
            self::INVALID_KEY_LENGTH,
            "Key '{$kid}' is {$actual} bytes; AES-256 requires 32"
        );
    }

    public static function invalidEnvelope(string $reason): self
    {
        return new self(self::INVALID_ENVELOPE, "Invalid envelope: {$reason}");
    }

    public static function base64DecodeFailed(): self
    {
        return new self(self::BASE64_DECODE_FAILED, 'Payload is not valid base64');
    }

    public static function encryptionFailed(string $kid): self
    {
        return new self(self::ENCRYPTION_FAILED, "openssl_encrypt failed for kid '{$kid}'");
    }

    public static function weakHashParameters(int $memoryCost, int $timeCost, int $threads): self
    {
        return new self(
            self::WEAK_HASH_PARAMETERS,
            "Argon2id parameters below floor: memory_cost={$memoryCost} (min 32768), "
            . "time_cost={$timeCost} (min 2), threads={$threads} (min 1). "
            . 'System refuses to start with weak hash parameters (doctrine §4.4.3).'
        );
    }
}
