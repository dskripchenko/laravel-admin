<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth\TwoFactor;

use InvalidArgumentException;

/**
 * Минимальный RFC-4648 base32 encoder/decoder.
 *
 * Используется для secret в формате `otpauth://`. Без внешних зависимостей.
 */
final class Base32
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Закодировать произвольную byte-строку в base32 (без padding).
     */
    public static function encode(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        $bits = '';
        $length = strlen($bytes);
        for ($i = 0; $i < $length; $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }

        $result = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $result .= self::ALPHABET[bindec($chunk)];
        }

        return $result;
    }

    /**
     * Декодировать base32 в byte-строку.
     */
    public static function decode(string $encoded): string
    {
        $encoded = strtoupper(rtrim($encoded, '='));
        if ($encoded === '') {
            return '';
        }

        $bits = '';
        $length = strlen($encoded);
        for ($i = 0; $i < $length; $i++) {
            $position = strpos(self::ALPHABET, $encoded[$i]);
            if ($position === false) {
                throw new InvalidArgumentException("Invalid base32 character: {$encoded[$i]}");
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $result = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $result .= chr(bindec($chunk));
            }
        }

        return $result;
    }

    /**
     * Сгенерировать новый случайный base32-секрет указанной длины.
     */
    public static function generateSecret(int $length = 32): string
    {
        $alphabet = self::ALPHABET;
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }

        return $secret;
    }
}
