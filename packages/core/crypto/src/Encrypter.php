<?php

declare(strict_types=1);

namespace SovereignStack\Core\Crypto;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * AES-256-GCM authenticated encryption with versioned envelopes.
 *
 * The encrypter is the only entry point for symmetric encryption in
 * the SovereignStack. It owns three concerns: IV generation, AEAD
 * encryption via OpenSSL, and envelope serialisation. Key material
 * is delegated to KeyRegistryInterface.
 *
 * Per doctrine §4.4.4: DEK (raw key from registry) is zeroized via
 * sodium_memzero after each encrypt/decrypt call. The encrypter does
 * not cache DEKs across requests.
 *
 * @package SovereignStack\Core\Crypto
 */
final class Encrypter implements EncrypterInterface
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const KEY_LENGTH = 32;
    private const ENVELOPE_VERSION = 1;

    public function __construct(
        private readonly KeyRegistryInterface $registry,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    public function encrypt(string $plaintext, ?string $kid = null): string
    {
        $effectiveKid = $kid ?? $this->registry->getActiveKey();
        $key = $this->registry->getKey($effectiveKid);

        if (\strlen($key) !== self::KEY_LENGTH) {
            throw CryptoException::invalidKeyLength($effectiveKid, \strlen($key));
        }

        $iv = \random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = \openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            \OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            $this->logger->error('openssl_encrypt failed', ['kid' => $effectiveKid]);
            // Zeroize key material before throwing (doctrine §4.4.4)
            \sodium_memzero($key);
            throw CryptoException::encryptionFailed($effectiveKid);
        }

        $envelope = new Envelope(
            version: self::ENVELOPE_VERSION,
            kid: $effectiveKid,
            iv: $iv,
            ciphertext: $ciphertext,
            tag: $tag
        );

        $this->logger->debug('envelope encrypted', ['kid' => $effectiveKid, 'bytes' => \strlen($plaintext)]);

        // Zeroize key material after use (doctrine §4.4.4)
        \sodium_memzero($key);

        return \base64_encode(\json_encode($envelope, \JSON_THROW_ON_ERROR));
    }

    public function decrypt(string $payload): string
    {
        $decoded = \base64_decode($payload, true);
        if ($decoded === false) {
            throw CryptoException::base64DecodeFailed();
        }

        try {
            $data = \json_decode($decoded, true, 4, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw CryptoException::invalidEnvelope('json decode failed: ' . $e->getMessage());
        }

        if (!\is_array($data)
            || !isset($data['v'], $data['kid'], $data['iv'], $data['ciphertext'], $data['tag'])
        ) {
            throw CryptoException::invalidEnvelope('missing required fields');
        }

        if ((int) $data['v'] !== self::ENVELOPE_VERSION) {
            throw CryptoException::invalidEnvelope('unsupported envelope version: ' . $data['v']);
        }

        $kid = (string) $data['kid'];
        $key = $this->registry->getKey($kid);

        if (\strlen($key) !== self::KEY_LENGTH) {
            \sodium_memzero($key);
            throw CryptoException::invalidKeyLength($kid, \strlen($key));
        }

        $iv = \base64_decode((string) $data['iv'], true);
        $ciphertext = \base64_decode((string) $data['ciphertext'], true);
        $tag = \base64_decode((string) $data['tag'], true);

        if ($iv === false || $ciphertext === false || $tag === false) {
            \sodium_memzero($key);
            throw CryptoException::invalidEnvelope('inner base64 decode failed');
        }

        $plaintext = \openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            \OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            // Tag mismatch — fail closed, zeroize key (doctrine §4.4.4)
            \sodium_memzero($key);
            $this->logger->warning('openssl_decrypt failed (tag mismatch)', ['kid' => $kid]);
            throw CryptoException::tagMismatch($kid);
        }

        $this->logger->debug('envelope decrypted', ['kid' => $kid, 'bytes' => \strlen($plaintext)]);

        // Zeroize key material after use (doctrine §4.4.4)
        \sodium_memzero($key);

        return $plaintext;
    }

    public function rotateKey(string $newKid): void
    {
        $newKey = \random_bytes(self::KEY_LENGTH);
        $this->registry->addKey($newKid, $newKey);
        $this->registry->setActiveKey($newKid);
        $this->logger->info('encryption key rotated', ['new_kid' => $newKid]);
    }
}
