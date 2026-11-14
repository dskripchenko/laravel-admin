<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Impersonation;

use Dskripchenko\LaravelAdmin\Auth\SessionPasswordHash;
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

    /** The impersonator's morph type, beside the id. */
    public const SESSION_TYPE_KEY = 'admin_impersonator_type';

    /**
     * The impersonator's password hash at the start, so that coming back
     * honours a password changed meanwhile as a fresh login would.
     */
    public const SESSION_HASH_KEY = 'admin_impersonator_password_hash';

    /** The audit events of an impersonation's start and end. */
    public const EVENT_STARTED = 'impersonation.started';

    public const EVENT_STOPPED = 'impersonation.stopped';

    /**
     * True while start() and stop() switch the signed-in user: the Login
     * event that switch fires is not a login, and AuthAuditListener leaves it
     * out — the impersonation's own entries tell who signed in as whom.
     */
    private static bool $switching = false;

    public static function isSwitching(): bool
    {
        return self::$switching;
    }

    /**
     * The administrator signed in as someone else in the current request, as
     * `{type, id}`; null outside an impersonation, and outside a request with
     * a session (a queued job, the console).
     *
     * @return array{type: string, id: int|string}|null
     */
    public static function currentImpersonator(): ?array
    {
        try {
            if (! app()->bound('session') || ! Session::isStarted() || ! Session::has(self::SESSION_KEY)) {
                return null;
            }
            /** @var int|string $id */
            $id = Session::get(self::SESSION_KEY);
            $type = Session::get(self::SESSION_TYPE_KEY);
            if (! is_string($type) || $type === '') {
                $modelClass = \Dskripchenko\LaravelAdmin\Panel\Panels::currentAuthModel();
                $type = (new $modelClass)->getMorphClass();
            }

            return ['type' => $type, 'id' => $id];
        } catch (\Throwable) {
            return null;
        }
    }

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
     * The original user as the shell shows them, `{id, name}`; null outside an
     * impersonation or when the original is gone.
     *
     * @return array{id: mixed, name: mixed}|null
     */
    public function describeImpersonator(): ?array
    {
        if (! $this->isActive()) {
            return null;
        }

        $provider = Auth::createUserProvider(
            \Dskripchenko\LaravelAdmin\Panel\Panels::currentProvider(),
        );
        $original = $provider?->retrieveById($this->impersonatorId());

        return $original instanceof Model
            ? ['id' => $original->getKey(), 'name' => $original->getAttribute('name')]
            : null;
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
        Session::put(self::SESSION_TYPE_KEY, $impersonator->getMorphClass());
        Session::put(self::SESSION_HASH_KEY, SessionPasswordHash::make($guard, (string) $impersonator->getAuthPassword()));
        // A role preview belongs to the impersonator and ends here.
        Session::forget(\Dskripchenko\LaravelAdmin\Permission\RolePreview::SESSION_KEY);
        $this->switchTo($guard, $target);

        $this->audit(self::EVENT_STARTED, $impersonator, $target);
    }

    /**
     * Stops the impersonation and brings the original user back.
     *
     * Returns the original's Authenticatable, or null when the original is
     * gone — deleted, switched off, or with a password changed since. In that
     * case the session is cleared too, and nobody is logged in.
     */
    public function stop(): ?Authenticatable
    {
        $impersonatorId = $this->impersonatorId();
        $impersonatorType = Session::get(self::SESSION_TYPE_KEY);
        $impersonatorHash = Session::get(self::SESSION_HASH_KEY);
        $target = Auth::guard($this->guard())->user();
        Session::forget([self::SESSION_KEY, self::SESSION_TYPE_KEY, self::SESSION_HASH_KEY]);

        if ($impersonatorId === null) {
            return null;
        }

        $provider = Auth::createUserProvider(
            \Dskripchenko\LaravelAdmin\Panel\Panels::currentProvider(),
        );
        $user = $provider?->retrieveById($impersonatorId);

        // The way back is a login of its own: an account switched off, or a
        // password changed since the start, does not get the session back.
        if ($user instanceof Authenticatable && (
            \Dskripchenko\LaravelAdmin\Auth\AccountState::isDisabled($user)
            || (is_string($impersonatorHash) && ! SessionPasswordHash::matches($this->guard(), (string) $user->getAuthPassword(), $impersonatorHash))
        )) {
            $user = null;
        }

        if ($user instanceof Authenticatable) {
            $this->switchTo($this->guard(), $user);
        } else {
            // Nobody to return to: the impersonated session does not outlive it.
            Auth::guard($this->guard())->logout();
        }

        if ($user instanceof Model && $target instanceof Model) {
            $this->audit(self::EVENT_STOPPED, $user, $target);
        } elseif ($target instanceof Model) {
            $this->audit(self::EVENT_STOPPED, null, $target, [
                'type' => is_string($impersonatorType) ? $impersonatorType : null,
                'id' => $impersonatorId,
            ]);
        }

        return $user;
    }

    /**
     * Whether the user can be entered at all: one who may not use the panel,
     * or whose account is switched off, would lock the administrator out —
     * every request, «Exit» included, would be refused.
     */
    public function canBeImpersonated(Authenticatable&Model $target): bool
    {
        return \Dskripchenko\LaravelAdmin\Auth\PanelAccess::allows($target)
            && ! \Dskripchenko\LaravelAdmin\Auth\AccountState::isDisabled($target);
    }

    private function switchTo(string $guard, Authenticatable $user): void
    {
        self::$switching = true;
        try {
            Auth::guard($guard)->login($user);
        } finally {
            self::$switching = false;
        }
        // The session changed its user legitimately, so we refresh the hash
        // for AdminAuth — otherwise the session invalidation would consider it
        // stale.
        Session::put(SessionPasswordHash::key($guard), SessionPasswordHash::make($guard, (string) $user->getAuthPassword()));
    }

    /**
     * An entry of the audit log: the administrator is the actor and the
     * impersonator, the impersonated user the subject.
     *
     * @param  array{type: string|null, id: int|string}|null  $goneImpersonator  For an administrator deleted since.
     */
    private function audit(string $event, ?Model $impersonator, Model $target, ?array $goneImpersonator = null): void
    {
        if (! (bool) config('admin.audit.enabled', true)) {
            return;
        }

        $type = $impersonator?->getMorphClass() ?? ($goneImpersonator['type'] ?? null);
        $id = $impersonator?->getKey() ?? ($goneImpersonator['id'] ?? null);

        $attributes = [
            'actor_type' => $type,
            'actor_id' => $id,
            'subject_type' => $target->getMorphClass(),
            'subject_id' => $target->getKey(),
            'event' => $event,
            'changes' => ['payload' => [
                'guard' => $this->guard(),
                'impersonator_id' => $id,
                'impersonator_name' => $impersonator?->getAttribute('name'),
                'target_id' => $target->getKey(),
                'target_name' => $target->getAttribute('name'),
            ]],
            ...\Dskripchenko\LaravelAdmin\Audit\AuditLog::requestContext(),
        ];
        if (\Dskripchenko\LaravelAdmin\Audit\AuditLog::supportsImpersonator()) {
            $attributes['impersonator_type'] = $type;
            $attributes['impersonator_id'] = $id;
        }

        \Dskripchenko\LaravelAdmin\Audit\AuditLog::create($attributes);
    }

    /**
     * Blocks impersonating a target who holds more rights than the
     * impersonator, when `block_higher_powered` is on.
     *
     * Every permission the target holds must be the impersonator's too —
     * literally or through hasAccess(). A target whose permissions cannot be
     * listed counts as higher, unless the impersonator holds `*`.
     */
    public function isHigherPowered(Authenticatable&Model $impersonator, Authenticatable&Model $target): bool
    {
        if (! (bool) config('admin.auth.impersonation.block_higher_powered', false)) {
            return false;
        }

        $imp = method_exists($impersonator, 'getAllPermissions')
            ? array_values(array_filter((array) $impersonator->getAllPermissions(), 'is_string'))
            : [];

        // An impersonator holding `*` is always higher or equal.
        if (in_array('*', $imp, true)) {
            return false;
        }

        // A target whose rights cannot be listed cannot be compared — and a
        // check that cannot answer refuses. It used to let such a target
        // through, which on a host whose user model has no
        // getAllPermissions() switched the protection off altogether.
        if (! method_exists($target, 'getAllPermissions')) {
            return true;
        }

        // Does the target hold a permission the impersonator does not? A key
        // the impersonator holds through a wildcard of their own, or through
        // the host's permission bridge, is theirs — asked through hasAccess(),
        // the same check every action makes.
        foreach ((array) $target->getAllPermissions() as $permission) {
            if (! is_string($permission) || $permission === '' || in_array($permission, $imp, true)) {
                continue;
            }
            if (method_exists($impersonator, 'hasAccess') && $impersonator->hasAccess($permission) === true) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function guard(): string
    {
        return \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
    }
}
