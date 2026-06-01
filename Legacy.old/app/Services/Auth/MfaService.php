<?php

namespace DGLab\Services\Auth;

use DGLab\Models\User;
use Exception;

/**
 * MFA Service
 *
 * Handles TOTP generation, verification, and backup codes.
 */
class MfaService
{
    /**
     * Generate a new TOTP secret.
     */
    public function generateSecret(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Get the TOTP code for a secret at a given time.
     */
    public function getCode(string $secret, ?int $time = null): string
    {
        if (null === $time) {
            $time = time();
        }
        $timestamp = floor($time / 30);

        $secretKey = $this->base32Decode($secret);

        $timeHex = str_pad(dechex($timestamp), 16, '0', STR_PAD_LEFT);
        $timeBin = pack('H*', $timeHex);

        $hash = hash_hmac('sha1', $timeBin, $secretKey, true);
        $offset = ord($hash[19]) & 0xf;

        $otp = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a TOTP code.
     */
    public function verifyCode(string $secret, string $code, int $discrepancy = 1): bool
    {
        $currentTime = time();
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            if ($this->getCode($secret, $currentTime + ($i * 30)) === $code) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate a set of backup codes.
     */
    public function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = sprintf('%04d-%04d', random_int(0, 9999), random_int(0, 9999));
        }
        return $codes;
    }

    /**
     * Decode base32 string.
     */
    protected function base32Decode(string $base32): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32 = strtoupper($base32);
        $result = '';
        $buffer = 0;
        $bufferLength = 0;

        for ($i = 0; $i < strlen($base32); $i++) {
            $char = $base32[$i];
            $value = strpos($chars, $char);
            if ($value === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $value;
            $bufferLength += 5;

            if ($bufferLength >= 8) {
                $result .= chr(($buffer >> ($bufferLength - 8)) & 0xff);
                $bufferLength -= 8;
            }
        }

        return $result;
    }

    /**
     * Generate QR code URL (using external service or simple placeholder)
     */
    public function getQrCodeUrl(string $user, string $secret, string $issuer = 'DGLab'): string
    {
        $user = urlencode($user);
        $issuer = urlencode($issuer);
        return "otpauth://totp/{$issuer}:{$user}?secret={$secret}&issuer={$issuer}";
    }
}
