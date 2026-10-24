<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Permission;

use Dskripchenko\LaravelAdmin\Audit\AuditLog;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;

/**
 * Viewing the panel as a role.
 *
 * An administrator whose own roles grant `admin.auth.role_preview.permission`
 * (`*` by default — the Super Admin) may look at the panel through another
 * role's eyes: the menu, the screens, the actions and every backend check
 * answer as if that role were the only one they held. Unlike an
 * impersonation, nobody else's account is entered — the session stays the
 * administrator's own, and the audit keeps naming them.
 *
 * The session keeps `{type, id, role}`. HasAdminAccess asks roleFor() on every
 * check and gets the role only for the very user who started the preview, in
 * a request that carries that session: another user checked in the same
 * request and a Bearer request are never affected.
 */
final class RolePreview
{
    public const SESSION_KEY = 'admin_role_preview';

    /**
     * The previewed role, memoized for the request by slug; false — looked up and gone.
     *
     * @var array<string, Role|false>
     */
    private array $roles = [];

    public function enabled(): bool
    {
        return (bool) config('admin.auth.role_preview.enabled', true);
    }

    /**
     * The permission one's own roles must grant to preview roles.
     */
    public function requiredPermission(): string
    {
        return (string) config('admin.auth.role_preview.permission', '*');
    }

    /**
     * Whether the user may preview roles — judged by their real roles, never by
     * the role being previewed, or the way back would depend on it.
     */
    public function allowedFor(?object $user): bool
    {
        return $this->enabled()
            && $user !== null
            && method_exists($user, 'hasRealAccess')
            && $user->hasRealAccess($this->requiredPermission());
    }

    /**
     * The roles one may preview: all of them, by name.
     *
     * @return list<array{slug: string, name: string}>
     */
    public function options(): array
    {
        return Role::query()->orderBy('name')->get(['slug', 'name'])
            ->map(static fn (Role $role): array => ['slug' => (string) $role->slug, 'name' => (string) $role->name])
            ->values()->all();
    }

    public function start(Model $user, Role $role): void
    {
        Session::put(self::SESSION_KEY, [
            'type' => $user->getMorphClass(),
            'id' => $user->getKey(),
            'role' => (string) $role->slug,
        ]);
        $this->roles = [];
        $this->audit('role_preview.started', $user, $role);
    }

    public function stop(?Model $user = null): void
    {
        $role = $user !== null ? $this->roleFor($user) : null;
        Session::forget(self::SESSION_KEY);
        $this->roles = [];
        if ($user !== null) {
            $this->audit('role_preview.stopped', $user, $role);
        }
    }

    /**
     * The role the user is previewing in this request, or null.
     *
     * A role deleted since the preview began ends it: the user is back to
     * their own roles rather than left with none.
     */
    public function roleFor(Model $user): ?Role
    {
        $state = $this->state();
        if ($state === null
            || $state['type'] !== $user->getMorphClass()
            || (string) $state['id'] !== (string) $user->getKey()) {
            return null;
        }

        $slug = (string) $state['role'];
        if (! array_key_exists($slug, $this->roles)) {
            $this->roles[$slug] = Role::query()->where('slug', $slug)->first() ?? false;
        }
        $role = $this->roles[$slug];
        if ($role === false) {
            Session::forget(self::SESSION_KEY);

            return null;
        }

        return $role;
    }

    /**
     * What the shell shows: the previewed role of the user, or null.
     *
     * @return array{slug: string, name: string}|null
     */
    public function describe(?object $user): ?array
    {
        $role = $user instanceof Model ? $this->roleFor($user) : null;

        return $role === null ? null : ['slug' => (string) $role->slug, 'name' => (string) $role->name];
    }

    /**
     * @return array{type: string, id: int|string, role: string}|null
     */
    private function state(): ?array
    {
        try {
            $request = request();
            // A token request is a client of its own, not the browser session.
            if (! $request->hasSession() || $request->bearerToken() !== null) {
                return null;
            }
            $state = $request->session()->get(self::SESSION_KEY);
        } catch (\Throwable) {
            // No request and no session outside HTTP: nothing is previewed.
            return null;
        }

        return is_array($state) && isset($state['type'], $state['id'], $state['role']) ? $state : null;
    }

    private function audit(string $event, Model $user, ?Role $role): void
    {
        if (! (bool) config('admin.audit.enabled', true) || ! (bool) config('admin.audit.log_auth_events', true)) {
            return;
        }

        AuditLog::create([
            'actor_type' => $user->getMorphClass(),
            'actor_id' => $user->getKey(),
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->getKey(),
            'event' => $event,
            'changes' => ['payload' => ['role' => $role?->slug]],
            ...AuditLog::requestContext(),
        ]);
    }
}
