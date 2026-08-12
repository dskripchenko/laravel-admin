<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth\TwoFactor;

/**
 * Managing the 2FA recovery codes.
 *
 * Each code is ten hex characters in the form `xxxxx-xxxxx`. They live in
 * AdminUser's encrypted JSON column `two_factor_recovery_codes`.
 *
 * The codes are single-use: `verify($codes, $input)` returns what is left
 * after removing the one that was used, or null when nothing matched.
 */
final class RecoveryCodes
{
    public const DEFAULT_COUNT = 8;

    /**
     * Generates a set of recovery codes.
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
     * Checks the code that was entered against the list and, on success,
     * returns the list without it. null means nothing matched.
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
