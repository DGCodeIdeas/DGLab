<?php

namespace DGLab\Services\Auth;

use RuntimeException;
use InvalidArgumentException;

class JWTService
{
    public function encode(array $payload, string $key, string $algo = 'HS256'): string
    {
        $header = ['typ' => 'JWT', 'alg' => $algo];
        $segments = [ $this->base64UrlEncode(json_encode($header)), $this->base64UrlEncode(json_encode($payload)) ];
        $signingInput = implode('.', $segments);
        $signature = $this->sign($signingInput, $key, $algo);
        $segments[] = $this->base64UrlEncode($signature);
        return implode('.', $segments);
    }
    public function decode(string $jwt, string $key, array $allowedAlgos = ['HS256', 'RS256']): array
    {
        $tks = explode('.', $jwt);
        if (count($tks) !== 3) {
            throw new InvalidArgumentException('Wrong number of segments');
        }
        list($headb64, $bodyb64, $sigb64) = $tks;
        $header = json_decode($this->base64UrlDecode($headb64), true);
        if (null === $header) {
            throw new InvalidArgumentException('Invalid header');
        }
        if (empty($header['alg']) || !in_array($header['alg'], $allowedAlgos)) {
            throw new InvalidArgumentException('Algorithm not allowed');
        }
        $payload = json_decode($this->base64UrlDecode($bodyb64), true);
        if (null === $payload) {
            throw new InvalidArgumentException('Invalid payload');
        }
        $sig = $this->base64UrlDecode($sigb64);
        if (!$this->verify("$headb64.$bodyb64", $sig, $key, $header['alg'])) {
            throw new RuntimeException('Signature verification failed');
        }
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new RuntimeException('Token expired');
        }
        return $payload;
    }
    protected function sign(string $input, string $key, string $algo): string
    {
        switch ($algo) {
            case 'HS256':
                return hash_hmac('sha256', $input, $key, true);
            case 'RS256':
                $signature = '';
                if (!openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
                    throw new RuntimeException("OpenSSL sign failed");
                }
                return $signature;
            default:
                throw new InvalidArgumentException("Unsupported algorithm: $algo");
        }
    }
    protected function verify(string $input, string $signature, string $key, string $algo): bool
    {
        switch ($algo) {
            case 'HS256':
                $expected = hash_hmac('sha256', $input, $key, true);
                return hash_equals($expected, $signature);
            case 'RS256':
                $success = openssl_verify($input, $signature, $key, OPENSSL_ALGO_SHA256);
                if ($success === -1) {
                    throw new RuntimeException("OpenSSL error");
                }
                return $success === 1;
            default:
                throw new InvalidArgumentException("Unsupported algorithm: $algo");
        }
    }
    protected function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
    protected function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }
}
