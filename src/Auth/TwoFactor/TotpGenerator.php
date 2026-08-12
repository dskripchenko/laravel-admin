<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth\TwoFactor;

/**
 * TOTP — the time-based one-time password of RFC 6238.
 *
 * An implementation with no external dependencies: HMAC-SHA1, a 30-second
 * window and a six-digit code. `verify()` accepts a code within ±$window
 * periods — one, that is ±30 seconds, by default — to absorb the clock drift
 * between the server and the user's device.
 */
final class TotpGenerator
{
    private const PERIOD = 30;

    private const DIGITS = 6;

    private const ALGORITHM = 'sha1';

    /**
     * Generates the code for the current timestamp, for tests and debugging.
     */
    public static function code(string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $counter = (int) floor($timestamp / self::PERIOD);

        return self::generate($secret, $counter);
    }

    /**
     * Checks a code, allowing a drift of `$window` periods either way.
     */
    public static function verify(string $secret, string $code, int $window = 1, ?int $timestamp = null): bool
    {
        $timestamp ??= time();
        $code = trim($code);

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $current = (int) floor($timestamp / self::PERIOD);

        for ($offset = -$window; $offset <= $window; $offset++) {
            $candidate = self::generate($secret, $current + $offset);
            if (hash_equals($candidate, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Builds the otpauth:// URI behind the QR code.
     *
     * For example: otpauth://totp/Acme:admin@example.com?secret=...&issuer=Acme
     */
    public static function provisioningUri(string $secret, string $accountName, string $issuer): string
    {
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);

        $label = rawurlencode($issuer.':'.$accountName);

        return "otpauth://totp/{$label}?{$params}";
    }

    private static function generate(string $secret, int $counter): string
    {
        $binarySecret = Base32::decode($secret);

        // An eight-byte counter in network byte order
        $counterBytes = pack('N*', 0, $counter);

        $hash = hash_hmac(self::ALGORITHM, $counterBytes, $binarySecret, true);

        // Dynamic truncation per RFC 4226 §5.3
        $offsetByte = ord(substr($hash, -1)) & 0x0F;
        /** @var array{1: int} $unpacked */
        $unpacked = unpack('N', substr($hash, $offsetByte, 4));
        $truncated = $unpacked[1] & 0x7FFFFFFF;

        $code = $truncated % (10 ** self::DIGITS);

        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }
}
