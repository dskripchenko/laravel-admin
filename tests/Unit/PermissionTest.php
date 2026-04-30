<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\ItemPermission;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Permission\PermissionRegistry;

it('ItemPermission::group + addPermission chains fluently', function (): void {
    $group = ItemPermission::group('Системы')
        ->addPermission('admin.systems.users.view', 'Пользователи: просмотр')
        ->addPermission('admin.systems.users.update', 'Пользователи: редактирование');

    expect($group->group)->toBe('Системы');
    expect($group->keys())->toBe([
        'admin.systems.users.view',
        'admin.systems.users.update',
    ]);
});

it('ItemPermission serializes to UI-friendly array', function (): void {
    $group = ItemPermission::group('Roles')
        ->addPermission('admin.roles.view', 'View')
        ->addPermission('admin.roles.update', 'Edit');

    $arr = $group->toArray();
    expect($arr['name'])->toBe('Roles');
    expect($arr['items'])->toHaveCount(2);
    expect($arr['items'][0])->toBe(['key' => 'admin.roles.view', 'label' => 'View']);
});

it('PermissionRegistry stores and lists groups', function (): void {
    $registry = new PermissionRegistry;
    $registry->add(ItemPermission::group('A')->addPermission('a.x', 'AX'));
    $registry->add(ItemPermission::group('B')->addPermission('b.y', 'BY'));

    expect($registry->groups())->toHaveCount(2);
    expect($registry->flat())->toBe(['a.x', 'b.y']);
    expect($registry->knows('a.x'))->toBeTrue();
    expect($registry->knows('unknown'))->toBeFalse();
});

it('PermissionRegistry merges duplicate groups', function (): void {
    $registry = new PermissionRegistry;
    $registry->add(ItemPermission::group('Sys')->addPermission('a', 'A'));
    $registry->add(ItemPermission::group('Sys')->addPermission('b', 'B'));

    expect($registry->groups())->toHaveCount(1);
    expect($registry->flat())->toBe(['a', 'b']);
});

it('Role::hasPermission supports exact match', function (): void {
    $role = new Role(['permissions' => ['admin.users.view', 'admin.users.update']]);

    expect($role->hasPermission('admin.users.view'))->toBeTrue();
    expect($role->hasPermission('admin.users.delete'))->toBeFalse();
});

it('Role::hasPermission supports wildcard *', function (): void {
    $role = new Role(['permissions' => ['*']]);

    expect($role->hasPermission('admin.users.view'))->toBeTrue();
    expect($role->hasPermission('anything.else'))->toBeTrue();
});

it('Role::hasPermission supports prefix wildcard like admin.users.*', function (): void {
    $role = new Role(['permissions' => ['admin.users.*']]);

    expect($role->hasPermission('admin.users.view'))->toBeTrue();
    expect($role->hasPermission('admin.users.delete'))->toBeTrue();
    expect($role->hasPermission('admin.orders.view'))->toBeFalse();
});

it('AdminUser uses HasAdminAccess trait via roles', function (): void {
    // admin_roles + admin_role_assignments уже созданы миграциями.
    $admin = AdminUser::create([
        'name' => 'Test',
        'email' => 'test-perm-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $role = Role::create([
        'name' => 'Editor',
        'slug' => 'editor',
        'permissions' => ['admin.posts.view', 'admin.posts.update'],
    ]);

    $admin->assignRole($role);
    $admin->refresh();

    expect($admin->hasAccess('admin.posts.view'))->toBeTrue();
    expect($admin->hasAccess('admin.posts.delete'))->toBeFalse();
    expect($admin->hasAnyAccess(['admin.posts.delete', 'admin.posts.view']))->toBeTrue();
    expect($admin->hasAllAccess(['admin.posts.view', 'admin.posts.update']))->toBeTrue();
    expect($admin->hasAllAccess(['admin.posts.view', 'admin.posts.delete']))->toBeFalse();

    expect($admin->getAllPermissions())->toBe(['admin.posts.view', 'admin.posts.update']);
});

it('Admin::permissions delegates to registry', function (): void {
    /** @var PermissionRegistry $registry */
    $registry = app(PermissionRegistry::class);
    $registry->clear();

    $manager = app(Dskripchenko\LaravelAdmin\Admin::class);
    $manager->permissions([
        ItemPermission::group('X')->addPermission('a', 'A'),
        ItemPermission::group('Y')->addPermission('b', 'B'),
    ]);

    expect($registry->groups())->toHaveCount(2);
    expect($registry->flat())->toBe(['a', 'b']);
});

it('Admin::permissions accepts a single ItemPermission', function (): void {
    /** @var PermissionRegistry $registry */
    $registry = app(PermissionRegistry::class);
    $registry->clear();

    app(Dskripchenko\LaravelAdmin\Admin::class)
        ->permissions(ItemPermission::group('Z')->addPermission('z.x', 'ZX'));

    expect($registry->flat())->toBe(['z.x']);
});
