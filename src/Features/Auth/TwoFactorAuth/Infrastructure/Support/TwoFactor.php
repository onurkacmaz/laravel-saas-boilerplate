<?php

namespace Features\Auth\TwoFactorAuth\Infrastructure\Support;

use Random\RandomException;

class TwoFactor
{
    public function verifyOtp(string $secret, string $otp, int $timeStep = 30, int $window = 1): bool
    {
        for ($i = -$window; $i <= $window; $i++) {
            $generatedOtp = $this->generateOtp($secret, $timeStep);
            if ($generatedOtp === $otp) {
                return true;
            }
        }

        return false;
    }

    public function generateOtp(string $secret, int $timeStep = 30, int $digits = 6): string
    {
        $time = intdiv(time(), $timeStep);
        $binaryTime = pack('N*', 0).pack('N*', $time);

        $decodedSecret = $this->base32Decode($secret);
        $hmac = hash_hmac('sha1', $binaryTime, $decodedSecret, true);

        $offset = ord($hmac[strlen($hmac) - 1]) & 0x0F;
        $code = (
            ((ord($hmac[$offset]) & 0x7F) << 24) |
            ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
            ((ord($hmac[$offset + 2]) & 0xFF) << 8) |
            (ord($hmac[$offset + 3]) & 0xFF)
        ) % (10 ** $digits);

        return str_pad($code, $digits, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $data): string
    {
        $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = strtoupper($data);
        $buffer = '';

        foreach (str_split($data) as $char) {
            $buffer .= str_pad(decbin(strpos($base32Chars, $char)), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($buffer, 8) as $byte) {
            $binary .= chr(bindec($byte));
        }

        return $binary;
    }

    /**
     * @throws RandomException
     */
    public function generateTwoFactorSecret(): string
    {
        $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        for ($i = 0; $i < 16; $i++) {
            $secret .= $base32Chars[random_int(0, 31)];
        }

        return $secret;
    }
}
