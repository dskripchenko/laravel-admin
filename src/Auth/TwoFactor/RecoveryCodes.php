<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth\TwoFactor;

/**
 * Управление recovery-кодами 2FA.
 *
 * Каждый код — 10 hex-символов в формате `xxxxx-xxxxx`. Хранятся в зашифрованной
 * JSON-колонке `two_factor_recovery_codes` модели AdminUser (encrypted cast).
 *
 * Коды одноразовые: `verify($codes, $input)` возвращает оставшиеся коды
 * после удаления использованного. Если код не подошёл — возвращает null.
 */
final class RecoveryCodes
{
    public const DEFAULT_COUNT = 8;

    /**
     * Сгенерировать набор recovery-кодов.
     *
     * @return list<string>
     */
    public static function generate(int $count = self::DEFAULT_COUNT): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = self::generateOne();
        }

        return $codes;
    }

    /**
     * Проверить введённый код против списка и вернуть обновлённый список
     * (без использованного) при успехе. null = не подошёл.
     *
     * @param  list<string>  $codes
     * @return list<string>|null
     */
    public static function verify(array $codes, string $input): ?array
    {
        $input = trim($input);
        $remaining = [];
        $matched = false;

        foreach ($codes as $code) {
            if (! $matched && hash_equals($code, $input)) {
                $matched = true;

                continue;
            }
            $remaining[] = $code;
        }

        return $matched ? $remaining : null;
    }

    private static function generateOne(): string
    {
        $left = bin2hex(random_bytes(3));   // 6 hex chars
        $right = bin2hex(random_bytes(3));

        return $left.'-'.$right;
    }
}
