<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Permission;

use Dskripchenko\LaravelAdmin\Panel\Panels;
use Illuminate\Support\Facades\Auth;

/**
 * Checks permission keys against a user — by default the one signed in to the
 * current panel.
 *
 * The semantics are those of the AdminAccess middleware: every listed key is
 * required, and a user model without `hasAccess()` holds none. An empty list
 * (or null) requires nothing.
 */
final class PermissionCheck
{
    /**
     * @param  list<string>|string|null  $permission
     */
    public static function allows(array|string|null $permission, ?object $user = null): bool
    {
        $required = self::normalize($permission);
        if ($required === []) {
            return true;
        }

        $user ??= self::currentUser();
        if ($user === null || ! method_exists($user, 'hasAccess')) {
            return false;
        }

        foreach ($required as $key) {
            if (! $user->hasAccess($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the user holds at least one of the permissions — the rule the
     * menu uses for a node, the same as the SPA's hasAnyPermission(). True
     * when none are given.
     *
     * @param  list<string>|string|null  $permission
     */
    public static function allowsAny(array|string|null $permission, ?object $user = null): bool
    {
        $required = self::normalize($permission);
        if ($required === []) {
            return true;
        }

        $user ??= self::currentUser();
        if ($user === null || ! method_exists($user, 'hasAccess')) {
            return false;
        }

        foreach ($required as $key) {
            if ($user->hasAccess($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The first key the user lacks, for an error message; null when none.
     *
     * @param  list<string>|string|null  $permission
     */
    public static function firstMissing(array|string|null $permission, ?object $user = null): ?string
    {
        $user ??= self::currentUser();
        foreach (self::normalize($permission) as $key) {
            if (! self::allows($key, $user)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param  list<string>|string|null  $permission
     * @return list<string>
     */
    public static function normalize(array|string|null $permission): array
    {
        $keys = is_array($permission) ? $permission : [$permission];

        return array_values(array_filter(
            array_map(static fn (mixed $p): string => is_string($p) ? trim($p) : '', $keys),
            static fn (string $p): bool => $p !== '',
        ));
    }

    private static function currentUser(): ?object
    {
        try {
            return Auth::guard(Panels::currentGuard())->user();
        } catch (\Throwable) {
            // No panel and no auth outside an HTTP request: nobody is signed in.
            return null;
        }
    }
}
