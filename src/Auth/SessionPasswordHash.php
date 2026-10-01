<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth;

use Illuminate\Support\Facades\Auth;

/**
 * The password fingerprint a session keeps under `password_hash_{guard}`, so
 * that changing the password signs the other sessions out.
 *
 * Laravel's SessionGuard writes it at the login. Older releases stored the
 * password hash itself; current 12.x and 13.x store an HMAC of it
 * (`hashPasswordForCookie()`). Everything that writes the value goes through
 * make() — the same format the guard writes — and verification accepts both,
 * as Laravel's own AuthenticateSession does, so a session opened before an
 * upgrade stays valid.
 */
final class SessionPasswordHash
{
    public static function key(string $guard): string
    {
        return 'password_hash_'.$guard;
    }

    /** The value to store for this password hash, in the guard's format. */
    public static function make(string $guard, string $passwordHash): string
    {
        $sessionGuard = Auth::guard($guard);

        return method_exists($sessionGuard, 'hashPasswordForCookie')
            ? (string) $sessionGuard->hashPasswordForCookie($passwordHash)
            : $passwordHash;
    }

    /** Whether the stored value belongs to this password hash. */
    public static function matches(string $guard, string $passwordHash, string $stored): bool
    {
        return hash_equals(self::make($guard, $passwordHash), $stored)
            || hash_equals($passwordHash, $stored);
    }
}
