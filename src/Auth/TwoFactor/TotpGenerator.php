<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth\TwoFactor;

/**
 * TOTP (Time-based One-Time Password) — RFC 6238.
 *
 * Реализация без внешних зависимостей: HMAC-SHA1 + 30s окно + 6-значный код.
 * `verify()` проверяет код в окне ±$window периодов (default 1 = ±30s)
 * для компенсации clock-drift'а между сервером и устройством пользователя.
 */
final class TotpGenerator
{
    private const PERIOD = 30;

    private const DIGITS = 6;

    private const ALGORITHM = 'sha1';

    /**
     * Сгенерировать код для текущего timestamp (для тестов / debug).
     */
    public static function code(string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $counter = (int) floor($timestamp / self::PERIOD);

        return self::generate($secret, $counter);
    }

    /**
     * Проверить код, допуская drift в `$window` периодов в обе стороны.
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
     * Построить otpauth:// URI для QR-кода.
     *
     * Пример: otpauth://totp/Acme:admin@example.com?secret=...&issuer=Acme
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

        // 8-байтный counter в network byte order
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
