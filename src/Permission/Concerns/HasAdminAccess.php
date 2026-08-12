<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Permission\Concerns;

use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * The trait of an administrator model — AdminUser, or the host's own User in
 * the shared mode.
 *
 * It adds a morphToMany through the `admin_role_assignments` pivot, plus the
 * `hasAccess()`, `assignRole()` and `revokeRole()` methods. The wildcard
 * permissions (`*`, `admin.users.*`) are handled inside
 * `Role::hasPermission()`.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Role> $roles
 */
trait HasAdminAccess
{
    /**
     * @return MorphToMany<Role, $this>
     */
    public function roles(): MorphToMany
    {
        return $this->morphToMany(
            Role::class,
            'assignable',
            'admin_role_assignments',
            'assignable_id',
            'role_id',
        )->withTimestamps();
    }

    /**
     * Tells whether the user holds a permission through any of their roles.
     */
    public function hasAccess(string $permission): bool
    {
        foreach ($this->roles as $role) {
            if ($role->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether the user holds at least one of the listed permissions.
     *
     * @param  list<string>  $permissions
     */
    public function hasAnyAccess(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasAccess($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether the user holds every one of the listed permissions.
     *
     * @param  list<string>  $permissions
     */
    public function hasAllAccess(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->hasAccess($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  Role|int|string  $role  A Role instance, an id or a slug.
     */
    public function assignRole(Role|int|string $role): void
    {
        $roleId = match (true) {
            $role instanceof Role => $role->getKey(),
            is_int($role) => $role,
            default => Role::query()->where('slug', $role)->firstOrFail()->getKey(),
        };

        $this->roles()->syncWithoutDetaching([$roleId]);
    }

    public function revokeRole(Role|int|string $role): void
    {
        $roleId = match (true) {
            $role instanceof Role => $role->getKey(),
            is_int($role) => $role,
            default => Role::query()->where('slug', $role)->firstOrFail()->getKey(),
        };

        $this->roles()->detach($roleId);
    }

    /**
     * Every permission the user holds across every role, as one unique flat list.
     *
     * @return list<string>
     */
    public function getAllPermissions(): array
    {
        $all = [];
        foreach ($this->roles as $role) {
            foreach ((array) $role->permissions as $key) {
                $all[] = (string) $key;
            }
        }

        return array_values(array_unique($all));
    }
}
