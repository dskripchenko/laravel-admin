<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Support;

/**
 * Masks of personal data, as a list shows them until «Show» is pressed:
 *
 *   Mask::phone('+7 999 123-45-67')   // +7 *** ***-**-67
 *
 *   Mask::email('ivan.petrov@mail.ru') // i***@mail.ru
 *   Mask::name('Petrov Ivan Sergeevich') // P*** I. S.
 *   Mask::passport('4510 123456')      // **** ***456
 *
 * A host's search provider masks its own items with the same helpers.
 */
final class Mask
{
    public const KINDS = ['phone', 'email', 'name', 'passport', 'full'];

    /**
     * Masks a value by kind; null and '' stay as they are, anything else
     * becomes a string.
     */
    public static function apply(string $kind, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }
        if (! is_scalar($value)) {
            return '***';
        }
        $value = (string) $value;

        return match ($kind) {
            'phone' => self::phone($value),
            'email' => self::email($value),
            'name' => self::name($value),
            'passport' => self::passport($value),
            default => self::full($value),
        };
    }

    /** The country code and the last two digits stay: `+7 *** ***-**-67`. */
    public static function phone(string $value): string
    {
        $digits = (string) preg_replace('/\D/', '', $value);
        if (strlen($digits) < 4) {
            return '***';
        }
        $tail = substr($digits, -2);
        $prefix = strlen($digits) === 11 && in_array($digits[0], ['7', '8'], true) ? '+7 ' : (str_starts_with(trim($value), '+') ? '+'.substr($digits, 0, max(1, strlen($digits) - 10)).' ' : '');

        return $prefix.'*** ***-**-'.$tail;
    }

    /** The first letter and the domain stay: `i***@mail.ru`. */
    public static function email(string $value): string
    {
        $at = strrpos($value, '@');
        if ($at === false || $at === 0) {
            return self::full($value);
        }

        return mb_substr($value, 0, 1).'***'.substr($value, $at);
    }

    /** The surname's first letter and the initials: `P*** I. S.`. */
    public static function name(string $value): string
    {
        $words = preg_split('/\s+/u', trim($value)) ?: [];
        $out = [];
        foreach ($words as $i => $word) {
            if ($word === '') {
                continue;
            }
            $out[] = $i === 0 ? mb_substr($word, 0, 1).'***' : mb_substr($word, 0, 1).'.';
        }

        return $out === [] ? '***' : implode(' ', $out);
    }

    /** The last three digits stay: `**** ***456`. */
    public static function passport(string $value): string
    {
        $digits = (string) preg_replace('/\D/', '', $value);

        return '**** ***'.(strlen($digits) >= 3 ? substr($digits, -3) : '');
    }

    public static function full(string $value): string
    {
        return '***';
    }
}
