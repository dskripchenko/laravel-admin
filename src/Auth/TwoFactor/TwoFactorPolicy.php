<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth\TwoFactor;

use Illuminate\Database\Eloquent\Model;

/**
 * The installation's two-factor policy, from `admin.auth.two_factor`.
 *
 *   - `enabled` — whether users may set 2FA up at all. Switching it off hides
 *     the setup and refuses to enable it; an account that enrolled earlier
 *     still passes the challenge at login and may still switch it off.
 *   - `enforce_for` — the role slugs ('*' for everyone) whose holders must
 *     enable 2FA before they can use the panel.
 */
final class TwoFactorPolicy
{
    public static function enabled(): bool
    {
        return (bool) config('admin.auth.two_factor.enabled', true);
    }

    /**
     * Whether the user is one of those who must have 2FA on.
     */
    public static function requiredFor(object $user): bool
    {
        if (! self::enabled()) {
            return false;
        }

        $roles = array_values(array_filter(
            (array) config('admin.auth.two_factor.enforce_for', []),
            static fn ($role): bool => is_string($role) && $role !== '',
        ));
        if ($roles === []) {
            return false;
        }
        if (in_array('*', $roles, true)) {
            return true;
        }
        if (! $user instanceof Model || ! method_exists($user, 'roles')) {
            return false;
        }

        return $user->roles()->whereIn('slug', $roles)->exists();
    }

    public static function hasEnabled(object $user): bool
    {
        return method_exists($user, 'hasTwoFactorEnabled') && $user->hasTwoFactorEnabled() === true;
    }

    /**
     * Must enable 2FA and has not yet: the panel is closed to them until they do.
     */
    public static function setupPending(object $user): bool
    {
        return self::requiredFor($user) && ! self::hasEnabled($user);
    }
}
