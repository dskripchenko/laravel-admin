<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Testing\Concerns;

use Dskripchenko\LaravelAdmin\Panel\Panels;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * The trait for a host project's admin tests.
 *
 * Usage:
 *
 *     class MyTest extends TestCase
 *     {
 *         use ActsAsAdmin;
 *
 *         it('does something', function () {
 *             $admin = $this->actingAsAdmin(permissions: ['admin.users.view']);
 *             $this->getJson('/api/admin/users/meta')->assertOk();
 *         });
 *     }
 *
 * `actingAsAdmin()` creates a user of the panel's user model — AdminUser in
 * the dedicated strategy, the host's own model in the shared one —
 * optionally assigns a role with the given permissions, and signs in on the
 * panel's guard. It returns the user it created.
 *
 * In the shared strategy a user without an admin role is not an
 * administrator at all, so the user always gets a role there, if an empty one.
 *
 * `actingAsSuperAdmin()` is the shortcut: a user with the `*` permission, and
 * so full access to every resource.
 */
trait ActsAsAdmin
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $permissions
     */
    public function actingAsAdmin(array $attributes = [], array $permissions = []): Model&Authenticatable
    {
        $panel = Panels::current();
        /** @var class-string<Model&Authenticatable> $model */
        $model = $panel->authModel();

        $suffix = uniqid();
        /** @var Model&Authenticatable $admin */
        $admin = $model::query()->create(array_merge([
            'name' => 'Admin '.$suffix,
            'email' => 'admin-'.$suffix.'@example.com',
            'password' => 'secret',
        ], $attributes));

        $shared = (string) ($panel->auth['strategy'] ?? config('admin.auth.strategy', 'dedicated')) === 'shared';
        if (($permissions !== [] || $shared) && method_exists($admin, 'assignRole')) {
            $role = Role::create([
                'name' => 'TestRole',
                'slug' => 'role-'.$suffix,
                'permissions' => $permissions,
            ]);
            $admin->assignRole($role);
            $admin->refresh();
        }

        $this->actingAs($admin, $panel->guard);

        return $admin;
    }

    /**
     * Creates an administrator with the `*` permission — full access.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function actingAsSuperAdmin(array $attributes = []): Model&Authenticatable
    {
        return $this->actingAsAdmin($attributes, ['*']);
    }
}
