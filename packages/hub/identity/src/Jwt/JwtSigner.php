<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Jwt;
use OpenSSLCertificate;

/**
 * JWT signer using ES256 (ECDSA P-256 + SHA-256) per ADR-003.
 *
 * Per Lap 2 P0-2: converts OpenSSL DER ECDSA signature to JWS R||S format
 * (64-octet raw: 32 bytes R + 32 bytes S) per RFC 7518 §3.1.
 * This ensures interoperability with standard JWT/JWS libraries.
 *
 * @package SovereignStack\Hub\Identity\Jwt
 */
final class JwtSigner
{
    private const HEADER = ['alg' => 'ES256', 'typ' => 'JWT'];

    public function __construct(
        private readonly OpenSSLCertificate|string $privateKey,
    ) {}

    public function sign(array $payload, int $expiresIn = 3600): string
    {
        $now = time();
        $payload['iat'] = $payload['iat'] ?? $now;
        $payload['exp'] = $payload['exp'] ?? ($now + $expiresIn);

        $header = $this->b64(json_encode(self::HEADER, JSON_THROW_ON_ERROR));
        $body = $this->b64(json_encode($payload, JSON_THROW_ON_ERROR));
        $data = $header . '.' . $body;

        // OpenSSL produces DER-encoded ECDSA signature
        $derSignature = '';
        if (!openssl_sign($data, $derSignature, $this->privateKey, OPENSSL_ALG_SHA256)) {
            throw new \RuntimeException('JWT signing failed: ' . openssl_error_string());
        }

        // P0-2: Convert DER → raw R||S (64 octets) for JWS ES256 compliance
        $rawSignature = $this->derToRaw($derSignature);

        return $data . '.' . $this->b64($rawSignature);
    }

    /**
     * Convert DER-encoded ECDSA signature to raw R||S format (64 octets).
     * Per RFC 7518 §3.1: ES256 signature = 32-byte R || 32-byte S.
     *
     * DER format: 30 <len> 02 <r_len> <r_bytes> 02 <s_len> <s_bytes>
     */
    private function derToRaw(string $der): string
    {
        $offset = 0;

        // SEQUENCE tag (0x30)
        if (strlen($der) < 2 || ord($der[$offset]) !== 0x30) {
            throw new \RuntimeException('Invalid DER: expected SEQUENCE tag');
        }
        $offset++;

        // Length
        $seqLen = ord($der[$offset]);
        $offset++;
        if ($seqLen & 0x80) {
            $lenBytes = $seqLen & 0x7f;
            $seqLen = 0;
            for ($i = 0; $i < $lenBytes; $i++) {
                $seqLen = ($seqLen << 8) | ord($der[$offset]);
                $offset++;
            }
        }

        // INTEGER r (0x02)
        if (ord($der[$offset]) !== 0x02) {
            throw new \RuntimeException('Invalid DER: expected INTEGER for r');
        }
        $offset++;
        $rLen = ord($der[$offset]);
        $offset++;
        $r = substr($der, $offset, $rLen);
        $offset += $rLen;

        // INTEGER s (0x02)
        if (ord($der[$offset]) !== 0x02) {
            throw new \RuntimeException('Invalid DER: expected INTEGER for s');
        }
        $offset++;
        $sLen = ord($der[$offset]);
        $offset++;
        $s = substr($der, $offset, $sLen);

        // Pad to exactly 32 bytes each (DER may have leading zeros or be short)
        $r = str_pad($r, 32, "\0", STR_PAD_LEFT);
        $s = str_pad($s, 32, "\0", STR_PAD_LEFT);
        // Truncate to 32 bytes if longer
        $r = substr($r, -32);
        $s = substr($s, -32);

        return $r . $s; // 64 octets total
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
