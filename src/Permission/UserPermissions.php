<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Permission;

/**
 * A user's flat permission list, for the SPA — the login payload and the
 * bootstrap.
 *
 * A panel's own user model, in the shared strategy, is required to implement
 * only `hasAccess(string): bool`, and its rights cannot be enumerated. Such a
 * model gets the wildcard `['*']`: the real authorization stays with the
 * backend, where AdminAccess answers 403, and the SPA's guards do not lock the
 * user into /forbidden. A model with granular rights should return
 * getAllPermissions().
 */
final class UserPermissions
{
    /**
     * @return list<string>
     */
    public static function resolve(?object $user): array
    {
        if ($user === null) {
            return [];
        }

        if (method_exists($user, 'getAllPermissions')) {
            return array_values((array) $user->getAllPermissions());
        }

        if (method_exists($user, 'hasAccess')) {
            return ['*'];
        }

        return [];
    }
}
