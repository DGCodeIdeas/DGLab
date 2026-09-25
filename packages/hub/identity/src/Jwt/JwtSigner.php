<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Jwt;
use OpenSSLCertificate;
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
        $signature = '';
        if (!openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALG_SHA256)) {
            throw new \RuntimeException('JWT signing failed: ' . openssl_error_string());
        }
        return $data . '.' . $this->b64($signature);
    }
    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
