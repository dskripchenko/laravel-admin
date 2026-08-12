<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Impersonation;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Manages an impersonation session.
 *
 * While an impersonation is active it keeps the original administrator's id in
 * the session under `admin_impersonator_id`. start() and stop() switch the
 * authenticated user on the admin guard.
 *
 * The power check: with `block_higher_powered=true` it forbids impersonating
 * a user who holds more permissions than the current one — a guard against
 * escalating privileges through impersonation.
 */
final class ImpersonationManager
{
    public const SESSION_KEY = 'admin_impersonator_id';

    /**
     * Tells whether impersonation is enabled in the config.
     */
    public function enabled(): bool
    {
        return (bool) config('admin.auth.impersonation.enabled', false);
    }

    /**
     * The permission one must hold to start an impersonation.
     */
    public function requiredPermission(): string
    {
        return (string) config('admin.auth.impersonation.permission', 'admin.impersonate');
    }

    /**
     * Tells whether an impersonation is active right now.
     */
    public function isActive(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    /**
     * The id of the original user — the impersonator.
     */
    public function impersonatorId(): int|string|null
    {
        /** @var int|string|null $id */
        $id = Session::get(self::SESSION_KEY);

        return $id;
    }

    /**
     * Starts an impersonation: logs in as `$target` and stores the original's
     * id.
     *
     * It guarantees that the impersonator is authenticated already.
     */
    public function start(Authenticatable&Model $impersonator, Authenticatable&Model $target): void
    {
        $guard = $this->guard();

        Session::put(self::SESSION_KEY, $impersonator->getKey());
        Auth::guard($guard)->login($target);
        // The session changed its user legitimately, so we refresh the hash
        // for AdminAuth — otherwise the session invalidation would consider it
        // stale.
        Session::put('password_hash_'.$guard, (string) $target->getAuthPassword());
    }

    /**
     * Stops the impersonation and brings the original user back.
     *
     * Returns the original's Authenticatable, or null when the original is
     * gone — deleted since. In that case the session is cleared too, but
     * nobody is logged in.
     */
    public function stop(): ?Authenticatable
    {
        $impersonatorId = $this->impersonatorId();
        Session::forget(self::SESSION_KEY);

        if ($impersonatorId === null) {
            return null;
        }

        $provider = Auth::createUserProvider(
            \Dskripchenko\LaravelAdmin\Panel\Panels::currentProvider(),
        );
        $user = $provider?->retrieveById($impersonatorId);

        if ($user instanceof Authenticatable) {
            Auth::guard($this->guard())->login($user);
            Session::put('password_hash_'.$this->guard(), (string) $user->getAuthPassword());
        }

        return $user;
    }

    /**
     * Blocks impersonating a target who holds more rights than the
     * impersonator, when `block_higher_powered` is on.
     *
     * The comparison counts permissions: a proxy measure, but one that keeps
     * RBAC from being walked around. Wildcards (*, admin.users.*) are taken
     * literally — whoever holds them counts as "higher".
     */
    public function isHigherPowered(Authenticatable&Model $impersonator, Authenticatable&Model $target): bool
    {
        if (! (bool) config('admin.auth.impersonation.block_higher_powered', false)) {
            return false;
        }

        if (! method_exists($impersonator, 'getAllPermissions')
            || ! method_exists($target, 'getAllPermissions')) {
            return false;
        }

        $imp = (array) $impersonator->getAllPermissions();
        $tgt = (array) $target->getAllPermissions();

        // An impersonator holding `*` is always higher or equal.
        if (in_array('*', $imp, true)) {
            return false;
        }

        // Does the target hold permissions the impersonator does not?
        return count(array_diff($tgt, $imp)) > 0;
    }

    private function guard(): string
    {
        return \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
    }
}
