<?php

declare(strict_types=1);

namespace SovereignStack\Core\Crypto;

use JsonSerializable;

/**
 * Value object representing an encrypted envelope.
 *
 * Properties are base64-encoded for JSON transport. The envelope
 * is the only payload format the Encrypter accepts/returns.
 *
 * @package SovereignStack\Core\Crypto
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
        return new self(
            version: (int) ($data['v'] ?? 0),
            kid: (string) ($data['kid'] ?? ''),
            iv: base64_decode((string) ($data['iv'] ?? ''), true) ?: '',
            ciphertext: base64_decode((string) ($data['ciphertext'] ?? ''), true) ?: '',
            tag: base64_decode((string) ($data['tag'] ?? ''), true) ?: '',
        );
    }
}
