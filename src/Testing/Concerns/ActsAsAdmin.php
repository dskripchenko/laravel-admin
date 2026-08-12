<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Testing\Concerns;

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;

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
 * `actingAsAdmin()` creates a new AdminUser, optionally assigns a role with
 * the given permissions, and calls actingAs($admin, 'admin') along the way. It
 * returns the user it created.
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
    public function actingAsAdmin(array $attributes = [], array $permissions = []): AdminUser
    {
        $admin = AdminUser::create(array_merge([
            'name' => 'Admin '.uniqid(),
            'email' => 'admin-'.uniqid().'@example.com',
            'password' => 'secret',
        ], $attributes));

        if ($permissions !== []) {
            $role = Role::create([
                'name' => 'TestRole',
                'slug' => 'role-'.uniqid(),
                'permissions' => $permissions,
            ]);
            $admin->assignRole($role);
            $admin->refresh();
        }

        $this->actingAs($admin, 'admin');

        return $admin;
    }

    /**
     * Creates an administrator with the `*` permission — full access.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function actingAsSuperAdmin(array $attributes = []): AdminUser
    {
        return $this->actingAsAdmin($attributes, ['*']);
    }
}
