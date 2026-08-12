<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth\TwoFactor;

use InvalidArgumentException;

/**
 * A minimal RFC-4648 base32 encoder and decoder.
 *
 * It is used for the secret in the `otpauth://` format, with no external
 * dependencies.
 */
final class Base32
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Encodes an arbitrary byte string into base32, without padding.
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
     * Decodes base32 back into a byte string.
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
     * Generates a new random base32 secret of the given length.
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
