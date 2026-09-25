<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Jwt;
use OpenSSLCertificate;
final class JwtVerifier
{
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
        $data = $header . '.' . $payload;
        $decodedSignature = $this->unb64($signature);
        $result = openssl_verify($data, $decodedSignature, $this->publicKey, OPENSSL_ALG_SHA256);
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
    private function unb64(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
