<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Testing\Concerns;

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;

/**
 * Trait для admin-тестов host-проекта.
 *
 * Использование:
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
 * `actingAsAdmin()` создаёт нового AdminUser, опционально ассайнит роль с
 * permissions, заодно регистрирует actingAs($admin, 'admin'). Возвращает
 * созданного юзера для дальнейшей работы.
 *
 * `actingAsSuperAdmin()` — shortcut: создаёт пользователя с `*` permission
 * (полный доступ ко всем Resource'ам).
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
     * Создаёт админа с `*` permission (полный доступ).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function actingAsSuperAdmin(array $attributes = []): AdminUser
    {
        return $this->actingAsAdmin($attributes, ['*']);
    }
}
