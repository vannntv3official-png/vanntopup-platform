<?php

namespace App\Services\Security;

class MfaService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $length = 32): string
    {
        $secret = '';
        $max = strlen(self::BASE32_ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, $max)];
        }

        return $secret;
    }

    public function generateCode(string $base32Secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $timeSlice = intdiv($timestamp, 30);
        $secretKey = $this->base32Decode($base32Secret);
        $timeBytes = pack('N*', 0).pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $timeBytes, $secretKey, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $binary = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    public function verifyCode(string $base32Secret, string $code, int $window = 1): bool
    {
        $cleanCode = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($cleanCode) !== 6) {
            return false;
        }

        $now = time();
        for ($i = -$window; $i <= $window; $i++) {
            $expected = $this->generateCode($base32Secret, $now + ($i * 30));
            if (hash_equals($expected, $cleanCode)) {
                return true;
            }
        }

        return false;
    }

    private function base32Decode(string $input): string
    {
        $input = strtoupper(preg_replace('/[^A-Z2-7]/', '', $input) ?? '');
        $bits = '';
        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $val = strpos(self::BASE32_ALPHABET, $input[$i]);
            if ($val !== false) {
                $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
            }
        }

        $output = '';
        for ($i = 0, $len = strlen($bits); $i + 8 <= $len; $i += 8) {
            $output .= chr((int) bindec(substr($bits, $i, 8)));
        }

        return $output;
    }
}
