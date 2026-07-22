<?php

namespace App\Support;

/**
 * Minimal RFC 6238 TOTP generator (SHA-1, 6 digits, 30s period) so the
 * lookup API can hand a Shortcut the current code without a dependency.
 */
class Totp
{
    public static function code(string $base32Secret, ?int $timestamp = null): ?string
    {
        $key = self::base32Decode($base32Secret);

        if ($key === null) {
            return null;
        }

        $counter = intdiv($timestamp ?? time(), 30);
        $binary = pack('N2', 0, $counter);
        $hash = hash_hmac('sha1', $binary, $key, true);

        $offset = ord($hash[19]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            (ord($hash[$offset + 1]) << 16) |
            (ord($hash[$offset + 2]) << 8) |
            ord($hash[$offset + 3])
        ) % 1_000_000;

        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $input): ?string
    {
        $input = strtoupper(str_replace([' ', '='], '', $input));

        if ($input === '' || preg_match('/[^A-Z2-7]/', $input)) {
            return null;
        }

        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';

        foreach (str_split($input) as $char) {
            $position = strpos($alphabet, $char);

            if ($position === false) {
                return null;
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr((int) bindec($byte));
            }
        }

        return $bytes === '' ? null : $bytes;
    }
}
