<?php

namespace Features\Auth\TwoFactorAuth\Infrastructure\Support;

use Random\RandomException;

/**
 * Minimal RFC 6238 (TOTP) implementation.
 */
class TwoFactor
{
    private const string BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Verify a code, accepting $window time steps of clock drift on each side.
     */
    public function verifyOtp(string $secret, string $otp, int $timeStep = 30, int $window = 1): bool
    {
        $otp = trim($otp);

        if ($secret === '' || ! preg_match('/^\d{6}$/', $otp)) {
            return false;
        }

        $now = time();

        for ($i = -$window; $i <= $window; $i++) {
            $generatedOtp = $this->generateOtp($secret, $timeStep, 6, $now + ($i * $timeStep));

            if (hash_equals($generatedOtp, $otp)) {
                return true;
            }
        }

        return false;
    }

    public function generateOtp(string $secret, int $timeStep = 30, int $digits = 6, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), $timeStep);
        $binaryCounter = pack('N*', 0).pack('N*', $counter);

        $hmac = hash_hmac('sha1', $binaryCounter, $this->base32Decode($secret), true);

        $offset = ord($hmac[strlen($hmac) - 1]) & 0x0F;
        $code = (
            ((ord($hmac[$offset]) & 0x7F) << 24) |
            ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
            ((ord($hmac[$offset + 2]) & 0xFF) << 8) |
            (ord($hmac[$offset + 3]) & 0xFF)
        ) % (10 ** $digits);

        return str_pad((string) $code, $digits, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $data): string
    {
        // Authenticator apps display secrets in groups and with padding; ignore both.
        $data = strtoupper((string) preg_replace('/[\s=-]+/', '', $data));
        $buffer = '';

        foreach (str_split($data) as $char) {
            $position = strpos(self::BASE32_ALPHABET, $char);

            if ($position === false) {
                continue;
            }

            $buffer .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';

        foreach (str_split($buffer, 8) as $byte) {
            // Trailing bits that do not form a full byte are padding.
            if (strlen($byte) === 8) {
                $binary .= chr(bindec($byte));
            }
        }

        return $binary;
    }

    /**
     * Generate a random 160-bit secret (32 base32 characters), as recommended by RFC 4226.
     *
     * @throws RandomException
     */
    public function generateTwoFactorSecret(): string
    {
        $secret = '';

        for ($i = 0; $i < 32; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, 31)];
        }

        return $secret;
    }

    /**
     * Build the otpauth:// URI consumed by authenticator apps (QR code payload).
     */
    public function provisioningUri(string $issuer, string $accountName, string $secret): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?%s',
            rawurlencode($issuer),
            rawurlencode($accountName),
            http_build_query([
                'secret' => $secret,
                'issuer' => $issuer,
                'algorithm' => 'SHA1',
                'digits' => 6,
                'period' => 30,
            ], '', '&', PHP_QUERY_RFC3986)
        );
    }
}
