<?php

declare(strict_types=1);

namespace DGLab\Core\Crypto;

use JsonSerializable;

/**
 * Value object representing an encrypted envelope.
 *
 * Properties are base64-encoded for JSON transport. The envelope
 * is the only payload format the Encrypter accepts/returns.
 *
 * @package DGLab\Core\Crypto
 */
final readonly class Envelope implements JsonSerializable
{
    public function __construct(
        public int $version,
        public string $kid,
        public string $iv,
        public string $ciphertext,
        public string $tag,
    ) {
    }

    /**
     * @return array{v: int, kid: string, iv: string, ciphertext: string, tag: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'v' => $this->version,
            'kid' => $this->kid,
            'iv' => base64_encode($this->iv),
            'ciphertext' => base64_encode($this->ciphertext),
            'tag' => base64_encode($this->tag),
        ];
    }

    /**
     * Reconstruct from a decoded JSON array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $raw = $data['v'] ?? 0;

        return new self(
            version: is_int($raw) ? $raw : (is_numeric($raw) ? (int) $raw : 0),
            kid: self::stringOf($data['kid'] ?? ''),
            iv: self::binaryOf($data['iv'] ?? ''),
            ciphertext: self::binaryOf($data['ciphertext'] ?? ''),
            tag: self::binaryOf($data['tag'] ?? ''),
        );
    }

    private static function stringOf(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }

        return \is_scalar($value) ? (string) $value : '';
    }

    private static function binaryOf(mixed $value): string
    {
        $encoded = self::stringOf($value);

        if ($encoded === '') {
            return '';
        }

        return base64_decode($encoded, true) ?: '';
    }
}
