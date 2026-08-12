<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth;

/**
 * Whether an account is switched off.
 *
 * Which field says so depends on the model: `is_active` on `AdminUser`,
 * `enabled` on a panel's own user models. The check lives in one place because
 * it is applied twice and must answer the same way both times — at the login,
 * in AuthController, and on every request, in AdminAuth. Should the two
 * diverge, the panel would let a dismissed employee in and throw them out only
 * on the next request.
 */
final class AccountState
{
    /** @var list<string> */
    private const FLAGS = ['is_active', 'enabled'];

    public static function isDisabled(object $user): bool
    {
        // A model may close access not by a field of its own but by the state
        // of whoever it belongs to: a suspended account, an expired
        // subscription, a revoked contract. The panel knows nothing of those
        // rules and has no business knowing — it only asks.
        if (method_exists($user, 'isDisabledForLogin') && $user->isDisabledForLogin() === true) {
            return true;
        }

        if (! method_exists($user, 'getAttribute')) {
            return false;
        }

        foreach (self::FLAGS as $flag) {
            $value = $user->getAttribute($flag);

            // A missing field is not a refusal: the model may have no switch
            // at all. Only an explicitly false value refuses — `0` included,
            // for the databases without a boolean type.
            if ($value !== null && ! $value) {
                return true;
            }
        }

        return false;
    }
}
