<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Tests\Unit\Jwt;
use PHPUnit\Framework\TestCase;
use SovereignStack\Hub\Identity\Jwt\JwtSigner;
use SovereignStack\Hub\Identity\Jwt\JwtVerifier;

/**
 * Depth 3 error-path tests for JWT (Lap 2 deepening).
 *
 * Per P0-2: verifies ES256 JWS interoperability + algorithm enforcement.
 */
final class JwtDepth3Test extends TestCase
{
    private string $privateKey;
    private string $publicKey;

    protected function setUp(): void
    {
        $config = [
            'digest_alg' => 'sha256',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ];
        $key = openssl_pkey_new($config);
        openssl_pkey_export($key, $this->privateKey);
        $this->publicKey = openssl_pkey_get_details($key)['key'];
    }

    /**
     * P0-2: Sign+verify round-trip works with R||S format.
     */
    public function testSignAndVerifyWithRFormat(): void
    {
        $signer = new JwtSigner($this->privateKey);
        $verifier = new JwtVerifier($this->publicKey);

        $jwt = $signer->sign(['sub' => '01HTEST', 'email' => 'user@test.com']);
        $payload = $verifier->verify($jwt);

        self::assertSame('01HTEST', $payload['sub']);
    }

    /**
     * P0-2: Signature is 64 bytes (R||S), not DER.
     */
    public function testSignatureIsRawRFormat(): void
    {
        $signer = new JwtSigner($this->privateKey);
        $jwt = $signer->sign(['sub' => 'test']);

        $parts = explode('.', $jwt);
        $signature = base64_decode(strtr($parts[2], '-_', '+/'));

        // ES256 signature MUST be exactly 64 bytes (32 R + 32 S)
        self::assertSame(64, strlen($signature), 'ES256 signature must be 64 bytes (R||S)');
    }

    /**
     * P0-2: Verifier rejects non-ES256 algorithm.
     */
    public function testVerifierRejectsWrongAlgorithm(): void
    {
        // Craft a JWT with alg=HS256 (wrong algorithm)
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode(['sub' => 'test']));
        $jwt = $header . '.' . $payload . '.signature';

        $verifier = new JwtVerifier($this->publicKey);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('alg mismatch');
        $verifier->verify($jwt);
    }

    /**
     * P0-2: Verifier rejects missing alg.
     */
    public function testVerifierRejectsMissingAlgorithm(): void
    {
        $header = base64_encode(json_encode(['typ' => 'JWT']));
        $payload = base64_encode(json_encode(['sub' => 'test']));
        $jwt = $header . '.' . $payload . '.signature';

        $verifier = new JwtVerifier($this->publicKey);

        $this->expectException(\RuntimeException::class);
        $verifier->verify($jwt);
    }

    /**
     * P0-2: Expired token rejected.
     */
    public function testExpiredTokenRejected(): void
    {
        $signer = new JwtSigner($this->privateKey);
        $verifier = new JwtVerifier($this->publicKey);

        $jwt = $signer->sign(['sub' => '01HTEST'], expiresIn: -1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('expired');
        $verifier->verify($jwt);
    }

    /**
     * P0-2: Invalid signature rejected.
     */
    public function testInvalidSignatureRejected(): void
    {
        $signer = new JwtSigner($this->privateKey);

        // Generate different key for verification
        $config = ['digest_alg' => 'sha256', 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];
        $otherKey = openssl_pkey_new($config);
        $wrongPublicKey = openssl_pkey_get_details($otherKey)['key'];

        $jwt = $signer->sign(['sub' => '01HTEST']);
        $verifier = new JwtVerifier($wrongPublicKey);

        $this->expectException(\RuntimeException::class);
        $verifier->verify($jwt);
    }

    /**
     * P0-2: Malformed JWT (2 parts) rejected.
     */
    public function testMalformedJwtRejected(): void
    {
        $verifier = new JwtVerifier($this->publicKey);

        $this->expectException(\InvalidArgumentException::class);
        $verifier->verify('not.a.jwt');
    }
}
