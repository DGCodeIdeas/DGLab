<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Jwt;
use OpenSSLCertificate;

/**
 * JWT verifier using ES256 (ECDSA P-256 + SHA-256) per ADR-003.
 *
 * Per Lap 2 P0-2: enforces header.alg === 'ES256' and converts
 * JWS R||S signature back to DER for OpenSSL verification.
 *
 * @package SovereignStack\Hub\Identity\Jwt
 */
final class JwtVerifier
{
    private const EXPECTED_ALG = 'ES256';

    public function __construct(
        private readonly OpenSSLCertificate|string $publicKey,
    ) {}

    public function verify(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Invalid JWT format');
        }

        [$header, $payload, $signature] = $parts;

        // P0-2: Enforce header.alg === 'ES256'
        $headerData = json_decode($this->unb64($header), true);
        if (!is_array($headerData) || ($headerData['alg'] ?? null) !== self::EXPECTED_ALG) {
            throw new \RuntimeException(
                'JWT alg mismatch: expected ' . self::EXPECTED_ALG .
                ', got ' . ($headerData['alg'] ?? 'missing')
            );
        }

        $data = $header . '.' . $payload;

        // P0-2: Convert JWS R||S (64 octets) back to DER for openssl_verify
        $rawSignature = $this->unb64($signature);
        $derSignature = $this->rawToDer($rawSignature);

        $result = openssl_verify($data, $derSignature, $this->publicKey, OPENSSL_ALG_SHA256);
        if ($result !== 1) {
            throw new \RuntimeException('JWT signature verification failed');
        }

        $payloadData = json_decode($this->unb64($payload), true);
        if (!is_array($payloadData)) {
            throw new \RuntimeException('Invalid JWT payload');
        }

        if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
            throw new \RuntimeException('JWT expired');
        }

        return $payloadData;
    }

    /**
     * Convert raw R||S signature (64 octets) to DER format for OpenSSL.
     * Inverse of JwtSigner::derToRaw().
     */
    private function rawToDer(string $raw): string
    {
        if (strlen($raw) !== 64) {
            throw new \RuntimeException('Invalid raw signature: expected 64 bytes, got ' . strlen($raw));
        }

        $r = substr($raw, 0, 32);
        $s = substr($raw, 32, 32);

        // Remove leading zeros (DER INTEGER encoding)
        $r = ltrim($r, "\0");
        $s = ltrim($s, "\0");
        if ($r === '') $r = "\0";
        if ($s === '') $s = "\0";

        // If high bit is set, add leading zero (DER positive integer)
        if (ord($r[0]) & 0x80) $r = "\0" . $r;
        if (ord($s[0]) & 0x80) $s = "\0" . $s;

        $rEncoded = "\x02" . chr(strlen($r)) . $r;
        $sEncoded = "\x02" . chr(strlen($s)) . $s;
        $sequence = $rEncoded . $sEncoded;

        return "\x30" . chr(strlen($sequence)) . $sequence;
    }

    private function unb64(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
