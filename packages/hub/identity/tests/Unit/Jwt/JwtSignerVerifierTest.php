<?php
declare(strict_types=1);
namespace SovereignStack\Hub\Identity\Tests\Unit\Jwt;
use PHPUnit\Framework\TestCase;
use SovereignStack\Hub\Identity\Jwt\JwtSigner;
use SovereignStack\Hub\Identity\Jwt\JwtVerifier;
final class JwtSignerVerifierTest extends TestCase
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
    public function testSignAndVerifyRoundTrip(): void
    {
        $signer = new JwtSigner($this->privateKey);
        $verifier = new JwtVerifier($this->publicKey);
        $jwt = $signer->sign(['sub' => '01HTEST', 'email' => 'user@test.com']);
        $payload = $verifier->verify($jwt);
        self::assertSame('01HTEST', $payload['sub']);
        self::assertSame('user@test.com', $payload['email']);
    }
    public function testExpiredTokenRejected(): void
    {
        $signer = new JwtSigner($this->privateKey);
        $verifier = new JwtVerifier($this->publicKey);
        $jwt = $signer->sign(['sub' => '01HTEST'], expiresIn: -1);
        $this->expectException(\RuntimeException::class);
        $verifier->verify($jwt);
    }
    public function testInvalidSignatureRejected(): void
    {
        $signer = new JwtSigner($this->privateKey);
        $config = ['digest_alg' => 'sha256', 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];
        $otherKey = openssl_pkey_new($config);
        $wrongPublicKey = openssl_pkey_get_details($otherKey)['key'];
        $jwt = $signer->sign(['sub' => '01HTEST']);
        $verifier = new JwtVerifier($wrongPublicKey);
        $this->expectException(\RuntimeException::class);
        $verifier->verify($jwt);
    }
}
