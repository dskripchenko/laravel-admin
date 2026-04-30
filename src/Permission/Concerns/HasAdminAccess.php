<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Permission\Concerns;

use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Trait для модели администратора (AdminUser либо host'овской User в shared-режиме).
 *
 * Подключает morphToMany через `admin_role_assignments` pivot, добавляет
 * методы `hasAccess()`, `assignRole()`, `revokeRole()`. Wildcard-permissions
 * (`*`, `admin.users.*`) обрабатываются на уровне `Role::hasPermission()`.
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Role> $roles
 */
trait HasAdminAccess
{
    public function roles(): MorphToMany
    {
        /** @phpstan-ignore-next-line method.notFound */
        return $this->morphToMany(
            Role::class,
            'assignable',
            'admin_role_assignments',
            'assignable_id',
            'role_id',
        )->withTimestamps();
    }

    /**
     * Имеет ли пользователь permission через любую назначенную роль.
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
     * Имеет ли пользователь хотя бы один из перечисленных permissions.
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
     * Имеет ли пользователь все перечисленные permissions.
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
     * @param  Role|int|string  $role  Role-instance, id, либо slug.
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
     * Все permissions пользователя из всех ролей (плоский уникальный список).
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
