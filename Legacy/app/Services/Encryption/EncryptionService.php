<?php

namespace DGLab\Services\Encryption;

use RuntimeException;

/**
 * Encryption Service
 *
 * Provides secure AES-256-GCM encryption and decryption.
 */
class EncryptionService
{
    /**
     * Encryption cipher
     */
    private const CIPHER = 'aes-256-gcm';

    /**
     * Encryption key
     */
    private string $key;

    /**
     * Constructor
     *
     * @param string $key 32-character encryption key
     */
    public function __construct(string $key)
    {
        if (strlen($key) !== 32) {
            throw new RuntimeException("Encryption key must be exactly 32 characters.");
        }
        $this->key = $key;
    }

    /**
     * Encrypt data
     *
     * @param mixed $data Data to encrypt
     * @return string Base64URL encoded encrypted string
     */
    public function encrypt(mixed $data): string
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag = '';

        $json = json_encode($data);
        if ($json === false) {
            throw new RuntimeException("Failed to encode data to JSON for encryption.");
        }

        $ciphertext = openssl_encrypt(
            $json,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            throw new RuntimeException("Encryption failed.");
        }

        // Combine IV + Tag + Ciphertext
        $combined = $iv . $tag . $ciphertext;

        return $this->base64UrlEncode($combined);
    }

    /**
     * Decrypt data
     *
     * @param string $encrypted Base64URL encoded encrypted string
     * @return mixed Decrypted data
     */
    public function decrypt(string $encrypted): mixed
    {
        $data = $this->base64UrlDecode($encrypted);

        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        $tagLen = 16; // Standard tag length for GCM

        if (strlen($data) <= ($ivLen + $tagLen)) {
            return null;
        }

        $iv = substr($data, 0, $ivLen);
        $tag = substr($data, $ivLen, $tagLen);
        $ciphertext = substr($data, $ivLen + $tagLen);

        $decrypted = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            return null;
        }

        return json_decode($decrypted, true);
    }

    /**
     * Base64URL encode
     */
    private function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * Base64URL decode
     */
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }
}
